<?php

declare(strict_types=1);

namespace tests\unit\services;

use app\commands\CronController;
use app\models\TaskOccurrence;
use Codeception\Test\Unit;
use tests\_support\FamilyFactory;
use Yii;
use yii\console\ExitCode;

final class CronTest extends Unit
{
    public function testDailyMarksMissedAndGeneratesToday(): void
    {
        $family = FamilyFactory::family();
        $child = FamilyFactory::child($family);
        $task = FamilyFactory::task($family, [$child]);
        $old = new TaskOccurrence(['task_id' => $task->id, 'child_id' => $child->id, 'due_date' => '2026-11-15', 'status' => 'pending']);
        $old->family_id = $family->id;
        $old->save(false);
        Yii::$app->get('clock')->setNow(new \DateTimeImmutable('2026-11-16 00:05:00', new \DateTimeZone('Europe/Rome')));

        $controller = new CronController('cron', Yii::$app);
        ob_start();
        $exit = $controller->actionDaily();
        ob_end_clean();
        Yii::$app->get('clock')->setNow(null);

        $this->assertSame(ExitCode::OK, $exit);
        $this->assertSame('missed', TaskOccurrence::findOne($old->id)?->status);
        $this->assertTrue(TaskOccurrence::find()->where(['task_id' => $task->id, 'due_date' => '2026-11-16', 'status' => 'pending'])->exists());
    }
}
