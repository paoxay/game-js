<?php
require_once __DIR__ . '/price_cache_helper.php';

header('Content-Type: application/json');

$host = 'localhost'; $dbname = 'ppshop-js'; $username = 'root'; $password = ''; // ⚠️ ແກ້ໄຂ DB

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ຮັບຂໍ້ມູນ
    $id = $_POST['id'];
    $custom_name = trim($_POST['custom_name']);

    // ຖ້າຊື່ວ່າງເປົ່າ ໃຫ້ຕັ້ງເປັນ NULL (ໃຊ້ຊື່ເດີມ)
    $val = empty($custom_name) ? NULL : $custom_name;

    $stmt = $pdo->prepare("UPDATE game_packages SET custom_name = ? WHERE id = ?");
    if ($stmt->execute([$val, $id])) {
        rebuildPriceCache($pdo, __DIR__ . '/price_cache.json');
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error"]);
    }

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>