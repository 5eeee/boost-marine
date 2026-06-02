<?php

function bm_env(string $key, string $default = ''): string
{
    static $loaded = false;

    if (!$loaded) {
        $envFile = __DIR__ . '/.env';
        if (is_readable($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                if (!str_contains($line, '=')) {
                    continue;
                }
                [$name, $value] = explode('=', $line, 2);
                putenv(trim($name) . '=' . trim($value));
            }
        }
        $loaded = true;
    }

    $value = getenv($key);
    return ($value !== false && $value !== '') ? $value : $default;
}
