<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/price_cache_helper.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$transferPercent = isset($_POST['transfer_percent']) ? (float) $_POST['transfer_percent'] : null;
$cardPercent = isset($_POST['card_percent']) ? (float) $_POST['card_percent'] : null;

if ($transferPercent === null || $cardPercent === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing percent values']);
    exit;
}

if ($transferPercent < -100 || $transferPercent > 1000 || $cardPercent < -100 || $cardPercent > 1000) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Percent out of allowed range (-100 to 1000)']);
    exit;
}

function updateEnvValue($content, $key, $value) {
    $escapedKey = preg_quote($key, '/');
    $line = $key . '=' . $value;

    if (preg_match('/^' . $escapedKey . '=.*$/m', $content)) {
        return preg_replace('/^' . $escapedKey . '=.*$/m', $line, $content);
    }

    $trimmed = rtrim($content, "\r\n");
    if ($trimmed === '') {
        return $line . PHP_EOL;
    }

    return $trimmed . PHP_EOL . $line . PHP_EOL;
}

$envPath = __DIR__ . '/.env';
$envContent = is_file($envPath) ? file_get_contents($envPath) : '';
if ($envContent === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Cannot read .env file']);
    exit;
}

$transferValue = rtrim(rtrim(number_format($transferPercent, 4, '.', ''), '0'), '.');
$cardValue = rtrim(rtrim(number_format($cardPercent, 4, '.', ''), '0'), '.');
if ($transferValue === '') {
    $transferValue = '0';
}
if ($cardValue === '') {
    $cardValue = '0';
}

$envContent = updateEnvValue($envContent, 'TRANSFER_PERCENT', $transferValue);
$envContent = updateEnvValue($envContent, 'CARD_PERCENT', $cardValue);

if (file_put_contents($envPath, $envContent, LOCK_EX) === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Cannot write .env file']);
    exit;
}

try {
    $pdo = app_db_pdo();
    $cacheData = rebuildPriceCache($pdo, __DIR__ . '/price_cache.json', (float) $transferValue, (float) $cardValue);

    echo json_encode([
        'status' => 'success',
        'transfer_percent' => (float) $transferValue,
        'card_percent' => (float) $cardValue,
        'game_count' => (int) ($cacheData['game_count'] ?? 0),
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $exception->getMessage()]);
}
