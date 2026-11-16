<?php

declare(strict_types=1);

namespace tests\unit\services;

use app\components\ApiException;
use app\models\ChildProfile;
use app\models\PointTransaction;
use app\services\PointsService;
use Codeception\Test\Unit;
use tests\_support\FamilyFactory;
use yii\base\InvalidCallException;

final class PointsServiceTest extends Unit
{
    public function testLevelCurve(): void
    {
        $this->assertSame(0, PointsService::xpForLevel(1));
        $this->assertSame(100, PointsService::xpForLevel(2));
        $this->assertSame(300, PointsService::xpForLevel(3));
        $this->assertSame(600, PointsService::xpForLevel(4));
        $this->assertSame(1, PointsService::levelForXp(99));
        $this->assertSame(2, PointsService::levelForXp(100));
        $this->assertSame(3, PointsService::levelForXp(599));
        $this->assertSame(4, PointsService::levelForXp(600));
    }

    public function testAwardWritesLedgerAndTotals(): void
    {
        $child = FamilyFactory::child(FamilyFactory::family());
        $service = new PointsService();

        $first = $service->award($child, 60, 60, 'task', 'task_occurrence', 1);
        $second = $service->award($child, 50, 50, 'task', 'task_occurrence', 2);

        $this->assertFalse($first->levelUp);
        $this->assertTrue($second->levelUp);
        $this->assertSame(2, $second->level);
        $fresh = ChildProfile::findOne($child->id);
        $this->assertSame(110, (int) $fresh?->points_balance);
        $this->assertSame(110, (int) $fresh?->xp_total);
        $this->assertSame(2, (int) $fresh?->level);
        $this->assertSame(2, (int) PointTransaction::find()->where(['child_id' => $child->id])->count());
        $this->assertSame(110, (int) PointTransaction::find()->where(['child_id' => $child->id])->sum('points'));
    }

    public function testBalanceNeverGoesNegative(): void
    {
        $child = FamilyFactory::child(FamilyFactory::family());
        $service = new PointsService();
        $service->award($child, 10, 10, 'task');

        try {
            $service->award($child, -11, 0, 'reward');
            $this->fail('Expected insufficient_points');
        } catch (ApiException $e) {
            $this->assertSame('insufficient_points', $e->errorCode);
        }
        $this->assertSame(10, (int) ChildProfile::findOne($child->id)?->points_balance);
        $this->assertSame(1, (int) PointTransaction::find()->where(['child_id' => $child->id])->count());

        $result = $service->award($child, -10, 0, 'reward');
        $this->assertSame(0, $result->balance);
        $this->assertSame(10, $result->xpTotal, 'spending never reduces XP');
    }

    public function testLedgerIsImmutable(): void
    {
        $child = FamilyFactory::child(FamilyFactory::family());
        $tx = (new PointsService())->award($child, 5, 5, 'bonus')->transaction;

        $tx->points = 500;
        $this->expectException(InvalidCallException::class);
        $tx->save(false);
    }
}
