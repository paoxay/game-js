<?php
require_once __DIR__ . '/price_cache_helper.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $pdo = app_db_pdo();

    $rawInput = file_get_contents('php://input');
    $jsonInput = json_decode($rawInput, true);

    $stmt = $pdo->prepare("UPDATE game_packages SET custom_name = ? WHERE id = ?");

    if (is_array($jsonInput) && isset($jsonInput['items']) && is_array($jsonInput['items'])) {
        $updated = 0;

        foreach ($jsonInput['items'] as $item) {
            $id = isset($item['id']) ? (int) $item['id'] : 0;
            $customName = trim((string) ($item['custom_name'] ?? ''));
            $val = $customName === '' ? null : $customName;

            if ($id <= 0) {
                continue;
            }

            if ($stmt->execute([$val, $id])) {
                $updated++;
            }
        }

        rebuildPriceCache($pdo, __DIR__ . '/price_cache.json');
        echo json_encode(["status" => "success", "mode" => "bulk", "updated" => $updated]);
        exit;
    }

    // ຮັບຂໍ້ມູນແບບບັນທຶກລາຍອັນ
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $custom_name = trim((string) ($_POST['custom_name'] ?? ''));

    // ຖ້າຊື່ວ່າງເປົ່າ ໃຫ້ຕັ້ງເປັນ NULL (ໃຊ້ຊື່ເດີມ)
    $val = empty($custom_name) ? NULL : $custom_name;

    if ($id <= 0) {
        echo json_encode(["status" => "error", "message" => "Invalid ID"]);
        exit;
    }

    if ($stmt->execute([$val, $id])) {
        rebuildPriceCache($pdo, __DIR__ . '/price_cache.json');
        echo json_encode(["status" => "success", "mode" => "single", "id" => $id]);
    } else {
        echo json_encode(["status" => "error"]);
    }

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>