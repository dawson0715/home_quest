<?php

declare(strict_types=1);

/**
 * Analysis-only application description for yii2-extensions/phpstan.
 */
return [
    'params' => require __DIR__ . '/params.php',
    'components' => [
        'db' => ['class' => yii\db\Connection::class],
        'user' => ['class' => yii\web\User::class, 'identityClass' => app\models\User::class],
        'familyContext' => ['class' => app\components\FamilyContext::class],
        'clock' => ['class' => app\components\Clock::class],
        'mailer' => ['class' => yii\symfonymailer\Mailer::class],
        'errorHandler' => ['class' => app\components\ApiErrorHandler::class],
    ],
];
