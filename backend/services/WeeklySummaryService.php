<?php

declare(strict_types=1);

namespace app\services;

use app\models\Family;
use app\models\FamilyMember;
use Yii;

/**
 * Weekly email to parents with an email address, for families that enabled
 * `settings.weekly_email`. Idempotent per family and week (notification_log).
 */
class WeeklySummaryService
{
    /**
     * Sends the summary of the 7 days ending on $lastDay.
     *
     * @return int emails sent
     */
    public function sendAll(string $lastDay): int
    {
        $from = (new \DateTimeImmutable($lastDay))->modify('-6 days')->format('Y-m-d');
        $notifications = NotificationService::create();
        $sent = 0;
        /** @var Family $family */
        foreach (Family::find()->each(50) as $family) {
            if (!($family->getEffectiveSettings()['weekly_email'] ?? false)) {
                continue;
            }
            $emails = array_filter(FamilyMember::find()->forFamily($family->id)->joinWith('user u', false)
                ->select('u.email')->column());
            if ($emails === [] || !$notifications->claim('weekly_email', "family:{$family->id}", $from)) {
                continue;
            }
            $stats = (new StatsService($family->id))->compute($from, $lastDay);
            foreach ($emails as $email) {
                $ok = Yii::$app->mailer->compose('weekly-summary', [
                    'familyName' => $family->name,
                    'from' => $from,
                    'to' => $lastDay,
                    'children' => $stats['children'],
                ])
                    ->setFrom(Yii::$app->params['mailerFrom'])
                    ->setTo((string) $email)
                    ->setSubject("HomeQuest · la settimana della famiglia {$family->name}")
                    ->send();
                $sent += $ok ? 1 : 0;
            }
        }
        return $sent;
    }
}
