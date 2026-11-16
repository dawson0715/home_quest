<?php

declare(strict_types=1);

namespace tests\functional\tasks;

use app\models\ChildProfile;
use app\models\FamilyMember;
use app\models\PointTransaction;
use app\models\Reward;
use app\models\TaskOccurrence;
use FunctionalTester;
use tests\_support\FamilyFactory;
use Yii;

final class PlayersCest
{
    private FamilyMember $anna;
    private FamilyMember $marco;
    private ChildProfile $luca;
    private ChildProfile $sofia;

    public function _before(): void
    {
        Yii::$app->get('clock')->setNow(new \DateTimeImmutable('2026-11-16 08:00:00', new \DateTimeZone('Europe/Rome')));
        $family = FamilyFactory::family('Rossi');
        $this->anna = FamilyFactory::member($family, FamilyFactory::user('anna', 'Anna'));
        $this->marco = FamilyFactory::member($family, FamilyFactory::user('marco', 'Marco'), FamilyMember::ROLE_COPARENT);
        $this->luca = FamilyFactory::child($family, 'Luca');
        $this->sofia = FamilyFactory::child($family, 'Sofia');
    }

    public function _after(): void
    {
        Yii::$app->get('clock')->setNow(null);
    }

    private function playerOf(FamilyMember $member): ChildProfile
    {
        return ChildProfile::findOne(['user_id' => $member->user_id]) ?? throw new \RuntimeException('no player');
    }

    public function parentsHaveAGameProfileAndPlay(FunctionalTester $I): void
    {
        $marcoPlayer = $this->playerOf($this->marco);
        $I->amAuthenticatedAs($this->anna->user);

        $I->sendGet('children');
        $I->assertEqualsCanonicalizing(['Luca', 'Sofia'], $I->grabDataFromResponseByJsonPath('$.data[*].name'));
        $I->sendGet('children?players=1');
        $I->assertEqualsCanonicalizing(['Luca', 'Sofia', 'Anna', 'Marco'], $I->grabDataFromResponseByJsonPath('$.data[*].name'));

        $I->sendPost('tasks', ['title' => 'Portare fuori il cane', 'icon' => '🐕', 'points' => 20, 'requires_approval' => true, 'recurrence_type' => 'daily', 'assignee_ids' => [$marcoPlayer->id]]);
        $I->seeResponseCodeIs(201);

        $I->amAuthenticatedAs($this->marco->user);
        $I->sendGet('me');
        $I->seeResponseContainsJson(['data' => ['player' => ['id' => $marcoPlayer->id, 'name' => 'Marco', 'is_adult' => true]]]);
        $I->sendGet('me/today');
        $id = $I->grabDataFromResponseByJsonPath('$.data.groups.any[0].id')[0];
        $I->sendPost("occurrences/$id/submit");
        $I->seeResponseContainsJson(['data' => ['occurrence' => ['status' => 'submitted']]]);

        $I->amAuthenticatedAs($this->anna->user);
        $I->sendPost("occurrences/$id/approve");
        $I->seeResponseContainsJson(['data' => ['points_awarded' => 20]]);
        $I->seeRecord(ChildProfile::class, ['id' => $marcoPlayer->id, 'points_balance' => 20]);

        $reward = new Reward(['title' => 'Partita a calcetto', 'icon' => '⚽', 'cost' => 15]);
        $reward->family_id = $this->anna->family_id;
        $reward->save(false);
        $I->amAuthenticatedAs($this->marco->user);
        $I->sendGet('rewards?view=shop');
        $I->seeResponseContainsJson(['data' => [['title' => 'Partita a calcetto', 'affordable' => true]]]);
        $I->sendPost("rewards/{$reward->id}/redeem");
        $I->seeResponseCodeIs(201);

        $I->amAuthenticatedAs($this->anna->user);
        $I->sendDelete("children/{$marcoPlayer->id}");
        $I->seeErrorEnvelope(404, 'not_found');
    }

    public function openTaskGoesToWhoeverDoesItFirst(FunctionalTester $I): void
    {
        $I->amAuthenticatedAs($this->anna->user);
        $I->sendPost('tasks', ['title' => 'Svuotare la lavastoviglie', 'icon' => '🫧', 'points' => 15, 'requires_approval' => true, 'is_open' => true, 'recurrence_type' => 'daily', 'assignee_ids' => []]);
        $I->seeResponseCodeIs(201);
        $I->seeResponseContainsJson(['data' => ['is_open' => true, 'assignee_ids' => []]]);
        $taskId = $I->grabDataFromResponseByJsonPath('$.data.id')[0];
        $I->assertSame(4, (int) TaskOccurrence::find()->where(['task_id' => $taskId, 'due_date' => '2026-11-16'])->count(), 'every player');

        $occurrenceOf = fn (ChildProfile $p): int => (int) TaskOccurrence::find()->select('id')->where(['task_id' => $taskId, 'child_id' => $p->id])->scalar();

        $I->amChild($this->luca);
        $I->sendGet('me/today');
        $I->seeResponseContainsJson(['data' => ['groups' => ['any' => [['title' => 'Svuotare la lavastoviglie', 'is_open' => true]]]]]);
        $I->sendPost('occurrences/' . $occurrenceOf($this->luca) . '/submit');
        $I->seeResponseContainsJson(['data' => ['occurrence' => ['status' => 'submitted']]]);

        $I->amChild($this->sofia);
        $I->sendGet('me/today');
        $I->assertSame([], $I->grabDataFromResponseByJsonPath('$.data.groups.any[*].id'), 'taken tasks disappear');
        $I->sendPost('occurrences/' . $occurrenceOf($this->sofia) . '/submit');
        $I->seeErrorEnvelope(422, 'invalid_transition');

        $I->amAuthenticatedAs($this->anna->user);
        $I->sendPost('occurrences/' . $occurrenceOf($this->luca) . '/reject');
        $I->amChild($this->sofia);
        $I->sendPost('occurrences/' . $occurrenceOf($this->sofia) . '/submit');
        $I->seeResponseContainsJson(['data' => ['occurrence' => ['status' => 'submitted']]]);
        $I->amAuthenticatedAs($this->anna->user);
        $I->sendPost('occurrences/' . $occurrenceOf($this->sofia) . '/approve');
        $I->seeRecord(PointTransaction::class, ['child_id' => $this->sofia->id, 'points' => 15]);
        $I->dontSeeRecord(PointTransaction::class, ['child_id' => $this->luca->id]);
        $I->seeRecord(TaskOccurrence::class, ['id' => $occurrenceOf($this->luca), 'status' => 'taken']);
    }

    public function closedTasksStillNeedAssignees(FunctionalTester $I): void
    {
        $I->amAuthenticatedAs($this->anna->user);
        $I->sendPost('tasks', ['title' => 'X', 'icon' => '⭐', 'points' => 1, 'recurrence_type' => 'daily', 'assignee_ids' => []]);
        $I->seeErrorEnvelope(422, 'validation_failed');
    }
}
