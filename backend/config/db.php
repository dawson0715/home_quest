<?php

// Defaults = docker-compose.yml (development); production sets DB_* (backend/.env)
return [
    'class' => 'yii\db\Connection',
    'dsn' => sprintf('mysql:host=%s;dbname=%s', getenv('DB_HOST') ?: 'hq_mysql', getenv('DB_NAME') ?: 'homequest'),
    'username' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: 'database',
    'charset' => 'utf8mb4',
    'enableSchemaCache' => getenv('HQ_ENV') === 'prod',
    'schemaCacheDuration' => 3600,
    'schemaCache' => 'cache',
];
