<?php

declare(strict_types=1);

namespace tests\functional\notifications;

use app\components\PushSender;
use app\components\WebPushSender;
use app\models\ChildProfile;
use app\models\FamilyMember;
use app\models\Reward;
use app\services\NotificationService;
use app\services\PointsService;
use FunctionalTester;
use tests\_support\FamilyFactory;
use tests\_support\RecordingPushSender;
use Yii;
use yii\db\Query;

final class PushCest
{
    private FamilyMember $parent;
    private ChildProfile $luca;
    private RecordingPushSender $sender;

    public function _before(FunctionalTester $I): void
    {
        $this->sender = new RecordingPushSender();
        Yii::$container->setSingleton(PushSender::class, fn () => $this->sender);
        Yii::$app->get('clock')->setNow(new \DateTimeImmutable('2026-11-16 07:30:00', new \DateTimeZone('Europe/Rome')));
        $this->parent = FamilyFactory::member(FamilyFactory::family('Rossi'));
        $this->luca = FamilyFactory::child($this->parent->family, 'Luca');
    }

    public function _after(): void
    {
        Yii::$container->setSingleton(PushSender::class, fn () => WebPushSender::fromParams());
        Yii::$app->get('clock')->setNow(null);
    }

    /** @param array<string, string> $keys */
    private function subscribe(FunctionalTester $I, string $endpoint, array $keys = ['p256dh' => 'BPkey', 'auth' => 'authkey']): void
    {
        $I->sendPost('push/subscribe', ['endpoint' => $endpoint, 'keys' => $keys]);
        $I->seeResponseCodeIs(201);
    }

    public function subscribeValidateReplaceAndUnsubscribe(FunctionalTester $I): void
    {
        $I->sendGet('push/vapid-key');
        $I->seeResponseCodeIs(200);

        $I->amAuthenticatedAs($this->parent->user);
        $I->sendPost('push/subscribe', ['endpoint' => 'http://insecure.example', 'keys' => ['p256dh' => 'x', 'auth' => 'y']]);
        $I->seeErrorEnvelope(422, 'validation_failed');
        $this->subscribe($I, 'https://push.example/parent');
        $this->subscribe($I, 'https://push.example/parent');
        $I->assertSame(1, (int) (new Query())->from('{{%push_subscription}}')->where(['user_id' => $this->parent->user_id])->count());

        // the tablet re-subscribes as a child: ownership moves
        $I->amChild($this->luca);
        $this->subscribe($I, 'https://push.example/parent');
        $I->assertEquals(['child_id' => $this->luca->id, 'user_id' => null], (new Query())->select(['child_id', 'user_id'])->from('{{%push_subscription}}')->one());

        $I->sendDelete('push/subscribe', ['endpoint' => 'https://push.example/parent']);
        $I->seeResponseCodeIs(204);
        $I->assertSame(0, (int) (new Query())->from('{{%push_subscription}}')->count());
    }

    public function parentsAreNotifiedOfThingsToReview(FunctionalTester $I): void
    {
        $I->amAuthenticatedAs($this->parent->user);
        $this->subscribe($I, 'https://push.example/parent');
        $I->amChild($this->luca);
        $this->subscribe($I, 'https://push.example/kid');

        FamilyFactory::task($this->parent->family, [$this->luca], ['title' => 'Camera', 'requires_approval' => true]);
        $I->sendGet('me/today');
        $id = $I->grabDataFromResponseByJsonPath('$.data.groups.morning[0].id')[0];
        $I->sendPost("occurrences/$id/submit");

        $reward = new Reward(['title' => 'Gelato', 'icon' => '🍦', 'cost' => 5]);
        $reward->family_id = $this->parent->family_id;
        $reward->save(false);
        (new PointsService())->award($this->luca, 10, 10, 'bonus');
        $I->sendPost("rewards/{$reward->id}/redeem");
        $I->sendPost('meal-plans/2026-11-16/proposals', ['day' => 0, 'meal_type' => 'dinner', 'free_text' => 'Pizza']);

        $I->assertSame(['task_submitted', 'reward_requested', 'dish_proposed'], $this->sender->types());
        $I->assertSame(['https://push.example/parent'], array_values(array_unique(array_column($this->sender->sent, 'endpoint'))));
        $I->assertSame(['name' => 'Luca', 'title' => 'Camera'], $this->sender->sent[0]['payload']['params']);
        $I->assertSame('/approvals', $this->sender->sent[0]['payload']['url']);
    }

    public function routineRemindersAreSentOncePerSlotAndExpiredSubscriptionsRemoved(FunctionalTester $I): void
    {
        $I->amChild($this->luca);
        $this->subscribe($I, 'https://push.example/kid');
        $anna = FamilyFactory::child($this->parent->family, 'Anna');
        $I->amChild($anna);
        $this->subscribe($I, 'https://push.example/anna');
        FamilyFactory::task($this->parent->family, [$this->luca, $anna], ['time_of_day' => 'morning']);
        FamilyFactory::task($this->parent->family, [$this->luca], ['time_of_day' => 'evening']);
        $I->sendGet('me/today'); // generates today's occurrences
        $this->sender->expired = ['https://push.example/anna'];

        $service = NotificationService::create();
        $I->assertSame(1, $service->sendRoutineReminders('2026-11-16', '07:30'));
        $I->assertSame(0, $service->sendRoutineReminders('2026-11-16', '07:30'));
        $I->assertSame(0, $service->sendRoutineReminders('2026-11-16', '07:31'));
        $I->assertSame(['routine_reminder', 'routine_reminder'], $this->sender->types());
        $kid = array_values(array_filter($this->sender->sent, fn (array $s): bool => $s['endpoint'] === 'https://push.example/kid'));
        $I->assertSame(['name' => 'Luca', 'count' => 1, 'time_of_day' => 'morning'], $kid[0]['payload']['params']);
        $I->assertFalse((new Query())->from('{{%push_subscription}}')->where(['endpoint_hash' => hash('sha256', 'https://push.example/anna')])->exists());
    }
}
