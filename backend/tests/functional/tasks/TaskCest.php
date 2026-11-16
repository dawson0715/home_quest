<?php

declare(strict_types=1);

namespace tests\functional\tasks;

use app\models\Task;
use app\models\TaskOccurrence;
use FunctionalTester;
use tests\_support\FamilyFactory;
use Yii;

final class TaskCest
{
    public function _before(): void
    {
        Yii::$app->get('clock')->setNow(new \DateTimeImmutable('2026-11-16 08:00:00', new \DateTimeZone('Europe/Rome')));
    }

    public function _after(): void
    {
        Yii::$app->get('clock')->setNow(null);
    }

    public function createRoutineGeneratesTodaysOccurrences(FunctionalTester $I): void
    {
        $member = $I->amFamilyMember();
        $luca = FamilyFactory::child($member->family, 'Luca');
        $anna = FamilyFactory::child($member->family, 'Anna');

        $I->sendPost('tasks', [
            'title' => 'Routine del mattino', 'icon' => '🌅', 'points' => 10,
            'requires_approval' => false, 'recurrence_type' => 'weekly', 'recurrence_days' => 31,
            'time_of_day' => 'morning', 'assignee_ids' => [$luca->id, $anna->id],
            'checklist' => [['label' => 'Lavarsi i denti', 'icon' => '🪥'], ['label' => 'Vestirsi', 'icon' => '👕']],
        ]);

        $I->seeResponseCodeIs(201);
        $I->seeResponseContainsJson(['data' => [
            'title' => 'Routine del mattino', 'points' => 10, 'xp' => 10, 'requires_approval' => false,
            'recurrence_type' => 'weekly', 'recurrence_days' => 31, 'start_date' => '2026-11-16',
            'is_active' => true, 'checklist' => [['label' => 'Lavarsi i denti'], ['label' => 'Vestirsi']],
        ]]);
        $I->assertEqualsCanonicalizing([$luca->id, $anna->id], $I->grabDataFromResponseByJsonPath('$.data.assignee_ids')[0]);
        $taskId = $I->grabDataFromResponseByJsonPath('$.data.id')[0];
        $I->assertSame(2, (int) TaskOccurrence::find()->where(['task_id' => $taskId, 'due_date' => '2026-11-16'])->count());
    }

    public function validation(FunctionalTester $I): void
    {
        $member = $I->amFamilyMember();
        $other = FamilyFactory::child(FamilyFactory::family('Bianchi'));

        $I->sendPost('tasks', ['title' => '', 'recurrence_type' => 'weekly', 'points' => -1, 'assignee_ids' => [$other->id]]);
        $I->seeErrorEnvelope(422, 'validation_failed');
        $fields = $I->grabDataFromResponseByJsonPath('$.meta.errors[*].field');
        $I->assertEqualsCanonicalizing(['title', 'icon', 'points', 'recurrence_days', 'assignee_ids'], $fields);

        $child = FamilyFactory::child($member->family);
        $I->sendPost('tasks', [
            'title' => 'X', 'icon' => '⭐', 'points' => 1, 'recurrence_type' => 'interval', 'interval_days' => 0,
            'assignee_ids' => [$child->id], 'checklist' => [['label' => '']],
        ]);
        $fields = $I->grabDataFromResponseByJsonPath('$.meta.errors[*].field');
        $I->assertEqualsCanonicalizing(['interval_days', 'checklist'], $fields);
    }

    public function updateSyncsChecklistAssigneesAndOccurrences(FunctionalTester $I): void
    {
        $member = $I->amFamilyMember();
        $luca = FamilyFactory::child($member->family, 'Luca');
        $anna = FamilyFactory::child($member->family, 'Anna');
        $I->sendPost('tasks', [
            'title' => 'Letto', 'icon' => '🛏️', 'points' => 5, 'recurrence_type' => 'daily',
            'assignee_ids' => [$luca->id, $anna->id], 'checklist' => [['label' => 'A'], ['label' => 'B']],
        ]);
        $taskId = $I->grabDataFromResponseByJsonPath('$.data.id')[0];
        $keepId = $I->grabDataFromResponseByJsonPath('$.data.checklist[1].id')[0];

        $I->sendPatch("tasks/$taskId", [
            'points' => 7, 'assignee_ids' => [$anna->id],
            'checklist' => [['id' => $keepId, 'label' => 'B!'], ['label' => 'C']],
        ]);

        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['data' => ['points' => 7, 'xp' => 5, 'assignee_ids' => [$anna->id], 'checklist' => [['id' => $keepId, 'label' => 'B!'], ['label' => 'C']]]]);
        $children = TaskOccurrence::find()->where(['task_id' => $taskId])->select('child_id')->column();
        $I->assertSame([$anna->id], array_map('intval', $children));

        $I->sendPatch("tasks/$taskId", ['recurrence_type' => 'weekly', 'recurrence_days' => 64]); // Sundays: not today
        $I->assertSame(0, (int) TaskOccurrence::find()->where(['task_id' => $taskId])->count());
    }

    public function deleteWithoutHistoryRemovesWithHistoryDeactivates(FunctionalTester $I): void
    {
        $member = $I->amFamilyMember();
        $child = FamilyFactory::child($member->family);
        $fresh = FamilyFactory::task($member->family, [$child]);
        $used = FamilyFactory::task($member->family, [$child]);
        $occurrence = new TaskOccurrence(['task_id' => $used->id, 'child_id' => $child->id, 'due_date' => '2026-11-15', 'status' => 'approved']);
        $occurrence->family_id = $member->family_id;
        $occurrence->save(false);

        $I->sendDelete("tasks/{$fresh->id}");
        $I->seeResponseCodeIs(204);
        $I->dontSeeRecord(Task::class, ['id' => $fresh->id]);

        $I->sendDelete("tasks/{$used->id}");
        $I->seeResponseCodeIs(204);
        $I->seeRecord(Task::class, ['id' => $used->id, 'is_active' => 0]);

        $I->sendGet('tasks?active=1');
        $I->assertSame([], $I->grabDataFromResponseByJsonPath('$.data[*].id'));
        $I->sendGet("tasks?child_id={$child->id}");
        $I->assertSame([$used->id], $I->grabDataFromResponseByJsonPath('$.data[*].id'));
    }

    public function tasksAreIsolatedAndParentOnly(FunctionalTester $I): void
    {
        $bianchi = FamilyFactory::family('Bianchi');
        $other = FamilyFactory::task($bianchi, [FamilyFactory::child($bianchi)]);
        $I->amFamilyMember();

        $I->sendGet('tasks');
        $I->assertSame([], $I->grabDataFromResponseByJsonPath('$.data[*].id'));
        foreach (['sendGet', 'sendPatch', 'sendDelete'] as $method) {
            $I->$method("tasks/{$other->id}", ['title' => 'X']);
            $I->seeErrorEnvelope(404, 'not_found');
        }
        $I->seeRecord(Task::class, ['id' => $other->id, 'title' => 'Routine', 'is_active' => 1]);
    }

    public function templatesFilterByAge(FunctionalTester $I): void
    {
        $I->amFamilyMember();
        $I->sendGet('task-templates?age=4');
        $I->seeResponseCodeIs(200);
        $keys = $I->grabDataFromResponseByJsonPath('$.data[*].title_key');
        $I->assertContains('tidy_toys', $keys);
        $I->assertNotContains('walk_dog', $keys);
    }
}
