<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

date_default_timezone_set('Europe/Rome');

Yii::$container->setSingleton(\app\components\PushSender::class, fn () => \app\components\WebPushSender::fromParams());

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'timeZone' => 'Europe/Rome',
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            // the API uses no cookies; production still sets its own key (COOKIE_KEY)
            'cookieValidationKey' => getenv('COOKIE_KEY') ?: 'jKS61kd8YiO-c8wVZvQJxBDpBr6rRhTT',
            // l'app è servita dalla root del dominio (rewrite verso web/), non da /web:
            // senza baseUrl esplicito Yii lo deduce da SCRIPT_NAME (/web/index.php) e lo
            // strip su REQUEST_URI (/api/...) fallisce, causando "Page not found".
            'baseUrl' => '',
            // in produzione Apache mappa /api/* direttamente su web/index.php (AliasMatch):
            // SCRIPT_NAME è l'URL richiesto, quindi lo script va dichiarato esplicitamente
            'scriptUrl' => '/index.php',
            // only behind a reverse proxy: trusted for X-Forwarded-For (rate limits per real IP)
            'trustedHosts' => array_values(array_filter(explode(',', getenv('TRUSTED_PROXIES') ?: ''))),
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ],
        ],
        'response' => [
            'format' => \yii\web\Response::FORMAT_JSON,
            'on beforeSend' => [\app\components\ResponseEnvelope::class, 'onBeforeSend'],
        ],
        'familyContext' => [
            'class' => \app\components\FamilyContext::class,
        ],
        'clock' => [
            'class' => \app\components\Clock::class,
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            // templates are complete documents
            'htmlLayout' => false,
            'textLayout' => false,
            // dev/test: messages are written to runtime/mail; set params['mailerDsn'] (params-local.php) to really send
            'useFileTransport' => empty($params['mailerDsn']),
            'transport' => empty($params['mailerDsn']) ? [] : ['dsn' => $params['mailerDsn']],
        ],
        'errorHandler' => [
            'class' => \app\components\ApiErrorHandler::class,
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => false,
            'enableSession' => false,
            'loginUrl' => null,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                    // privacy: never dump request globals ($_SERVER has the bearer token, $_POST children's data)
                    'logVars' => [],
                ],
            ],
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                'GET api/v1/health' => 'v1/health/index',
                'GET api/v1/me' => 'v1/me/index',
                'PATCH api/v1/me' => 'v1/me/update',
                'GET api/v1/family' => 'v1/family/view',
                'PATCH api/v1/family' => 'v1/family/update',
                'GET api/v1/family/members' => 'v1/family/members',
                'GET api/v1/family/export' => 'v1/family/export',
                'DELETE api/v1/family' => 'v1/family/delete',
                'POST api/v1/devices/pairing-code' => 'v1/device/pairing-code',
                'POST api/v1/devices/pair' => 'v1/device/pair',
                'GET api/v1/devices/profiles' => 'v1/device/profiles',
                'POST api/v1/devices/select-child' => 'v1/device/select-child',
                'GET api/v1/devices' => 'v1/device/index',
                'DELETE api/v1/devices/<id:\d+>' => 'v1/device/delete',
                [
                    'class' => 'yii\rest\UrlRule',
                    'controller' => ['v1/child'],
                    'prefix' => 'api',
                    'extraPatterns' => [
                        'PUT {id}/pin' => 'pin',
                        'GET {id}/transactions' => 'transactions',
                        'POST {id}/adjust' => 'adjust',
                        'OPTIONS {id}/<sub:(pin|transactions|adjust)>' => 'options',
                    ],
                ],
                ['class' => 'yii\rest\UrlRule', 'controller' => ['v1/task'], 'prefix' => 'api'],
                'GET api/v1/task-templates' => 'v1/task-template/index',
                [
                    'class' => 'yii\rest\UrlRule',
                    'controller' => ['v1/reward'],
                    'prefix' => 'api',
                    'extraPatterns' => ['POST {id}/redeem' => 'redeem', 'OPTIONS {id}/redeem' => 'options'],
                ],
                'GET api/v1/redemptions' => 'v1/redemption/index',
                'POST api/v1/redemptions/<id:\d+>/<action:(approve|reject|fulfill)>' => 'v1/redemption/<action>',
                'GET api/v1/badges' => 'v1/badge/index',
                'GET api/v1/stats' => 'v1/stats/index',
                'GET api/v1/push/vapid-key' => 'v1/push/vapid-key',
                'POST api/v1/push/subscribe' => 'v1/push/subscribe',
                'DELETE api/v1/push/subscribe' => 'v1/push/unsubscribe',
                [
                    'class' => 'yii\rest\UrlRule',
                    'controller' => ['v1/recipe'],
                    'prefix' => 'api',
                    'extraPatterns' => ['POST,DELETE {id}/image' => 'image', 'OPTIONS {id}/image' => 'options'],
                ],
                'GET api/v1/ingredients' => 'v1/ingredient/index',
                'POST api/v1/ingredients' => 'v1/ingredient/create',
                'GET api/v1/shopping-categories' => 'v1/ingredient/categories',
                'PUT api/v1/shopping-categories/order' => 'v1/shopping/category-order',
                'GET api/v1/favorite-items' => 'v1/shopping/favorites',
                'GET api/v1/shopping-lists' => 'v1/shopping/index',
                'POST api/v1/shopping-lists' => 'v1/shopping/create',
                'GET api/v1/shopping-lists/<id:\d+>' => 'v1/shopping/view',
                'PATCH api/v1/shopping-lists/<id:\d+>' => 'v1/shopping/update',
                'DELETE api/v1/shopping-lists/<id:\d+>' => 'v1/shopping/delete',
                'POST api/v1/shopping-lists/<id:\d+>/generate' => 'v1/shopping/generate',
                'POST api/v1/shopping-lists/<id:\d+>/items' => 'v1/shopping/add-item',
                'POST api/v1/shopping-lists/<id:\d+>/clear-checked' => 'v1/shopping/clear-checked',
                'POST api/v1/shopping-lists/<id:\d+>/sync' => 'v1/shopping/sync',
                'PUT,DELETE api/v1/shopping-lists/<id:\d+>/pantry/<ingredient:\d+>' => 'v1/shopping/pantry',
                'PATCH api/v1/shopping-items/<id:\d+>' => 'v1/shopping/update-item',
                'DELETE api/v1/shopping-items/<id:\d+>' => 'v1/shopping/delete-item',
                'POST api/v1/shopping-items/<id:\d+>/<state:(check|uncheck)>' => 'v1/shopping/check',
                'GET api/v1/meal-plans/<week:\d{4}-\d{2}-\d{2}>' => 'v1/meal-plan/view',
                'PATCH api/v1/meal-plans/<week:\d{4}-\d{2}-\d{2}>' => 'v1/meal-plan/update',
                'PUT api/v1/meal-plans/<week:\d{4}-\d{2}-\d{2}>/slots/<day:\d>/<meal:[a-z]+>' => 'v1/meal-plan/slot',
                'POST api/v1/meal-plans/<week:\d{4}-\d{2}-\d{2}>/copy-from' => 'v1/meal-plan/copy-from',
                'POST api/v1/meal-plans/<week:\d{4}-\d{2}-\d{2}>/save-as-template' => 'v1/meal-plan/save-as-template',
                'POST api/v1/meal-plans/<week:\d{4}-\d{2}-\d{2}>/proposals' => 'v1/meal-plan/propose',
                'POST api/v1/meal-entries/<id:\d+>/vote' => 'v1/meal-plan/vote',
                'POST api/v1/meal-entries/<id:\d+>/<decision:(accept|reject)>' => 'v1/meal-plan/review',
                'GET api/v1/meal-templates' => 'v1/meal-plan/templates',
                'DELETE api/v1/meal-templates/<id:\d+>' => 'v1/meal-plan/delete-template',
                'PUT api/v1/me/savings-goal' => 'v1/me/savings-goal',
                'GET api/v1/me/transactions' => 'v1/me/transactions',
                'GET api/v1/me/today' => 'v1/me/today',
                'GET api/v1/occurrences' => 'v1/occurrence/index',
                'POST api/v1/occurrences/bulk-approve' => 'v1/occurrence/bulk-approve',
                'PATCH api/v1/occurrences/<id:\d+>/checklist' => 'v1/occurrence/checklist',
                'POST api/v1/occurrences/<id:\d+>/<action:(submit|approve|reject)>' => 'v1/occurrence/<action>',
                // Must stay last: OPTIONS for the custom routes above
                'OPTIONS api/v1/<path:.+>' => 'v1/options/index',
            ],
        ],
    ],
    'modules' => [
        'v1' => [
            'class' => 'app\modules\v1\Module',
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
