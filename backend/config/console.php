<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

Yii::$container->setSingleton(\app\components\PushSender::class, fn () => \app\components\WebPushSender::fromParams());

$config = [
    'id' => 'basic-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'app\commands',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
        '@tests' => '@app/tests',
    ],
    'components' => [
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'log' => [
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                    'logVars' => [],
                ],
            ],
        ],
        'db' => $db,
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
        'clock' => [
            'class' => \app\components\Clock::class,
        ],
        'familyContext' => [
            'class' => \app\components\FamilyContext::class,
        ],
    ],
    'params' => $params,
    /*
    'controllerMap' => [
        'fixture' => [ // Fixture generation command line.
            'class' => 'yii\faker\FixtureController',
        ],
    ],
    */
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
    ];
    // configuration adjustments for 'dev' environment
    // requires version `2.1.21` of yii2-debug module
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
