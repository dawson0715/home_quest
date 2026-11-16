<?php

/**
 * Loads backend/.env (KEY=value per line, never committed) into the process
 * environment, without overriding variables that are already set.
 * Production keeps its settings there (docs/08-deploy.md); dev has no .env and
 * uses the defaults in config/*.php.
 */
$file = dirname(__DIR__) . '/.env';
if (is_file($file)) {
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (strlen($value) > 1 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
            $value = substr($value, 1, -1);
        }
        if (getenv($key) === false) {
            putenv("$key=$value");
        }
    }
}
