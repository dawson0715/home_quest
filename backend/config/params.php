<?php

$local = is_file(__DIR__ . '/params-local.php') ? require __DIR__ . '/params-local.php' : [];

// Secrets (VAPID keys, mailer DSN) go in params-local.php, which is not committed.

return array_replace_recursive([
    // production: backend/.env (VAPID_*, MAILER_*, GEMINI_*); development: params-local.php
    'vapid' => array_filter([
        'subject' => getenv('VAPID_SUBJECT') ?: null,
        'publicKey' => getenv('VAPID_PUBLIC_KEY') ?: null,
        'privateKey' => getenv('VAPID_PRIVATE_KEY') ?: null,
    ]),
    // cross-origin callers allowed (dev servers); production serves app and API from the same origin
    'corsOrigins' => getenv('HQ_ENV') === 'prod' ? [] : ['http://localhost:5173', 'http://localhost:4173'],
    'mailerFrom' => getenv('MAILER_FROM') ?: 'noreply@homequest.local',
    'mailerDsn' => getenv('MAILER_DSN') ?: null,
    // AI menu suggestions (Gemini, paid tier). Without a key the local algorithm is used.
    'gemini' => [
        'apiKey' => getenv('GEMINI_API_KEY') ?: null,
        'model' => getenv('GEMINI_MODEL') ?: 'gemini-3.5-flash-lite',
        'timeout' => 40,
    ],
    // AI calls per family per day (beyond: local algorithm)
    'aiDailyLimit' => 20,
], $local);
