<?php

declare(strict_types=1);

namespace tests\functional\meals;

use app\models\ChildProfile;
use app\models\FamilyMember;
use app\models\MealEntry;
use app\models\Recipe;
use FunctionalTester;
use tests\_support\FamilyFactory;

final class MealPlanCest
{
    private FamilyMember $parent;
    private ChildProfile $luca;
    private Recipe $pasta;

    public function _before(FunctionalTester $I): void
    {
        $this->parent = FamilyFactory::member(FamilyFactory::family('Rossi'));
        $this->luca = FamilyFactory::child($this->parent->family, 'Luca');
        FamilyFactory::child($this->parent->family, 'Anna');
        $this->pasta = new Recipe(['title' => 'Pasta al pomodoro']);
        $this->pasta->family_id = $this->parent->family_id;
        $this->pasta->save(false);
        $I->amAuthenticatedAs($this->parent->user);
    }

    /** @return array<string, mixed> */
    private function slot(FunctionalTester $I, int $day, string $meal): array
    {
        foreach ($I->grabDataFromResponseByJsonPath('$.data.slots[*]') as $slot) {
            if ($slot['day_index'] === $day && $slot['meal_type'] === $meal) {
                return $slot;
            }
        }
        $I->fail("slot $day/$meal not found");
    }

    public function weekIsCreatedWithAllSlots(FunctionalTester $I): void
    {
        $I->sendGet('meal-plans/2026-11-16');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['data' => ['week_start' => '2026-11-16', 'meal_types' => ['breakfast', 'lunch', 'snack', 'dinner']]]);
        $I->assertCount(28, $I->grabDataFromResponseByJsonPath('$.data.slots[*]'));
        $sunday = $this->slot($I, 6, 'dinner');
        $I->assertEquals(['date' => '2026-11-22', 'servings' => 3, 'entries' => [], 'id' => null], array_intersect_key($sunday, array_flip(['date', 'servings', 'entries', 'id'])));

        $I->sendPatch('family', ['settings' => ['meal_types' => ['lunch', 'dinner']]]);
        $I->sendGet('meal-plans/2026-11-16');
        $I->assertCount(14, $I->grabDataFromResponseByJsonPath('$.data.slots[*]'));

        $I->sendGet('meal-plans/2026-11-17');
        $I->seeErrorEnvelope(422, 'validation_failed');
    }

    public function putSlotNotesCopyAndTemplates(FunctionalTester $I): void
    {
        $I->sendPut('meal-plans/2026-11-16/slots/0/dinner', ['servings' => 5, 'entries' => [['recipe_id' => $this->pasta->id], ['free_text' => 'Insalata']]]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['data' => ['servings' => 5, 'entries' => [
            ['title' => 'Pasta al pomodoro', 'recipe' => ['id' => $this->pasta->id], 'status' => 'planned'],
            ['title' => 'Insalata', 'recipe' => null],
        ]]]);
        $keep = $I->grabDataFromResponseByJsonPath('$.data.entries[0].id')[0];

        $I->sendPut('meal-plans/2026-11-16/slots/0/dinner', ['entries' => [['id' => $keep, 'recipe_id' => $this->pasta->id]]]);
        $I->assertSame([$keep], $I->grabDataFromResponseByJsonPath('$.data.entries[*].id'));
        $I->sendPut('meal-plans/2026-11-16/slots/0/dinner', ['entries' => [['free_text' => '']]]);
        $I->seeErrorEnvelope(422, 'validation_failed');
        $I->sendPut('meal-plans/2026-11-16/slots/9/dinner', ['entries' => []]);
        $I->seeErrorEnvelope(422, 'validation_failed');

        $I->sendPatch('meal-plans/2026-11-16', ['notes' => 'Ospiti venerdì']);
        $I->seeResponseContainsJson(['data' => ['notes' => 'Ospiti venerdì']]);

        $I->sendPost('meal-plans/2026-11-23/copy-from', ['source_week_start' => '2026-11-16']);
        $I->seeResponseCodeIs(200);
        $I->assertSame(['Pasta al pomodoro'], array_column($this->slot($I, 0, 'dinner')['entries'], 'title'));
        $I->assertSame(5, $this->slot($I, 0, 'dinner')['servings']);

        $I->sendPost('meal-plans/2026-11-16/save-as-template', ['name' => 'Settimana classica']);
        $I->seeResponseCodeIs(201);
        $templateId = $I->grabDataFromResponseByJsonPath('$.data.id')[0];
        $I->sendGet('meal-templates');
        $I->seeResponseContainsJson(['data' => [['id' => $templateId, 'name' => 'Settimana classica', 'dishes' => 1]]]);

        $I->sendPut('meal-plans/2026-11-30/slots/2/lunch', ['entries' => [['free_text' => 'Avanzi']]]);
        $I->sendPost('meal-plans/2026-11-30/copy-from', ['template_id' => $templateId]);
        $I->assertSame([], $this->slot($I, 2, 'lunch')['entries']);
        $I->assertSame(['Pasta al pomodoro'], array_column($this->slot($I, 0, 'dinner')['entries'], 'title'));

        $I->sendDelete("meal-templates/$templateId");
        $I->seeResponseCodeIs(204);
        $I->sendPost('meal-plans/2026-11-30/copy-from', ['template_id' => $templateId]);
        $I->seeErrorEnvelope(404, 'not_found');
    }

    public function votesAndChildProposals(FunctionalTester $I): void
    {
        $I->sendPut('meal-plans/2026-11-16/slots/1/lunch', ['entries' => [['recipe_id' => $this->pasta->id]]]);
        $entryId = $I->grabDataFromResponseByJsonPath('$.data.entries[0].id')[0];
        $I->sendPost("meal-entries/$entryId/vote", ['value' => 1]);
        $I->seeResponseContainsJson(['data' => ['votes' => ['up' => 1, 'down' => 0, 'mine' => 1]]]);

        $I->amChild($this->luca);
        $I->sendGet('meal-plans/2026-11-16');
        $I->seeResponseCodeIs(200);
        $I->sendPost("meal-entries/$entryId/vote", ['value' => -1]);
        $I->seeResponseContainsJson(['data' => ['votes' => ['up' => 1, 'down' => 1, 'mine' => -1]]]);
        $I->sendPost("meal-entries/$entryId/vote", ['value' => 0]);
        $I->seeResponseContainsJson(['data' => ['votes' => ['up' => 1, 'down' => 0, 'mine' => 0]]]);
        $I->sendPost("meal-entries/$entryId/vote", ['value' => 5]);
        $I->seeErrorEnvelope(422, 'validation_failed');

        $I->sendPost('meal-plans/2026-11-16/proposals', ['day' => 4, 'meal_type' => 'dinner', 'free_text' => 'Pizza!']);
        $I->seeResponseCodeIs(201);
        $I->seeResponseContainsJson(['data' => ['title' => 'Pizza!', 'status' => 'proposed', 'proposed_by' => ['name' => 'Luca']]]);
        $proposal = $I->grabDataFromResponseByJsonPath('$.data.id')[0];
        $I->sendPost('meal-plans/2026-11-16/proposals', ['day' => 4, 'meal_type' => 'dinner', 'recipe_id' => $this->pasta->id]);
        $I->seeErrorEnvelope(409, 'conflict');
        $I->sendPut('meal-plans/2026-11-16/slots/4/dinner', ['entries' => []]);
        $I->seeErrorEnvelope(403, 'forbidden');
        $I->sendPost("meal-entries/$proposal/accept");
        $I->seeErrorEnvelope(403, 'forbidden');

        $I->amAuthenticatedAs($this->parent->user);
        // replacing planned dishes keeps the child's proposal
        $I->sendPut('meal-plans/2026-11-16/slots/4/dinner', ['entries' => [['free_text' => 'Minestrone']]]);
        $I->assertSame(['Minestrone', 'Pizza!'], array_column($I->grabDataFromResponseByJsonPath('$.data.entries')[0], 'title'));
        $I->sendPost("meal-entries/$proposal/accept");
        $I->seeResponseContainsJson(['data' => ['status' => 'planned']]);
        $I->sendPost("meal-entries/$proposal/reject");
        $I->seeErrorEnvelope(422, 'invalid_transition');

        $I->amChild($this->luca);
        $I->sendPost('meal-plans/2026-11-16/proposals', ['day' => 5, 'meal_type' => 'lunch', 'free_text' => 'Patatine']);
        $second = $I->grabDataFromResponseByJsonPath('$.data.id')[0];
        $I->amAuthenticatedAs($this->parent->user);
        $I->sendPost("meal-entries/$second/reject");
        $I->seeRecord(MealEntry::class, ['id' => $second, 'status' => 'rejected']);
        $I->sendGet('meal-plans/2026-11-16');
        $I->assertSame([], $this->slot($I, 5, 'lunch')['entries']);
    }

    public function familiesAreIsolated(FunctionalTester $I): void
    {
        $I->sendPut('meal-plans/2026-11-16/slots/0/dinner', ['entries' => [['free_text' => 'Nostra cena']]]);
        $entryId = $I->grabDataFromResponseByJsonPath('$.data.entries[0].id')[0];

        $I->amFamilyMember(FamilyFactory::family('Bianchi'));
        $I->sendGet('meal-plans/2026-11-16');
        $I->assertSame([], array_merge(...array_column($I->grabDataFromResponseByJsonPath('$.data.slots')[0], 'entries')));
        foreach (['vote', 'accept'] as $action) {
            $I->sendPost("meal-entries/$entryId/$action", ['value' => 1]);
            $I->seeErrorEnvelope(404, 'not_found');
        }
        $I->sendPut('meal-plans/2026-11-16/slots/0/dinner', ['entries' => [['recipe_id' => $this->pasta->id]]]);
        $I->seeErrorEnvelope(422, 'validation_failed');
        $I->sendPost('meal-plans/2026-11-23/copy-from', ['source_week_start' => '2026-11-16']);
        $I->assertSame([], array_merge(...array_column($I->grabDataFromResponseByJsonPath('$.data.slots')[0], 'entries')));
    }

    public function helpCookingTaskLinksToAMealSlot(FunctionalTester $I): void
    {
        $I->sendPut('meal-plans/2026-11-16/slots/2/dinner', ['entries' => [['recipe_id' => $this->pasta->id]]]);
        $slotId = $I->grabDataFromResponseByJsonPath('$.data.id')[0];

        $I->sendPost('tasks', [
            'title' => 'Aiuta a cucinare', 'icon' => '👩‍🍳', 'points' => 15, 'recurrence_type' => 'none',
            'start_date' => '2026-11-18', 'due_date' => '2026-11-18', 'assignee_ids' => [$this->luca->id],
            'linked_meal_slot_id' => $slotId,
        ]);
        $I->seeResponseCodeIs(201);
        $I->seeResponseContainsJson(['data' => ['linked_meal_slot_id' => $slotId]]);

        $I->amFamilyMember(FamilyFactory::family('Bianchi'));
        $other = FamilyFactory::child(FamilyFactory::family('Verdi'));
        $I->sendPost('tasks', ['title' => 'X', 'icon' => '⭐', 'points' => 1, 'recurrence_type' => 'daily', 'assignee_ids' => [$other->id], 'linked_meal_slot_id' => $slotId]);
        $I->seeErrorEnvelope(422, 'validation_failed');
        $I->assertContains('linked_meal_slot_id', $I->grabDataFromResponseByJsonPath('$.meta.errors[*].field'));
    }
}
