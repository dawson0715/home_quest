<?php

declare(strict_types=1);

namespace tests\unit\services;

use app\models\Task;
use app\services\RecurrenceRule;
use Codeception\Test\Unit;

final class RecurrenceRuleTest extends Unit
{
    /**
     * @param array<string, mixed> $attributes
     */
    private function task(array $attributes): Task
    {
        return new Task(array_merge(['is_active' => true, 'start_date' => '2026-11-02', 'end_date' => null, 'due_date' => null], $attributes));
    }

    public function testWeekdayBits(): void
    {
        $this->assertSame(1, RecurrenceRule::weekdayBit('2026-11-16'));  // Monday
        $this->assertSame(16, RecurrenceRule::weekdayBit('2026-11-20')); // Friday
        $this->assertSame(64, RecurrenceRule::weekdayBit('2026-11-22')); // Sunday
    }

    public function testWeeklyBitmask(): void
    {
        $weekdays = $this->task(['recurrence_type' => 'weekly', 'recurrence_days' => 31]); // Mon–Fri
        $this->assertTrue(RecurrenceRule::isDue($weekdays, '2026-11-20'));
        $this->assertFalse(RecurrenceRule::isDue($weekdays, '2026-11-21'));

        $weekend = $this->task(['recurrence_type' => 'weekly', 'recurrence_days' => 32 | 64]);
        $this->assertTrue(RecurrenceRule::isDue($weekend, '2026-11-22'));
        $this->assertFalse(RecurrenceRule::isDue($weekend, '2026-11-16'));
    }

    public function testDailyRespectsStartEndAndActive(): void
    {
        $task = $this->task(['recurrence_type' => 'daily', 'end_date' => '2026-11-10']);
        $this->assertFalse(RecurrenceRule::isDue($task, '2026-11-01'));
        $this->assertTrue(RecurrenceRule::isDue($task, '2026-11-02'));
        $this->assertTrue(RecurrenceRule::isDue($task, '2026-11-10'));
        $this->assertFalse(RecurrenceRule::isDue($task, '2026-11-11'));

        $task->is_active = false;
        $this->assertFalse(RecurrenceRule::isDue($task, '2026-11-05'));
    }

    public function testIntervalAcrossDstChanges(): void
    {
        // Europe/Rome: DST starts 2027-03-28 and ends 2026-10-25
        $spring = $this->task(['recurrence_type' => 'interval', 'interval_days' => 3, 'start_date' => '2027-03-26']);
        $this->assertTrue(RecurrenceRule::isDue($spring, '2027-03-26'));
        $this->assertFalse(RecurrenceRule::isDue($spring, '2027-03-28'));
        $this->assertTrue(RecurrenceRule::isDue($spring, '2027-03-29'));
        $this->assertTrue(RecurrenceRule::isDue($spring, '2027-04-01'));

        $autumn = $this->task(['recurrence_type' => 'interval', 'interval_days' => 2, 'start_date' => '2026-10-24']);
        $this->assertTrue(RecurrenceRule::isDue($autumn, '2026-10-26'));
        $this->assertFalse(RecurrenceRule::isDue($autumn, '2026-10-25'));
        $this->assertSame(1, RecurrenceRule::daysBetween('2026-10-25', '2026-10-26'));
        $this->assertSame(1, RecurrenceRule::daysBetween('2027-03-28', '2027-03-29'));
    }

    public function testOneOffTaskIsVisibleUntilDeadlineWithSingleOccurrenceDate(): void
    {
        $withDeadline = $this->task(['recurrence_type' => 'none', 'due_date' => '2026-11-05']);
        $this->assertTrue(RecurrenceRule::isDue($withDeadline, '2026-11-03'));
        $this->assertFalse(RecurrenceRule::isDue($withDeadline, '2026-11-06'));
        $this->assertSame('2026-11-05', RecurrenceRule::occurrenceDate($withDeadline, '2026-11-03'));

        $open = $this->task(['recurrence_type' => 'none']);
        $this->assertTrue(RecurrenceRule::isDue($open, '2027-01-01'));
        $this->assertSame('2026-11-02', RecurrenceRule::occurrenceDate($open, '2027-01-01'));
    }
}
