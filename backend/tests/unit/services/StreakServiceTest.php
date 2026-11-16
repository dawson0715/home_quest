<?php

declare(strict_types=1);

namespace tests\unit\services;

use app\models\ChildProfile;
use app\models\Family;
use app\models\PointTransaction;
use app\models\Task;
use app\models\TaskOccurrence;
use app\services\StreakService;
use Codeception\Test\Unit;
use tests\_support\FamilyFactory;

final class StreakServiceTest extends Unit
{
    private Family $family;
    private ChildProfile $child;
    private Task $routine;

    protected function _before(): void
    {
        $this->family = FamilyFactory::family();
        $this->child = FamilyFactory::child($this->family);
        $this->routine = FamilyFactory::task($this->family, [$this->child]);
    }

    private function occurrence(string $day, string $status, ?Task $task = null): void
    {
        $o = new TaskOccurrence(['task_id' => ($task ?? $this->routine)->id, 'child_id' => $this->child->id, 'due_date' => $day, 'status' => $status]);
        $o->family_id = $this->family->id;
        $o->save(false);
    }

    private function fresh(): ChildProfile
    {
        return ChildProfile::findOne($this->child->id) ?? $this->fail('child vanished');
    }

    public function testPerfectDaysBuildTheStreakAndPayBonusAtThree(): void
    {
        $service = StreakService::create();
        foreach (['2026-11-10', '2026-11-11', '2026-11-12'] as $day) {
            $this->occurrence($day, $day === '2026-11-11' ? 'submitted' : 'approved');
            $service->evaluateDay($day);
        }

        $child = $this->fresh();
        $this->assertSame(3, (int) $child->current_streak);
        $this->assertSame(3, (int) $child->best_streak);
        $this->assertSame(10, (int) $child->points_balance);
        $this->assertTrue(PointTransaction::find()->where(['child_id' => $child->id, 'type' => 'streak_bonus', 'points' => 10])->exists());
        $this->assertTrue((new \yii\db\Query())->from('{{%child_badge}} cb')->innerJoin('{{%badge}} b', 'b.id = cb.badge_id')
            ->where(['cb.child_id' => $child->id, 'b.code' => 'streak_3'])->exists());
    }

    public function testImperfectDayResetsButKeepsBest(): void
    {
        $service = StreakService::create();
        $this->occurrence('2026-11-10', 'approved');
        $service->evaluateDay('2026-11-10');
        $this->occurrence('2026-11-11', 'approved');
        $service->evaluateDay('2026-11-11');
        $this->occurrence('2026-11-12', 'missed');
        $service->evaluateDay('2026-11-12');

        $child = $this->fresh();
        $this->assertSame(0, (int) $child->current_streak);
        $this->assertSame(2, (int) $child->best_streak);
    }

    public function testDaysWithoutRoutinesDoNotCountAndOneOffsAreIgnored(): void
    {
        $oneOff = FamilyFactory::task($this->family, [$this->child], ['recurrence_type' => 'none', 'start_date' => '2026-11-11']);
        $service = StreakService::create();
        $this->occurrence('2026-11-10', 'approved');
        $service->evaluateDay('2026-11-10');
        $this->occurrence('2026-11-11', 'pending', $oneOff); // only a one-off: no routine that day
        $service->evaluateDay('2026-11-11');

        $this->assertSame(1, (int) $this->fresh()->current_streak);
    }

    public function testEvaluationIsIdempotentPerDay(): void
    {
        $this->occurrence('2026-11-10', 'approved');
        $service = StreakService::create();

        $this->assertSame(1, $service->evaluateDay('2026-11-10'));
        $this->assertSame(0, $service->evaluateDay('2026-11-10'));
        $this->assertSame(1, (int) $this->fresh()->current_streak);
    }
}
