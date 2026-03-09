<?php

function app_load_env($envPath) {
    static $loaded = false;
    if ($loaded) {
        return;
    }

    if (!is_file($envPath)) {
        $loaded = true;
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        $loaded = true;
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        if ($key === '') {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    $loaded = true;
}

function app_env($key, $default = null) {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return ($value === false || $value === null || $value === '') ? $default : $value;
}

function app_cfg() {
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }

    app_load_env(__DIR__ . '/.env');

    $cfg = [
        'db' => [
            'host' => app_env('DB_HOST', 'localhost'),
            'name' => app_env('DB_NAME', 'ppshop-js'),
            'user' => app_env('DB_USER', 'root'),
            'pass' => app_env('DB_PASS', ''),
        ],
        'ppshop' => [
            'token' => app_env('PPSHOP_TOKEN', ''),
            'encrypted' => app_env('PPSHOP_X_ENCRYPTED', ''),
            'origin' => app_env('PPSHOP_ORIGIN', 'https://admin.ppshope.com'),
            'referer' => app_env('PPSHOP_REFERER', 'https://admin.ppshope.com/'),
            'timeout' => (int) app_env('PPSHOP_TIMEOUT', 60),
        ],
        'facebook' => [
            'page_access_token' => app_env('FB_PAGE_ACCESS_TOKEN', ''),
            'verify_token' => app_env('FB_VERIFY_TOKEN', ''),
        ],
        'pricing' => [
            'transfer_percent' => (float) app_env('TRANSFER_PERCENT', 0),
            'card_percent' => (float) app_env('CARD_PERCENT', 60),
        ],
    ];

    return $cfg;
}

function app_db_pdo() {
    $db = app_cfg()['db'];

    $pdo = new PDO(
        "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4",
        $db['user'],
        $db['pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $pdo;
}
