<?php

declare(strict_types=1);

namespace app\modules\v1\controllers;

use app\components\ApiException;
use app\components\ErrorCode;
use app\components\FamilyContext;
use app\components\PushSender;
use app\components\WebPushSender;
use app\controllers\ApiController;
use Yii;

/**
 * Web push subscriptions of parents and children (kids' devices).
 */
class PushController extends ApiController
{
    /**
     * @return array<string, mixed>
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = ['vapid-key'];
        return $behaviors;
    }

    /**
     * GET /push/vapid-key (public) → {public_key|null} (null = push disabled on this server)
     *
     * @return array{public_key: string|null}
     */
    public function actionVapidKey(): array
    {
        $sender = Yii::$container->get(PushSender::class);
        return ['public_key' => $sender instanceof WebPushSender ? $sender->publicKey() : null];
    }

    /**
     * POST /push/subscribe {endpoint, keys: {p256dh, auth}} → 201
     *
     * @return array{subscribed: bool}
     */
    public function actionSubscribe(): array
    {
        /** @var FamilyContext $context */
        $context = Yii::$app->get('familyContext');
        $request = Yii::$app->request;
        $endpoint = (string) $request->getBodyParam('endpoint', '');
        $keys = (array) $request->getBodyParam('keys', []);
        if (!preg_match('#^https://#', $endpoint) || strlen($endpoint) > 2000 || empty($keys['p256dh']) || empty($keys['auth'])) {
            throw new ApiException(ErrorCode::VALIDATION_FAILED, 'Invalid push subscription.', 422, 'endpoint');
        }
        $child = $context->getChild();
        if ($child === null) {
            $context->requireParent();
        }
        $row = [
            'family_id' => $context->getId(),
            'user_id' => $child === null ? (int) $context->getMember()->user_id : null,
            'child_id' => $child?->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => hash('sha256', $endpoint),
            'p256dh' => mb_substr((string) $keys['p256dh'], 0, 255),
            'auth' => mb_substr((string) $keys['auth'], 0, 255),
            'created_at' => time(),
        ];
        $db = Yii::$app->db;
        // the same browser re-subscribing (e.g. another child on the tablet) replaces the owner
        $db->createCommand()->delete('{{%push_subscription}}', ['endpoint_hash' => $row['endpoint_hash']])->execute();
        $db->createCommand()->insert('{{%push_subscription}}', $row)->execute();
        Yii::$app->response->statusCode = 201;
        return ['subscribed' => true];
    }

    /** DELETE /push/subscribe {endpoint} → 204 */
    public function actionUnsubscribe(): void
    {
        /** @var FamilyContext $context */
        $context = Yii::$app->get('familyContext');
        $endpoint = (string) Yii::$app->request->getBodyParam('endpoint', '');
        Yii::$app->db->createCommand()->delete('{{%push_subscription}}', [
            'endpoint_hash' => hash('sha256', $endpoint),
            'family_id' => $context->getId(),
        ])->execute();
        Yii::$app->response->statusCode = 204;
    }
}
