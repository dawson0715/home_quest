<?php

declare(strict_types=1);

namespace tests\unit\services;

use app\models\TaskOccurrence;
use app\services\OccurrenceGenerator;
use Codeception\Test\Unit;
use tests\_support\FamilyFactory;

final class OccurrenceGeneratorTest extends Unit
{
    public function testGeneratesOncePerChildAndIsIdempotent(): void
    {
        $family = FamilyFactory::family();
        $luca = FamilyFactory::child($family, 'Luca');
        $anna = FamilyFactory::child($family, 'Anna');
        $archived = FamilyFactory::child($family, 'Old', ['archived_at' => '2026-01-01 00:00:00']);
        $task = FamilyFactory::task($family, [$luca, $anna, $archived]);
        FamilyFactory::task($family, [$luca], ['recurrence_type' => 'weekly', 'recurrence_days' => 64]); // Sundays only
        $generator = new OccurrenceGenerator();

        $this->assertSame(2, $generator->generateForDate('2026-11-16', $family->id));
        $this->assertSame(0, $generator->generateForDate('2026-11-16', $family->id));

        $rows = TaskOccurrence::find()->where(['task_id' => $task->id])->select('child_id')->column();
        $this->assertEqualsCanonicalizing([$luca->id, $anna->id], array_map('intval', $rows));
    }

    public function testOtherFamiliesAreNotTouchedWhenScoped(): void
    {
        $rossi = FamilyFactory::family('Rossi');
        $bianchi = FamilyFactory::family('Bianchi');
        FamilyFactory::task($rossi, [FamilyFactory::child($rossi)]);
        FamilyFactory::task($bianchi, [FamilyFactory::child($bianchi)]);

        (new OccurrenceGenerator())->generateForDate('2026-11-16', $rossi->id);

        $this->assertSame(1, (int) TaskOccurrence::find()->where(['family_id' => $rossi->id])->count());
        $this->assertSame(0, (int) TaskOccurrence::find()->where(['family_id' => $bianchi->id])->count());
    }

    public function testOneOffTaskHasASingleOccurrence(): void
    {
        $family = FamilyFactory::family();
        $child = FamilyFactory::child($family);
        FamilyFactory::task($family, [$child], ['recurrence_type' => 'none', 'start_date' => '2026-11-10']);
        $generator = new OccurrenceGenerator();

        $generator->generateForDate('2026-11-10', $family->id);
        $generator->generateForDate('2026-11-11', $family->id);
        $generator->generateForDate('2026-11-12', $family->id);

        $dates = TaskOccurrence::find()->where(['child_id' => $child->id])->select('due_date')->column();
        $this->assertSame(['2026-11-10'], $dates);
    }

    public function testMarkMissed(): void
    {
        $family = FamilyFactory::family();
        $child = FamilyFactory::child($family);
        $daily = FamilyFactory::task($family, [$child]);
        $openOneOff = FamilyFactory::task($family, [$child], ['recurrence_type' => 'none', 'start_date' => '2026-11-10']);
        $deadline = FamilyFactory::task($family, [$child], ['recurrence_type' => 'none', 'start_date' => '2026-11-10', 'due_date' => '2026-11-14']);
        $generator = new OccurrenceGenerator();
        $generator->generateForDate('2026-11-14', $family->id);
        $generator->generateForDate('2026-11-15', $family->id);
        TaskOccurrence::updateAll(['status' => 'approved'], ['task_id' => $daily->id, 'due_date' => '2026-11-15']);

        $marked = $generator->markMissed('2026-11-16');

        $this->assertSame(2, $marked);
        $status = fn (int $taskId, string $date): ?string => TaskOccurrence::find()
            ->where(['task_id' => $taskId, 'due_date' => $date])->select('status')->scalar() ?: null;
        $this->assertSame('missed', $status($daily->id, '2026-11-14'));
        $this->assertSame('approved', $status($daily->id, '2026-11-15'));
        $this->assertSame('pending', $status($openOneOff->id, '2026-11-10'));
        $this->assertSame('missed', $status($deadline->id, '2026-11-14'));
    }
}
