<?php

declare(strict_types=1);

namespace tests\functional\tasks;

use app\models\ChildProfile;
use app\models\FamilyMember;
use app\models\PointTransaction;
use app\models\TaskOccurrence;
use FunctionalTester;
use tests\_support\FamilyFactory;
use Yii;

final class OccurrenceCest
{
    private FamilyMember $parent;
    private ChildProfile $luca;
    private ChildProfile $anna;

    public function _before(FunctionalTester $I): void
    {
        Yii::$app->get('clock')->setNow(new \DateTimeImmutable('2026-11-16 08:00:00', new \DateTimeZone('Europe/Rome')));
        $this->parent = FamilyFactory::member(FamilyFactory::family('Rossi'));
        $this->luca = FamilyFactory::child($this->parent->family, 'Luca');
        $this->anna = FamilyFactory::child($this->parent->family, 'Anna');
    }

    public function _after(): void
    {
        Yii::$app->get('clock')->setNow(null);
    }

    /** @return array{0: int, 1: list<int>} occurrence id and checklist item ids from /me/today */
    private function todayFor(FunctionalTester $I, string $title): array
    {
        $I->sendGet('me/today');
        $I->seeResponseCodeIs(200);
        foreach (['morning', 'afternoon', 'evening', 'any'] as $group) {
            foreach ($I->grabDataFromResponseByJsonPath('$.data.groups.' . $group . '[*]') as $o) {
                if ($o['title'] === $title) {
                    return [$o['id'], array_column($o['checklist'], 'id')];
                }
            }
        }
        $I->fail("No occurrence '$title' today");
    }

    public function todayGroupsByTimeOfDayAndExcludesOtherDays(FunctionalTester $I): void
    {
        $family = $this->parent->family;
        FamilyFactory::task($family, [$this->luca], ['title' => 'Mattina', 'time_of_day' => 'morning'], ['Denti', 'Vestiti']);
        FamilyFactory::task($family, [$this->luca], ['title' => 'Sera', 'time_of_day' => 'evening']);
        FamilyFactory::task($family, [$this->luca], ['title' => 'Domenica', 'recurrence_type' => 'weekly', 'recurrence_days' => 64]);
        FamilyFactory::task($family, [$this->anna], ['title' => 'Di Anna']);
        FamilyFactory::task($family, [$this->luca], ['title' => 'Camera', 'recurrence_type' => 'none', 'start_date' => '2026-11-10', 'time_of_day' => 'any']);

        $I->amChild($this->luca);
        $I->sendGet('me/today');

        $I->seeResponseContainsJson(['data' => [
            'date' => '2026-11-16',
            'child' => ['name' => 'Luca', 'level' => 1, 'xp_level_start' => 0, 'xp_next_level' => 100],
            'groups' => [
                'morning' => [['title' => 'Mattina', 'status' => 'pending', 'checklist' => [['label' => 'Denti', 'done' => false], ['label' => 'Vestiti', 'done' => false]]]],
                'evening' => [['title' => 'Sera']],
                'any' => [['title' => 'Camera']],
            ],
        ]]);
        $titles = $I->grabDataFromResponseByJsonPath('$.data.groups.*[*].title');
        $I->assertEqualsCanonicalizing(['Mattina', 'Sera', 'Camera'], $titles);
    }

    public function checklistThenSubmitWithoutApprovalAwardsPoints(FunctionalTester $I): void
    {
        FamilyFactory::task($this->parent->family, [$this->luca], ['title' => 'Mattina', 'points' => 60, 'xp' => 120], ['Denti', 'Vestiti']);
        $I->amChild($this->luca);
        [$id, $items] = $this->todayFor($I, 'Mattina');

        $I->sendPost("occurrences/$id/submit");
        $I->seeErrorEnvelope(422, 'checklist_incomplete');

        foreach ($items as $itemId) {
            $I->sendPatch("occurrences/$id/checklist", ['item_id' => $itemId, 'done' => true]);
            $I->seeResponseCodeIs(200);
        }
        $I->seeResponseContainsJson(['data' => ['checklist' => [['done' => true], ['done' => true]]]]);

        $I->sendPost("occurrences/$id/submit");
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['data' => [
            'occurrence' => ['status' => 'approved', 'points_awarded' => 60],
            'points_awarded' => 60, 'level_up' => true, 'balance' => 60, 'new_badges' => [],
        ]]);
        $I->seeRecord(ChildProfile::class, ['id' => $this->luca->id, 'points_balance' => 60, 'xp_total' => 120, 'level' => 2]);
        $I->seeRecord(PointTransaction::class, ['child_id' => $this->luca->id, 'points' => 60, 'type' => 'task', 'source_type' => 'task_occurrence', 'source_id' => $id]);

        $I->sendPost("occurrences/$id/submit");
        $I->seeErrorEnvelope(422, 'invalid_transition');
        $I->assertSame(1, (int) PointTransaction::find()->where(['child_id' => $this->luca->id])->count());
    }

    public function approvalFlowWithRejectAndRetry(FunctionalTester $I): void
    {
        FamilyFactory::task($this->parent->family, [$this->luca], ['title' => 'Camera', 'points' => 20, 'requires_approval' => true]);
        $I->amChild($this->luca);
        [$id] = $this->todayFor($I, 'Camera');

        $I->sendPost("occurrences/$id/submit");
        $I->seeResponseContainsJson(['data' => ['occurrence' => ['status' => 'submitted'], 'points_awarded' => 0]]);

        $I->amAuthenticatedAs($this->parent->user);
        $I->sendGet('occurrences?status=submitted');
        $I->seeResponseContainsJson(['data' => [['id' => $id, 'child' => ['name' => 'Luca'], 'title' => 'Camera']]]);

        $I->sendPost("occurrences/$id/reject", ['note' => 'Ci sono ancora i giochi a terra']);
        $I->seeResponseContainsJson(['data' => ['status' => 'rejected', 'review_note' => 'Ci sono ancora i giochi a terra']]);

        $I->amChild($this->luca);
        $I->sendPost("occurrences/$id/submit");
        $I->seeResponseContainsJson(['data' => ['occurrence' => ['status' => 'submitted']]]);

        $I->amAuthenticatedAs($this->parent->user);
        $I->sendPost("occurrences/$id/approve");
        $I->seeResponseContainsJson(['data' => ['occurrence' => ['status' => 'approved'], 'points_awarded' => 20]]);
        $I->sendPost("occurrences/$id/approve");
        $I->seeErrorEnvelope(422, 'invalid_transition');
        $I->seeRecord(ChildProfile::class, ['id' => $this->luca->id, 'points_balance' => 20]);
    }

    public function bulkApprove(FunctionalTester $I): void
    {
        $task = FamilyFactory::task($this->parent->family, [$this->luca, $this->anna], ['points' => 5, 'requires_approval' => true]);
        Yii::$app->db->createCommand()->batchInsert(TaskOccurrence::tableName(),
            ['family_id', 'task_id', 'child_id', 'due_date', 'status', 'created_at', 'updated_at'], [
                [$this->parent->family_id, $task->id, $this->luca->id, '2026-11-16', 'submitted', 1, 1],
                [$this->parent->family_id, $task->id, $this->anna->id, '2026-11-16', 'submitted', 1, 1],
                [$this->parent->family_id, $task->id, $this->luca->id, '2026-11-15', 'approved', 1, 1],
            ])->execute();
        $ids = array_map('intval', TaskOccurrence::find()->where(['task_id' => $task->id])->orderBy('id')->select('id')->column());

        $I->amAuthenticatedAs($this->parent->user);
        $I->sendPost('occurrences/bulk-approve', ['ids' => [...$ids, 999999]]);

        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['data' => ['approved' => [$ids[0], $ids[1]], 'skipped' => [$ids[2], 999999]]]);
        $I->seeRecord(ChildProfile::class, ['id' => $this->anna->id, 'points_balance' => 5]);
    }

    public function childrenOnlyTouchTheirOwnTodayOccurrences(FunctionalTester $I): void
    {
        $task = FamilyFactory::task($this->parent->family, [$this->luca, $this->anna]);
        $I->amChild($this->anna);
        [$annaId] = $this->todayFor($I, 'Routine');
        $I->amChild($this->luca);
        [$lucaId] = $this->todayFor($I, 'Routine');

        $I->sendPost("occurrences/$annaId/submit");
        $I->seeErrorEnvelope(404, 'not_found');
        $I->sendPost("occurrences/$lucaId/approve");
        $I->seeErrorEnvelope(403, 'forbidden');
        $I->sendGet('occurrences');
        $I->seeErrorEnvelope(403, 'forbidden');

        $old = new TaskOccurrence(['task_id' => $task->id, 'child_id' => $this->luca->id, 'due_date' => '2026-11-15', 'status' => 'pending']);
        $old->family_id = $this->parent->family_id;
        $old->save(false);
        $I->sendPost("occurrences/{$old->id}/submit");
        $I->seeErrorEnvelope(422, 'invalid_transition');

        $I->amAuthenticatedAs($this->parent->user);
        $I->sendPost("occurrences/$lucaId/submit");
        $I->seeErrorEnvelope(403, 'forbidden');
    }

    public function otherFamiliesOccurrencesAreInvisible(FunctionalTester $I): void
    {
        FamilyFactory::task($this->parent->family, [$this->luca], ['requires_approval' => true]);
        $I->amChild($this->luca);
        [$id] = $this->todayFor($I, 'Routine');
        $I->sendPost("occurrences/$id/submit");

        $I->amFamilyMember(FamilyFactory::family('Bianchi'));
        $I->sendGet('occurrences?status=submitted');
        $I->assertSame([], $I->grabDataFromResponseByJsonPath('$.data[*].id'));
        $I->sendPost("occurrences/$id/approve");
        $I->seeErrorEnvelope(404, 'not_found');
        $I->sendPost('occurrences/bulk-approve', ['ids' => [$id]]);
        $I->seeResponseContainsJson(['data' => ['approved' => [], 'skipped' => [$id]]]);
        $I->seeRecord(TaskOccurrence::class, ['id' => $id, 'status' => 'submitted']);
    }
}
