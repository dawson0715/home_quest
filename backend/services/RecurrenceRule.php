<?php

declare(strict_types=1);

namespace app\services;

use app\models\Task;

/**
 * Pure recurrence maths on calendar dates (Y-m-d). Calendar arithmetic is done in UTC
 * so DST changes in Europe/Rome never shift a day.
 */
final class RecurrenceRule
{
    /** Mon=1, Tue=2, Wed=4, Thu=8, Fri=16, Sat=32, Sun=64 */
    public static function weekdayBit(string $date): int
    {
        return 1 << ((int) self::utc($date)->format('N') - 1);
    }

    public static function daysBetween(string $from, string $to): int
    {
        return intdiv(self::utc($to)->getTimestamp() - self::utc($from)->getTimestamp(), 86400);
    }

    /**
     * Is the task active and scheduled on $date? For one-off tasks: visible from
     * start_date until its deadline (if any).
     */
    public static function isDue(Task $task, string $date): bool
    {
        if (!$task->is_active || $date < $task->start_date) {
            return false;
        }
        if ($task->end_date !== null && $date > $task->end_date) {
            return false;
        }

        return match ($task->recurrence_type) {
            Task::RECURRENCE_NONE => $task->due_date === null || $date <= $task->due_date,
            Task::RECURRENCE_DAILY => true,
            Task::RECURRENCE_WEEKLY => ((int) $task->recurrence_days & self::weekdayBit($date)) !== 0,
            Task::RECURRENCE_INTERVAL => self::daysBetween($task->start_date, $date) % max(1, (int) $task->interval_days) === 0,
            default => false,
        };
    }

    /**
     * Date stored on the occurrence: the day itself, or for one-off tasks a single
     * date (deadline or start) so that only one occurrence per child ever exists.
     */
    public static function occurrenceDate(Task $task, string $date): string
    {
        if ($task->recurrence_type === Task::RECURRENCE_NONE) {
            return $task->due_date ?? $task->start_date;
        }
        return $date;
    }

    private static function utc(string $date): \DateTimeImmutable
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('UTC'));
        if ($d === false) {
            throw new \InvalidArgumentException("Invalid date: $date");
        }
        return $d;
    }
}
