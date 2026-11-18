<?php

/**
 * Application configuration for Codeception functional/unit tests:
 * the real web config with the test database.
 */

$config = require __DIR__ . '/web.php';

$config['id'] = 'homequest-tests';
$config['components']['db'] = require __DIR__ . '/test_db.php';
$config['components']['request']['cookieValidationKey'] = 'test';
$config['components']['request']['enableCsrfValidation'] = false;
$config['components']['urlManager']['showScriptName'] = false;

// Rate-limit counters must survive the per-request app reset but not leak between runs
$config['components']['cache'] = ['class' => 'yii\caching\FileCache', 'cachePath' => '@runtime/cache-test'];

// Never call the real AI provider from tests (a dev .env may hold a key)
$config['params']['gemini']['apiKey'] = null;

return $config;
