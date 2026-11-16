<?php

require __DIR__ . '/../config/env.php';

// HQ_ENV=prod in production (backend/.env): no debug, no gii/debug modules
$env = getenv('HQ_ENV') ?: 'dev';
defined('YII_DEBUG') or define('YII_DEBUG', $env !== 'prod');
defined('YII_ENV') or define('YII_ENV', $env);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/../config/web.php';

(new yii\web\Application($config))->run();
