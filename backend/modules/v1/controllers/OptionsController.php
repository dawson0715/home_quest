<?php

declare(strict_types=1);

namespace app\modules\v1\controllers;

use app\controllers\ApiController;
use Yii;

/**
 * Answers OPTIONS for custom (non-UrlRule) routes. CORS preflights are already
 * answered by the Cors filter in ApiController before reaching the action.
 */
class OptionsController extends ApiController
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

    public function actionIndex(): void
    {
        Yii::$app->response->statusCode = 204;
    }
}
