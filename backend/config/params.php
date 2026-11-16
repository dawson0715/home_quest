<?php

$local = is_file(__DIR__ . '/params-local.php') ? require __DIR__ . '/params-local.php' : [];

// Secrets (VAPID keys, mailer DSN) go in params-local.php, which is not committed.

return array_replace_recursive([
    'vapid' => [],
    // cross-origin callers allowed (dev servers); production serves app and API from the same origin
    'corsOrigins' => ['http://localhost:5173', 'http://localhost:4173'],
    'mailerFrom' => 'noreply@homequest.local',
    'mailerDsn' => null,
], $local);
