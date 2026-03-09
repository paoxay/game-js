<?php
require_once __DIR__ . '/price_cache_helper.php';
require_once __DIR__ . '/config.php';

// 1. ຕັ້ງຄ່າ Error ສຳລັບ Production
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/api_error_log.txt');
error_reporting(E_ALL);

$requestStartedAt = microtime(true);

function apiLog($message, array $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $line = '[' . $timestamp . '] ' . $message;

    if (!empty($context)) {
        $line .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    file_put_contents(__DIR__ . '/api_request_log.txt', $line . PHP_EOL, FILE_APPEND);
}

function respondJson(array $payload, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

set_exception_handler(function ($exception) {
    apiLog('Unhandled exception', [
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
    ]);

    respondJson([
        'success' => false,
        'game_name' => 'Error',
        'price_text' => '❌ ລະບົບຂັດຂ້ອງ ກະລຸນາລອງໃໝ່',
        'price_text_1' => '❌ ລະບົບຂັດຂ້ອງ ກະລຸນາລອງໃໝ່',
        'price_text_2' => '',
    ], 500);
});

register_shutdown_function(function () use ($requestStartedAt) {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        apiLog('Fatal shutdown error', $error);
        if (!headers_sent()) {
            respondJson([
                'success' => false,
                'game_name' => 'Error',
                'price_text' => '❌ ລະບົບຂັດຂ້ອງ ກະລຸນາລອງໃໝ່',
                'price_text_1' => '❌ ລະບົບຂັດຂ້ອງ ກະລຸນາລອງໃໝ່',
                'price_text_2' => '',
            ], 500);
        }
    }

    apiLog('Request completed', [
        'duration_ms' => (int) round((microtime(true) - $requestStartedAt) * 1000),
    ]);
});

// ຕັ້ງຄ່າ Header
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = app_db_pdo();
} catch (PDOException $e) {
    apiLog('Database connection failed', ['error' => $e->getMessage()]);
    respondJson([
        'success' => false,
        'game_name' => 'DB Error',
        'price_text' => '❌ ຕິດຕໍ່ຖານຂໍ້ມູນບໍ່ໄດ້',
        'price_text_1' => '❌ ຕິດຕໍ່ຖານຂໍ້ມູນບໍ່ໄດ້',
        'price_text_2' => '',
    ], 500);
}

// 3. ຮັບຄ່າຄົ້ນຫາ
$searchGame = isset($_GET['game']) ? trim($_GET['game']) : '';
$cleanSearch = normalizeGameSearchTerm($searchGame);
$cacheFilePath = __DIR__ . '/price_cache.json';

apiLog('Incoming request', [
    'raw_game' => $searchGame,
    'normalized_game' => $cleanSearch,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
]);

// 4. ຄົ້ນຫາຈາກ Cache ກ່ອນ ເພື່ອໃຫ້ຕອບໄວທີ່ສຸດ
$cacheData = loadPriceCacheFile($cacheFilePath);
if (is_array($cacheData) && !empty($cacheData['games'])) {
    $cachedEntry = searchPriceCache($cacheData, $searchGame);
    if ($cachedEntry) {
        apiLog('Served from cache', [
            'game' => $searchGame,
            'matched_game' => $cachedEntry['game_name'],
            'generated_at' => $cacheData['generated_at'] ?? null,
        ]);

        respondJson([
            'success' => true,
            'game_name' => $cachedEntry['game_name'],
            'price_text' => $cachedEntry['messages']['price_text'],
            'price_text_1' => $cachedEntry['messages']['price_text_1'],
            'price_text_2' => $cachedEntry['messages']['price_text_2'],
            'total_parts' => $cachedEntry['messages']['total_parts'],
            'source' => 'cache',
        ]);
    }
}

// 5. ຖ້າ cache ບໍ່ພ້ອມ ຫຼື ບໍ່ພົບ ຈຶ່ງ fallback ໄປ DB
try {
    $stmt = $pdo->query("SELECT game_name, package_name, custom_name, amount, sort_order FROM game_packages ORDER BY game_name ASC, sort_order ASC, amount ASC");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    apiLog('SQL query failed', [
        'error' => $e->getMessage(),
        'game' => $searchGame,
    ]);
    respondJson([
        'success' => false,
        'game_name' => $searchGame,
        'price_text' => '❌ ຄົ້ນຫາຂໍ້ມູນບໍ່ສຳເລັດ',
        'price_text_1' => '❌ ຄົ້ນຫາຂໍ້ມູນບໍ່ສຳເລັດ',
        'price_text_2' => '',
    ], 500);
}

// 5. ປະມວນຜົນຂໍ້ມູນ
$percent_add = 60;

if (empty($results)) {
    apiLog('No results found', ['game' => $searchGame]);
    respondJson([
        "success" => false,
        "game_name" => "Not Found",
        "price_text" => "❌ ບໍ່ພົບຂໍ້ມູນເກມທີ່ຄົ້ນຫາ: " . htmlspecialchars($searchGame, ENT_QUOTES, 'UTF-8'),
        "price_text_1" => "❌ ບໍ່ພົບຂໍ້ມູນເກມທີ່ຄົ້ນຫາ: " . htmlspecialchars($searchGame, ENT_QUOTES, 'UTF-8'),
        "price_text_2" => ""
    ]);
}

$cacheData = rebuildPriceCache($pdo, $cacheFilePath, $percent_add);
$cachedEntry = searchPriceCache($cacheData, $searchGame);

if ($cachedEntry) {
    apiLog('Served after cache rebuild', [
        'game' => $searchGame,
        'matched_game' => $cachedEntry['game_name'],
        'generated_at' => $cacheData['generated_at'] ?? null,
    ]);

    respondJson([
        'success' => true,
        'game_name' => $cachedEntry['game_name'],
        'price_text' => $cachedEntry['messages']['price_text'],
        'price_text_1' => $cachedEntry['messages']['price_text_1'],
        'price_text_2' => $cachedEntry['messages']['price_text_2'],
        'total_parts' => $cachedEntry['messages']['total_parts'],
        'source' => 'cache-rebuilt',
    ]);
}

apiLog('No results found after cache rebuild', ['game' => $searchGame]);
respondJson([
    'success' => false,
    'game_name' => 'Not Found',
    'price_text' => '❌ ບໍ່ພົບຂໍ້ມູນເກມທີ່ຄົ້ນຫາ: ' . htmlspecialchars($searchGame, ENT_QUOTES, 'UTF-8'),
    'price_text_1' => '❌ ບໍ່ພົບຂໍ້ມູນເກມທີ່ຄົ້ນຫາ: ' . htmlspecialchars($searchGame, ENT_QUOTES, 'UTF-8'),
    'price_text_2' => '',
]);
?>