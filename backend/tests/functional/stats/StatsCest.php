<?php

declare(strict_types=1);

namespace tests\functional\stats;

use app\models\ChildProfile;
use app\models\FamilyMember;
use app\models\PointTransaction;
use app\models\TaskOccurrence;
use app\services\PointsService;
use app\services\WeeklySummaryService;
use FunctionalTester;
use tests\_support\FamilyFactory;
use Yii;

final class StatsCest
{
    private FamilyMember $parent;
    private ChildProfile $luca;

    public function _before(FunctionalTester $I): void
    {
        Yii::$app->get('clock')->setNow(new \DateTimeImmutable('2026-11-16 09:00:00', new \DateTimeZone('Europe/Rome')));
        $this->parent = FamilyFactory::member(FamilyFactory::family('Rossi'));
        $this->luca = FamilyFactory::child($this->parent->family, 'Luca');
        $task = FamilyFactory::task($this->parent->family, [$this->luca]);
        foreach ([['2026-11-10', 'approved'], ['2026-11-11', 'approved'], ['2026-11-12', 'missed'], ['2026-11-13', 'approved'], ['2026-11-02', 'approved']] as [$day, $status]) {
            $o = new TaskOccurrence(['task_id' => $task->id, 'child_id' => $this->luca->id, 'due_date' => $day, 'status' => $status]);
            $o->family_id = $this->parent->family_id;
            $o->save(false);
        }
        $points = new PointsService();
        $points->award($this->luca, 30, 30, 'task');
        $points->award($this->luca, -10, 0, 'reward');
        // TimestampBehavior uses the real time: move the transactions inside the frozen week
        PointTransaction::updateAll(
            ['created_at' => Yii::$app->get('clock')->now()->getTimestamp()],
            ['child_id' => $this->luca->id],
        );
    }

    public function _after(): void
    {
        Yii::$app->get('clock')->setNow(null);
    }

    public function statsForTheLastWeek(FunctionalTester $I): void
    {
        $I->amAuthenticatedAs($this->parent->user);
        $I->sendGet('stats');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['data' => ['from' => '2026-11-10', 'to' => '2026-11-16', 'children' => [[
            'child' => ['name' => 'Luca'],
            'tasks' => ['approved' => 3, 'missed' => 1],
            'completion_rate' => 0.75,
            'points_earned' => 30,
            'points_spent' => 10,
        ]]]]);
        $I->assertCount(7, $I->grabDataFromResponseByJsonPath('$.data.children[0].per_day[*]'));
        $I->seeResponseContainsJson(['data' => ['children' => [['per_day' => [['date' => '2026-11-12', 'approved' => 0, 'missed' => 1]]]]]]);

        $I->sendGet('stats?from=2026-11-01&to=2026-11-03');
        $I->seeResponseContainsJson(['data' => ['children' => [['tasks' => ['approved' => 1], 'points_earned' => 0]]]]);
        $I->sendGet('stats?from=2026-01-01&to=2026-12-31');
        $I->seeErrorEnvelope(422, 'validation_failed');
        $I->sendGet('stats?from=nope');
        $I->seeErrorEnvelope(422, 'validation_failed');

        $I->amChild($this->luca);
        $I->sendGet('stats');
        $I->seeErrorEnvelope(403, 'forbidden');
        $I->amFamilyMember(FamilyFactory::family('Bianchi'));
        $I->sendGet("stats?child_id={$this->luca->id}");
        $I->seeResponseContainsJson(['data' => ['children' => []]]);
    }

    public function weeklyEmailOnlyWhenEnabledAndOnce(FunctionalTester $I): void
    {
        $this->parent->user->updateAttributes(['email' => 'anna@example.com']);
        $service = new WeeklySummaryService();

        $I->assertSame(0, $service->sendAll('2026-11-15'));
        $I->dontSeeEmailIsSent();

        $family = $this->parent->family;
        $family->settings = ['weekly_email' => true];
        $family->save(false);
        $I->assertSame(1, $service->sendAll('2026-11-15'));
        $I->assertSame(0, $service->sendAll('2026-11-15'));
        $I->seeEmailIsSent(1);
        $mail = $I->grabLastSentEmail();
        $I->assertSame(['anna@example.com'], array_keys((array) $mail->getTo()));
        $I->assertStringContainsString('Rossi', (string) $mail->getSubject());
        $I->assertStringContainsString('Compiti completati: <strong>3</strong>', $mail->toString());
    }
}
