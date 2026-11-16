<?php

declare(strict_types=1);

namespace app\modules\v1\controllers;

use app\controllers\ApiController;
use Yii;

/**
 * Public liveness/readiness probe.
 */
class HealthController extends ApiController
{
    /**
     * @return array<string, mixed>
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = ['index'];
        return $behaviors;
    }

    /**
     * @return array{status: string, db: bool, time: string}
     */
    public function actionIndex(): array
    {
        try {
            $db = Yii::$app->db->createCommand('SELECT 1')->queryScalar() == 1;
        } catch (\Throwable) {
            $db = false;
        }
        if (!$db) {
            Yii::$app->response->statusCode = 503;
        }

        return ['status' => $db ? 'ok' : 'degraded', 'db' => $db, 'time' => date(DATE_ATOM)];
    }
}
