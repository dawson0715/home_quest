<?php

declare(strict_types=1);

namespace app\commands;

use app\services\NotificationService;
use app\services\OccurrenceGenerator;
use app\services\StreakService;
use app\services\WeeklySummaryService;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Scheduled jobs (docs/02-architettura.md §9). Idempotent: safe to run more often.
 */
class CronController extends Controller
{
    /**
     * Hourly: marks past unfinished occurrences as missed, creates today's and
     * evaluates yesterday's streaks (once per child and day).
     */
    /**
     * Every minute: routine reminders to children (web push) at the configured times.
     */
    public function actionMinute(): int
    {
        $now = Yii::$app->get('clock')->now();
        $sent = NotificationService::create()->sendRoutineReminders($now->format('Y-m-d'), $now->format('H:i'));
        if ($sent > 0) {
            $this->stdout('[' . $now->format('Y-m-d H:i') . "] reminders: $sent\n");
        }
        return ExitCode::OK;
    }

    /**
     * Weekly (Monday morning): summary email of the previous 7 days.
     */
    public function actionWeekly(): int
    {
        $yesterday = Yii::$app->get('clock')->now()->modify('-1 day')->format('Y-m-d');
        $sent = (new WeeklySummaryService())->sendAll($yesterday);
        $this->stdout("weekly emails: $sent\n");
        return ExitCode::OK;
    }

    public function actionDaily(): int
    {
        $today = Yii::$app->get('clock')->today();
        $generator = new OccurrenceGenerator();
        $missed = $generator->markMissed($today);
        $created = $generator->generateForDate($today);
        $yesterday = (new \DateTimeImmutable($today))->modify('-1 day')->format('Y-m-d');
        $streaks = StreakService::create()->evaluateDay($yesterday);
        $this->stdout("[$today] missed: $missed, created: $created, streaks: $streaks\n");
        return ExitCode::OK;
    }
}
