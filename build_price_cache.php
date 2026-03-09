<?php
require_once __DIR__ . '/price_cache_helper.php';
require_once __DIR__ . '/config.php';

try {
    $pdo = app_db_pdo();

    $cacheData = rebuildPriceCache($pdo, __DIR__ . '/price_cache.json');

    header('Content-Type: text/plain; charset=utf-8');
    echo 'Cache rebuilt successfully. Games: ' . ($cacheData['game_count'] ?? 0) . PHP_EOL;
    echo 'HTML: ' . ($cacheData['exports']['html'] ?? 'N/A') . PHP_EOL;
    echo 'Markdown: ' . ($cacheData['exports']['markdown'] ?? 'N/A') . PHP_EOL;
    echo 'Text: ' . ($cacheData['exports']['text'] ?? 'N/A');
} catch (Throwable $exception) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Cache rebuild failed: ' . $exception->getMessage();
}