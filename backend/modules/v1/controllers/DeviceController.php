<?php

declare(strict_types=1);

namespace app\modules\v1\controllers;

use app\components\FamilyContext;
use app\controllers\ApiController;
use app\models\ChildProfile;
use app\models\Device;
use app\services\DeviceService;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;

/**
 * Device pairing (parents), profile picker and child login (paired devices).
 */
class DeviceController extends ApiController
{
    /**
     * @return array<string, mixed>
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = ['pair'];
        return $behaviors;
    }

    /**
     * POST /devices/pairing-code (parents) → {code, expires_at}
     *
     * @return array{code: string, expires_at: string}
     */
    public function actionPairingCode(): array
    {
        $context = $this->context();
        $context->requireParent();
        $pairing = $this->service()->createPairingCode($context->getMember());
        Yii::$app->response->statusCode = 201;

        return ['code' => $pairing->code, 'expires_at' => $this->iso($pairing->expires_at)];
    }

    /**
     * POST /devices/pair {code, device_name} (public) → {device_token, device}
     *
     * @return array{device_token: string, device: Device}
     */
    public function actionPair(): array
    {
        $request = Yii::$app->request;
        $result = $this->service()->pair(
            (string) $request->getBodyParam('code', ''),
            (string) $request->getBodyParam('device_name', ''),
            (string) $request->getUserIP(),
        );
        Yii::$app->response->statusCode = 201;

        return ['device_token' => $result['token'], 'device' => $result['device']];
    }

    /**
     * GET /devices/profiles (device token)
     *
     * @return list<array{id: int, name: string, avatar: string, color: string, has_pin: bool}>
     */
    public function actionProfiles(): array
    {
        $device = $this->requireDevice();
        return array_map(fn (ChildProfile $c): array => $c->toProfile(), $this->service()->profiles($device));
    }

    /**
     * POST /devices/select-child {child_id, pin?} (device token) → {child_token, child}
     *
     * @return array{child_token: string, child: ChildProfile}
     */
    public function actionSelectChild(): array
    {
        $device = $this->requireDevice();
        $request = Yii::$app->request;
        $pin = $request->getBodyParam('pin');
        $result = $this->service()->selectChild(
            $device,
            (int) $request->getBodyParam('child_id'),
            is_string($pin) ? $pin : null,
        );
        Yii::$app->response->statusCode = 201;

        return ['child_token' => $result['token'], 'child' => $result['session']->child];
    }

    /** GET /devices (parents): active devices, newest first. */
    public function actionIndex(): ActiveDataProvider
    {
        $context = $this->context();
        $context->requireParent();
        $query = Device::find()->forFamily($context->getId())->andWhere(['revoked_at' => null])->orderBy(['id' => SORT_DESC]);

        return new ActiveDataProvider(['query' => $query, 'pagination' => false]);
    }

    /** DELETE /devices/{id} (parents): revokes the device and its child sessions. */
    public function actionDelete(string $id): void
    {
        $context = $this->context();
        $context->requireParent();
        $device = Device::find()->forFamily($context->getId())->andWhere(['id' => $id])->one();
        if (!$device instanceof Device) {
            throw new NotFoundHttpException("Object not found: $id");
        }
        $this->service()->revoke($device);
        Yii::$app->response->statusCode = 204;
    }

    private function requireDevice(): Device
    {
        $context = $this->context();
        $context->requireRole(FamilyContext::ROLE_DEVICE);
        /** @var Device $device */
        $device = $context->getDevice();
        return $device;
    }

    private function context(): FamilyContext
    {
        return Yii::$app->get('familyContext');
    }

    private function service(): DeviceService
    {
        return DeviceService::create();
    }

    private function iso(string $dbDateTime): string
    {
        return (new \DateTimeImmutable($dbDateTime, new \DateTimeZone(Yii::$app->timeZone)))->format(DATE_ATOM);
    }
}
