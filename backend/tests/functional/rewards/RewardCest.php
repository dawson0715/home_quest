<?php

declare(strict_types=1);

namespace tests\functional\rewards;

use app\models\ChildProfile;
use app\models\FamilyMember;
use app\models\PointTransaction;
use app\models\Reward;
use app\models\RewardRedemption;
use app\services\PointsService;
use FunctionalTester;
use tests\_support\FamilyFactory;
use Yii;

final class RewardCest
{
    private FamilyMember $parent;
    private ChildProfile $luca;

    public function _before(): void
    {
        Yii::$app->get('clock')->setNow(new \DateTimeImmutable('2026-11-18 10:00:00', new \DateTimeZone('Europe/Rome'))); // Wednesday
        $this->parent = FamilyFactory::member(FamilyFactory::family('Rossi'));
        $this->luca = FamilyFactory::child($this->parent->family, 'Luca');
    }

    public function _after(): void
    {
        Yii::$app->get('clock')->setNow(null);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function reward(array $attributes = []): Reward
    {
        $reward = new Reward(array_merge(['title' => '30 min di tablet', 'icon' => '📱', 'cost' => 50], $attributes));
        $reward->family_id = $this->parent->family_id;
        $reward->save(false);
        $reward->refresh();
        return $reward;
    }

    private function give(int $points): void
    {
        (new PointsService())->award($this->luca, $points, $points, 'bonus');
    }

    public function parentManagesRewards(FunctionalTester $I): void
    {
        $I->amAuthenticatedAs($this->parent->user);
        $I->sendPost('rewards', ['title' => 'Scegli la cena', 'icon' => '🍕', 'cost' => 100, 'limit_per_period' => 1, 'limit_period' => 'week']);
        $I->seeResponseCodeIs(201);
        $I->seeResponseContainsJson(['data' => ['title' => 'Scegli la cena', 'cost' => 100, 'limit_period' => 'week', 'is_active' => true]]);
        $id = $I->grabDataFromResponseByJsonPath('$.data.id')[0];

        $I->sendPost('rewards', ['title' => 'X', 'icon' => '⭐', 'cost' => 0, 'limit_per_period' => 2]);
        $I->seeErrorEnvelope(422, 'validation_failed');
        $I->assertEqualsCanonicalizing(['cost', 'limit_period'], $I->grabDataFromResponseByJsonPath('$.meta.errors[*].field'));

        $I->sendPatch("rewards/$id", ['cost' => 80]);
        $I->seeResponseContainsJson(['data' => ['cost' => 80]]);
        $I->sendDelete("rewards/$id");
        $I->seeResponseCodeIs(204);
        $I->dontSeeRecord(Reward::class, ['id' => $id]);
    }

    public function childRequestsAndParentApproves(FunctionalTester $I): void
    {
        $reward = $this->reward(['cost' => 50]);
        $I->amChild($this->luca);

        $I->sendPost("rewards/{$reward->id}/redeem");
        $I->seeErrorEnvelope(422, 'insufficient_points');

        $this->give(60);
        $I->sendGet('rewards');
        $I->seeResponseContainsJson(['data' => [['id' => $reward->id, 'affordable' => true, 'available' => true, 'is_goal' => false]]]);

        $I->sendPost("rewards/{$reward->id}/redeem");
        $I->seeResponseCodeIs(201);
        $I->seeResponseContainsJson(['data' => ['status' => 'requested', 'cost' => 50, 'title' => '30 min di tablet']]);
        $redemptionId = $I->grabDataFromResponseByJsonPath('$.data.id')[0];

        // 60 points, 50 reserved: a second request does not fit
        $I->sendPost("rewards/{$reward->id}/redeem");
        $I->seeErrorEnvelope(422, 'insufficient_points');
        $I->sendGet('redemptions');
        $I->assertSame([$redemptionId], $I->grabDataFromResponseByJsonPath('$.data[*].id'));

        $I->amAuthenticatedAs($this->parent->user);
        $I->sendGet('redemptions?status=requested');
        $I->seeResponseContainsJson(['data' => [['id' => $redemptionId, 'child' => ['name' => 'Luca']]]]);
        $I->sendPost("redemptions/$redemptionId/approve");
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['data' => ['redemption' => ['status' => 'approved'], 'new_badges' => [['code' => 'first_reward']]]]);
        $I->seeRecord(ChildProfile::class, ['id' => $this->luca->id, 'points_balance' => 10, 'xp_total' => 60]);
        $I->seeRecord(PointTransaction::class, ['child_id' => $this->luca->id, 'type' => 'reward', 'points' => -50, 'source_id' => $redemptionId]);

        $I->sendPost("redemptions/$redemptionId/approve");
        $I->seeErrorEnvelope(422, 'invalid_transition');
        $I->sendPost("redemptions/$redemptionId/fulfill");
        $I->seeResponseContainsJson(['data' => ['status' => 'fulfilled']]);
    }

    public function rejectingDoesNotDebit(FunctionalTester $I): void
    {
        $reward = $this->reward();
        $this->give(50);
        $I->amChild($this->luca);
        $I->sendPost("rewards/{$reward->id}/redeem");
        $id = $I->grabDataFromResponseByJsonPath('$.data.id')[0];

        $I->amAuthenticatedAs($this->parent->user);
        $I->sendPost("redemptions/$id/reject", ['note' => 'Non oggi']);
        $I->seeResponseContainsJson(['data' => ['status' => 'rejected', 'note' => 'Non oggi']]);
        $I->seeRecord(ChildProfile::class, ['id' => $this->luca->id, 'points_balance' => 50]);
    }

    public function periodLimitAndStock(FunctionalTester $I): void
    {
        $weekly = $this->reward(['limit_per_period' => 1, 'limit_period' => 'week']);
        $stocked = $this->reward(['title' => 'Gelato', 'cost' => 10, 'stock' => 1]);
        $this->give(200);
        $old = new RewardRedemption(['reward_id' => $weekly->id, 'child_id' => $this->luca->id, 'cost_snapshot' => 50, 'status' => 'fulfilled', 'requested_at' => '2026-11-15 18:00:00']);
        $old->family_id = $this->parent->family_id;
        $old->save(false); // last Sunday: previous week

        $I->amChild($this->luca);
        $I->sendPost("rewards/{$weekly->id}/redeem");
        $I->seeResponseCodeIs(201);
        $I->sendPost("rewards/{$weekly->id}/redeem");
        $I->seeErrorEnvelope(422, 'reward_limit_reached');
        $I->sendGet('rewards');
        $I->seeResponseContainsJson(['data' => [['id' => $weekly->id, 'available' => false]]]);

        $I->sendPost("rewards/{$stocked->id}/redeem");
        $id = $I->grabDataFromResponseByJsonPath('$.data.id')[0];
        $I->amAuthenticatedAs($this->parent->user);
        $I->sendPost("redemptions/$id/approve");
        $I->seeRecord(Reward::class, ['id' => $stocked->id, 'stock' => 0]);
        $I->amChild($this->luca);
        $I->sendPost("rewards/{$stocked->id}/redeem");
        $I->seeErrorEnvelope(422, 'reward_limit_reached');
    }

    public function savingsGoalShowsOnTodayAndClearsOnApproval(FunctionalTester $I): void
    {
        $reward = $this->reward(['cost' => 200]);
        $this->give(50);
        $I->amChild($this->luca);

        $I->sendPut('me/savings-goal', ['reward_id' => $reward->id]);
        $I->seeResponseContainsJson(['data' => ['savings_goal' => ['reward_id' => $reward->id, 'cost' => 200, 'progress' => 0.25]]]);
        $I->sendGet('me/today');
        $I->seeResponseContainsJson(['data' => ['savings_goal' => ['title' => '30 min di tablet', 'progress' => 0.25]]]);
        $I->sendGet('rewards');
        $I->seeResponseContainsJson(['data' => [['id' => $reward->id, 'is_goal' => true, 'affordable' => false]]]);

        $this->give(150);
        $I->sendPost("rewards/{$reward->id}/redeem");
        $id = $I->grabDataFromResponseByJsonPath('$.data.id')[0];
        $I->amAuthenticatedAs($this->parent->user);
        $I->sendPost("redemptions/$id/approve");

        $I->amChild($this->luca);
        $I->sendGet('me/today');
        $I->seeResponseContainsJson(['data' => ['savings_goal' => null]]);

        $I->sendPut('me/savings-goal', ['reward_id' => 999999]);
        $I->seeErrorEnvelope(422, 'validation_failed');
    }

    public function bonusPenaltyAndLedger(FunctionalTester $I): void
    {
        $I->amAuthenticatedAs($this->parent->user);
        $I->sendPost("children/{$this->luca->id}/adjust", ['type' => 'bonus', 'points' => 20, 'note' => 'Ha aiutato la nonna']);
        $I->seeResponseCodeIs(201);
        $I->seeResponseContainsJson(['data' => ['child' => ['points_balance' => 20, 'xp_total' => 20], 'transaction' => ['type' => 'bonus', 'points' => 20, 'note' => 'Ha aiutato la nonna']]]);

        $I->sendPost("children/{$this->luca->id}/adjust", ['type' => 'penalty', 'points' => 5, 'note' => 'Litigio']);
        $I->seeErrorEnvelope(422, 'penalties_disabled');
        $I->sendPost("children/{$this->luca->id}/adjust", ['type' => 'bonus', 'points' => 5, 'note' => '']);
        $I->seeErrorEnvelope(422, 'validation_failed');

        $I->sendPatch('family', ['settings' => ['allow_penalties' => true]]);
        $I->sendPost("children/{$this->luca->id}/adjust", ['type' => 'penalty', 'points' => 5, 'note' => 'Litigio']);
        $I->seeResponseContainsJson(['data' => ['child' => ['points_balance' => 15, 'xp_total' => 20], 'transaction' => ['type' => 'penalty', 'points' => -5, 'xp' => 0]]]);
        $I->sendPost("children/{$this->luca->id}/adjust", ['type' => 'penalty', 'points' => 50, 'note' => 'Troppo']);
        $I->seeErrorEnvelope(422, 'insufficient_points');

        $I->sendGet("children/{$this->luca->id}/transactions");
        $I->assertSame([-5, 20], $I->grabDataFromResponseByJsonPath('$.data[*].points'));
        $I->seeResponseContainsJson(['meta' => ['pagination' => ['totalCount' => 2]]]);

        $I->amChild($this->luca);
        $I->sendGet('me/transactions');
        $I->assertSame([-5, 20], $I->grabDataFromResponseByJsonPath('$.data[*].points'));
        $I->sendPost("children/{$this->luca->id}/adjust", ['type' => 'bonus', 'points' => 500, 'note' => 'hack']);
        $I->seeErrorEnvelope(403, 'forbidden');
        $I->sendPost('rewards', ['title' => 'X', 'icon' => '⭐', 'cost' => 1]);
        $I->seeErrorEnvelope(403, 'forbidden');
    }

    public function badgesListEarnedOnes(FunctionalTester $I): void
    {
        FamilyFactory::task($this->parent->family, [$this->luca], ['points' => 5]);
        $I->amChild($this->luca);
        $I->sendGet('me/today');
        $id = $I->grabDataFromResponseByJsonPath('$.data.groups.morning[0].id')[0];
        $I->sendPost("occurrences/$id/submit");
        $I->seeResponseContainsJson(['data' => ['new_badges' => [['code' => 'first_task', 'icon' => '🌱']]]]);

        $I->sendGet('badges');
        $I->seeResponseContainsJson(['data' => [['code' => 'first_task'], ['code' => 'tasks_10', 'earned_at' => null]]]);
        $I->assertNotNull($I->grabDataFromResponseByJsonPath('$.data[0].earned_at')[0]);

        $I->amAuthenticatedAs($this->parent->user);
        $I->sendGet("badges?child_id={$this->luca->id}");
        $I->assertNotNull($I->grabDataFromResponseByJsonPath('$.data[0].earned_at')[0]);
        $I->sendGet('badges');
        $I->seeErrorEnvelope(422, 'validation_failed');
    }

    public function familiesAreIsolated(FunctionalTester $I): void
    {
        $bianchi = FamilyFactory::family('Bianchi');
        $foreign = new Reward(['title' => 'Altro', 'icon' => '🎁', 'cost' => 1]);
        $foreign->family_id = $bianchi->id;
        $foreign->save(false);
        $this->give(10);

        $I->amChild($this->luca);
        $I->sendPost("rewards/{$foreign->id}/redeem");
        $I->seeErrorEnvelope(404, 'not_found');
        $I->sendGet('rewards');
        $I->assertSame([], $I->grabDataFromResponseByJsonPath('$.data[*].id'));

        $otherChild = FamilyFactory::child($bianchi);
        $I->amAuthenticatedAs($this->parent->user);
        $I->sendPost("children/{$otherChild->id}/adjust", ['type' => 'bonus', 'points' => 5, 'note' => 'x']);
        $I->seeErrorEnvelope(404, 'not_found');
        $I->sendGet("badges?child_id={$otherChild->id}");
        $I->seeErrorEnvelope(422, 'validation_failed');
    }
}
