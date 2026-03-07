<?php
require_once __DIR__ . '/price_cache_helper.php';

$host = 'localhost';
$dbname = 'ppshop-js';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $cacheData = rebuildPriceCache($pdo, __DIR__ . '/price_cache.json');

    header('Content-Type: text/plain; charset=utf-8');
    echo 'Cache rebuilt successfully. Games: ' . ($cacheData['game_count'] ?? 0);
} catch (Throwable $exception) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Cache rebuild failed: ' . $exception->getMessage();
}