<?php

declare(strict_types=1);

namespace app\services;

use app\components\PushSender;
use app\models\ChildProfile;
use app\models\TaskOccurrence;
use Yii;
use yii\db\Query;

/**
 * Web push notifications. Payloads carry a `type` + params only: the service
 * worker builds the Italian text from it.json (`notifications.<type>`), so no
 * UI strings live in the backend. Failures never break the calling request.
 */
class NotificationService
{
    /** time_of_day => reminder time (Europe/Rome) */
    public const REMINDER_TIMES = ['morning' => '07:30', 'afternoon' => '16:30', 'evening' => '19:00'];

    public function __construct(private readonly PushSender $sender)
    {
    }

    public static function create(): self
    {
        /** @var PushSender $sender */
        $sender = Yii::$container->get(PushSender::class);
        return new self($sender);
    }

    /**
     * @param array<string, scalar|null> $params
     */
    public function notifyParents(int $familyId, string $type, array $params, string $url): int
    {
        return $this->deliver(['family_id' => $familyId, 'child_id' => null], $type, $params, $url);
    }

    /**
     * @param array<string, scalar|null> $params
     */
    public function notifyChild(ChildProfile $child, string $type, array $params, string $url): int
    {
        return $this->deliver(['family_id' => $child->family_id, 'child_id' => $child->id], $type, $params, $url);
    }

    /**
     * Sends routine reminders due at $time ("HH:MM") for $date, once per child and slot.
     *
     * @return int reminders sent
     */
    public function sendRoutineReminders(string $date, string $time): int
    {
        $timeOfDay = array_search($time, self::REMINDER_TIMES, true);
        if ($timeOfDay === false) {
            return 0;
        }
        $rows = (new Query())->from(TaskOccurrence::tableName() . ' o')
            ->innerJoin('{{%task}} t', 't.id = o.task_id')
            ->innerJoin('{{%child_profile}} c', 'c.id = o.child_id')
            ->innerJoin('{{%push_subscription}} p', 'p.child_id = c.id')
            ->where(['o.due_date' => $date, 'o.status' => [TaskOccurrence::STATUS_PENDING, TaskOccurrence::STATUS_REJECTED], 't.time_of_day' => $timeOfDay, 'c.archived_at' => null])
            ->groupBy('o.child_id')
            ->select(['child_id' => 'o.child_id', 'open' => 'COUNT(DISTINCT o.id)'])
            ->all();

        $sent = 0;
        foreach ($rows as $row) {
            if (!$this->claim('routine_reminder', "child:{$row['child_id']}:$timeOfDay", $date)) {
                continue;
            }
            $child = ChildProfile::findOne((int) $row['child_id']);
            if ($child !== null) {
                $sent += $this->notifyChild($child, 'routine_reminder', ['name' => $child->name, 'count' => (int) $row['open'], 'time_of_day' => $timeOfDay], '/today');
            }
        }
        return $sent;
    }

    /** Records a send once (kind, target, day); false if already done. */
    public function claim(string $kind, string $target, string $day): bool
    {
        return Yii::$app->db->createCommand(
            'INSERT IGNORE INTO {{%notification_log}} (kind, target, day, created_at) VALUES (:k, :t, :d, :c)',
            [':k' => $kind, ':t' => $target, ':d' => $day, ':c' => time()]
        )->execute() > 0;
    }

    /**
     * @param array{family_id: int, child_id: int|null} $to child_id null = parents
     * @param array<string, scalar|null> $params
     */
    private function deliver(array $to, string $type, array $params, string $url): int
    {
        if (!$this->sender->isEnabled()) {
            return 0;
        }
        $query = (new Query())->from('{{%push_subscription}}')->where(['family_id' => $to['family_id']]);
        $query->andWhere($to['child_id'] === null ? ['not', ['user_id' => null]] : ['child_id' => $to['child_id']]);
        $sent = 0;
        foreach ($query->all() as $sub) {
            try {
                $alive = $this->sender->send(
                    ['endpoint' => (string) $sub['endpoint'], 'p256dh' => (string) $sub['p256dh'], 'auth' => (string) $sub['auth']],
                    ['type' => $type, 'params' => $params, 'url' => $url]
                );
                if ($alive) {
                    $sent++;
                } else {
                    Yii::$app->db->createCommand()->delete('{{%push_subscription}}', ['id' => $sub['id']])->execute();
                }
            } catch (\Throwable $e) {
                Yii::error('Push error: ' . $e->getMessage(), __METHOD__);
            }
        }
        return $sent;
    }
}
