<?php

function normalizeGameSearchTerm($value) {
    $value = trim((string) $value);
    $value = str_replace([' ', '+', '%20', '-', '_'], '', $value);
    return mb_strtolower($value, 'UTF-8');
}

function buildPriceMessageParts(array $rows, $transferPercent = 0, $cardPercent = 60) {
    $groupedData = [];
    $groupedDataCard = [];

    foreach ($rows as $row) {
        $gameName = trim($row['game_name']);
        $displayName = !empty($row['custom_name']) ? $row['custom_name'] : $row['package_name'];

        $rawTransferAmount = ((float) $row['amount']) + (((float) $row['amount']) * ($transferPercent / 100));
        $roundedAmount = ceil($rawTransferAmount / 1000) * 1000;
        $price = number_format($roundedAmount);

        $rawCardAmount = $roundedAmount + ($roundedAmount * ($cardPercent / 100));
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

function buildPriceCacheData(PDO $pdo, $transferPercent = 0, $cardPercent = 60) {
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
        $messages = buildPriceMessageParts($gameRows, $transferPercent, $cardPercent);
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

function ensureDirectoryExists($dirPath) {
    if (!is_dir($dirPath)) {
        mkdir($dirPath, 0777, true);
    }
}

function buildMarkdownExport(array $cacheData) {
    $lines = [];
    $lines[] = '# PPShop Price Knowledge Base';
    $lines[] = '';
    $lines[] = '- Generated at: ' . ($cacheData['generated_at'] ?? date('c'));
    $lines[] = '- Total games: ' . ($cacheData['game_count'] ?? 0);
    $lines[] = '- Source: automatic export from price cache';
    $lines[] = '';
    $lines[] = 'Use this file as the latest game price reference.';
    $lines[] = '';

    foreach ($cacheData['games'] as $entry) {
        $lines[] = '## ' . $entry['game_name'];
        $lines[] = '';
        $lines[] = '### Transfer Price';
        $lines[] = '';

        foreach (explode("\n", $entry['messages']['price_text_1']) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '🏷️') || str_starts_with($trimmed, '🎮')) {
                continue;
            }
            $lines[] = '- ' . $trimmed;
        }

        $lines[] = '';
        $lines[] = '### Card Price';
        $lines[] = '';

        foreach (explode("\n", $entry['messages']['price_text_2']) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '💳') || str_starts_with($trimmed, '🎮')) {
                continue;
            }
            $lines[] = '- ' . $trimmed;
        }

        $lines[] = '';
    }

    return implode(PHP_EOL, $lines) . PHP_EOL;
}

function buildTextExport(array $cacheData) {
    $lines = [];
    $lines[] = 'PPShop Price Knowledge Base';
    $lines[] = 'Generated at: ' . ($cacheData['generated_at'] ?? date('c'));
    $lines[] = 'Total games: ' . ($cacheData['game_count'] ?? 0);
    $lines[] = str_repeat('=', 60);
    $lines[] = '';

    foreach ($cacheData['games'] as $entry) {
        $lines[] = 'GAME: ' . $entry['game_name'];
        $lines[] = 'TRANSFER PRICE';

        foreach (explode("\n", $entry['messages']['price_text_1']) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '🏷️') || str_starts_with($trimmed, '🎮')) {
                continue;
            }
            $lines[] = $trimmed;
        }

        $lines[] = '';
        $lines[] = 'CARD PRICE';

        foreach (explode("\n", $entry['messages']['price_text_2']) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '💳') || str_starts_with($trimmed, '🎮')) {
                continue;
            }
            $lines[] = $trimmed;
        }

        $lines[] = '';
        $lines[] = str_repeat('-', 60);
        $lines[] = '';
    }

    return implode(PHP_EOL, $lines) . PHP_EOL;
}

function buildHtmlExport(array $cacheData) {
    $title = 'PPShop Price Knowledge Base';
    $generatedAt = htmlspecialchars($cacheData['generated_at'] ?? date('c'), ENT_QUOTES, 'UTF-8');
    $totalGames = (int) ($cacheData['game_count'] ?? 0);

    $sections = [];
    foreach ($cacheData['games'] as $entry) {
        $gameName = htmlspecialchars($entry['game_name'], ENT_QUOTES, 'UTF-8');

        $transferItems = [];
        foreach (explode("\n", $entry['messages']['price_text_1']) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '🏷️') || str_starts_with($trimmed, '🎮')) {
                continue;
            }
            $transferItems[] = '<li>' . htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8') . '</li>';
        }

        $cardItems = [];
        foreach (explode("\n", $entry['messages']['price_text_2']) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '💳') || str_starts_with($trimmed, '🎮')) {
                continue;
            }
            $cardItems[] = '<li>' . htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8') . '</li>';
        }

        $sections[] = '<section class="game">'
            . '<h2>' . $gameName . '</h2>'
            . '<div class="columns">'
            . '<div class="card"><h3>Transfer Price</h3><ul>' . implode('', $transferItems) . '</ul></div>'
            . '<div class="card"><h3>Card Price</h3><ul>' . implode('', $cardItems) . '</ul></div>'
            . '</div>'
            . '</section>';
    }

    return '<!DOCTYPE html>'
        . '<html lang="lo"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>' . $title . '</title>'
        . '<style>'
        . 'body{font-family:Segoe UI,Tahoma,sans-serif;background:#f5f7fb;color:#1f2937;margin:0;padding:32px;line-height:1.5;}'
        . '.wrap{max-width:1100px;margin:0 auto;}'
        . 'h1{margin:0 0 8px;font-size:32px;} .meta{color:#6b7280;margin-bottom:24px;}'
        . '.game{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:20px;margin-bottom:20px;box-shadow:0 6px 20px rgba(0,0,0,.04);}'
        . '.columns{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;}'
        . '.card{background:#f9fafb;border-radius:12px;padding:16px;border:1px solid #eef2f7;}'
        . 'h2{margin:0 0 14px;font-size:24px;color:#0f172a;} h3{margin:0 0 10px;font-size:16px;color:#2563eb;}'
        . 'ul{margin:0;padding-left:18px;} li{margin:6px 0;}'
        . '</style></head><body><div class="wrap">'
        . '<h1>' . $title . '</h1>'
        . '<p class="meta">Generated at: ' . $generatedAt . ' | Total games: ' . $totalGames . '</p>'
        . implode('', $sections)
        . '</div></body></html>';
}

function writePriceKnowledgeExports($baseDir, array $cacheData) {
    $exportDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'exports';
    ensureDirectoryExists($exportDir);

    $markdownPath = $exportDir . DIRECTORY_SEPARATOR . 'price_knowledge.md';
    $textPath = $exportDir . DIRECTORY_SEPARATOR . 'price_knowledge.txt';
    $htmlPath = $exportDir . DIRECTORY_SEPARATOR . 'price_knowledge.html';

    file_put_contents($markdownPath, buildMarkdownExport($cacheData), LOCK_EX);
    file_put_contents($textPath, buildTextExport($cacheData), LOCK_EX);
    file_put_contents($htmlPath, buildHtmlExport($cacheData), LOCK_EX);

    return [
        'export_dir' => $exportDir,
        'markdown' => $markdownPath,
        'text' => $textPath,
        'html' => $htmlPath,
    ];
}

function rebuildPriceCache(PDO $pdo, $filePath, $transferPercent = 0, $cardPercent = 60) {
    $cacheData = buildPriceCacheData($pdo, $transferPercent, $cardPercent);
    writePriceCacheFile($filePath, $cacheData);
    $cacheData['exports'] = writePriceKnowledgeExports(dirname($filePath), $cacheData);
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