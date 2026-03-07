<?php

function normalizeGameSearchTerm($value) {
    $value = trim((string) $value);
    $value = str_replace([' ', '+', '%20', '-', '_'], '', $value);
    return mb_strtolower($value, 'UTF-8');
}

function buildPriceMessageParts(array $rows, $percentAdd = 60) {
    $groupedData = [];
    $groupedDataCard = [];

    foreach ($rows as $row) {
        $gameName = trim($row['game_name']);
        $displayName = !empty($row['custom_name']) ? $row['custom_name'] : $row['package_name'];

        $roundedAmount = ceil(((float) $row['amount']) / 1000) * 1000;
        $price = number_format($roundedAmount);

        $rawCardAmount = $roundedAmount + ($roundedAmount * ($percentAdd / 100));
        $cardAmountRounded = ceil($rawCardAmount / 10000) * 10000;
        $cardPrice = number_format($cardAmountRounded);

        if (!isset($groupedData[$gameName])) {
            $groupedData[$gameName] = [];
        }
        if (!isset($groupedDataCard[$gameName])) {
            $groupedDataCard[$gameName] = [];
        }

        $groupedData[$gameName][] = "💎 {$displayName} : {$price}₭";
        $groupedDataCard[$gameName][] = "💎 {$displayName} : {$cardPrice}₭";
    }

    $normalParts = [];
    foreach ($groupedData as $name => $items) {
        $normalParts[] = "🎮 {$name}\n" . implode("\n", $items);
    }

    $cardParts = [];
    foreach ($groupedDataCard as $name => $items) {
        $cardParts[] = "🎮 {$name}\n" . implode("\n", $items);
    }

    $msgNormal = implode("\n\n➖➖➖➖➖➖➖➖➖➖\n\n", $normalParts);
    $msgCard = implode("\n\n➖➖➖➖➖➖➖➖➖➖\n\n", $cardParts);

    $part1 = "🏷️ ປະຈຸບັນ (ລາຄາໂອນ)\n" . $msgNormal;
    $part2 = "💳 ລາຄາບັດເຕີມເງິນ\n" . $msgCard;

    return [
        'price_text' => $part1,
        'price_text_1' => $part1,
        'price_text_2' => $part2,
        'total_parts' => 2,
    ];
}

function buildPriceCacheData(PDO $pdo, $percentAdd = 60) {
    $stmt = $pdo->query("SELECT game_name, package_name, custom_name, amount, sort_order FROM game_packages ORDER BY game_name ASC, sort_order ASC, amount ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $groupedByGame = [];
    foreach ($rows as $row) {
        $gameName = trim($row['game_name']);
        if (!isset($groupedByGame[$gameName])) {
            $groupedByGame[$gameName] = [];
        }
        $groupedByGame[$gameName][] = $row;
    }

    $games = [];
    foreach ($groupedByGame as $gameName => $gameRows) {
        $normalized = normalizeGameSearchTerm($gameName);
        $messages = buildPriceMessageParts($gameRows, $percentAdd);
        $games[$normalized] = [
            'game_name' => $gameName,
            'normalized_game' => $normalized,
            'row_count' => count($gameRows),
            'messages' => $messages,
        ];
    }

    return [
        'generated_at' => date('c'),
        'game_count' => count($games),
        'games' => $games,
    ];
}

function writePriceCacheFile($filePath, array $cacheData) {
    file_put_contents(
        $filePath,
        json_encode($cacheData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

function rebuildPriceCache(PDO $pdo, $filePath, $percentAdd = 60) {
    $cacheData = buildPriceCacheData($pdo, $percentAdd);
    writePriceCacheFile($filePath, $cacheData);
    return $cacheData;
}

function loadPriceCacheFile($filePath) {
    if (!is_file($filePath)) {
        return null;
    }

    $content = file_get_contents($filePath);
    if ($content === false || $content === '') {
        return null;
    }

    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : null;
}

function searchPriceCache(array $cacheData, $searchTerm) {
    $normalized = normalizeGameSearchTerm($searchTerm);
    if ($normalized === '') {
        return null;
    }

    if (isset($cacheData['games'][$normalized])) {
        return $cacheData['games'][$normalized];
    }

    foreach ($cacheData['games'] as $entry) {
        if (mb_strpos($entry['normalized_game'], $normalized) !== false) {
            return $entry;
        }
    }

    return null;
}