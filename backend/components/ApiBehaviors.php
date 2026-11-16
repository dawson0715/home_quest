<?php

declare(strict_types=1);

namespace app\components;

use Yii;
use yii\filters\Cors;

/**
 * Behaviors shared by every API controller: CORS limited to the configured
 * origins (params['corsOrigins']; production is same-origin) and bearer auth.
 */
final class ApiBehaviors
{
    /**
     * @param array<string, mixed> $behaviors parent behaviors
     * @return array<string, mixed>
     */
    public static function apply(array $behaviors): array
    {
        $behaviors['authenticator'] = [
            'class' => ApiAuth::class,
            'except' => ['options'],
        ];

        // corsFilter must run before authenticator so pre-flight OPTIONS
        // (no Authorization header) is answered without a bearer token.
        return ['corsFilter' => [
            'class' => Cors::class,
            'cors' => [
                'Origin' => (array) Yii::$app->params['corsOrigins'],
                'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
                'Access-Control-Request-Headers' => ['Authorization', 'Content-Type', 'If-None-Match'],
                'Access-Control-Expose-Headers' => ['ETag', 'X-Pagination-Total-Count', 'X-Pagination-Page-Count'],
                'Access-Control-Max-Age' => 3600,
            ],
        ]] + $behaviors;
    }
}
