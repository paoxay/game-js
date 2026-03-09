<?php
require_once __DIR__ . '/config.php';
// ---------------------------------------------------------
// 1. ຕັ້ງຄ່າການເຊື່ອມຕໍ່ຖານຂໍ້ມູນ (Database Connection)
// ---------------------------------------------------------
try {
    $pdo = app_db_pdo();
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

// ---------------------------------------------------------
// 2. ຟັງຊັນຍິງ API (ໃຊ້ຊ້ຳໄດ້)
// ---------------------------------------------------------
function callAPI($url) {
    $ppshopCfg = app_cfg()['ppshop'];
    $token = $ppshopCfg['token'];
    $encrypted = $ppshopCfg['encrypted'];

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $ppshopCfg['timeout'],
        CURLOPT_HTTPHEADER => array(
            'accept: application/json',
            'authorization: Bearer ' . $token,
            'x-encrypted: ' . $encrypted,
            'origin: ' . $ppshopCfg['origin'],
            'referer: ' . $ppshopCfg['referer']
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return json_decode($response, true);
}

// ---------------------------------------------------------
// 3. ເລີ່ມຂະບວນການດຶງ ແລະ ບັນທຶກ
// ---------------------------------------------------------

// A. ລ້າງຂໍ້ມູນເກົ່າອອກກ່ອນ (Optional: ຖ້າຢາກເກັບປະຫວັດໃຫ້ລົບແຖວນີ້ອອກ)
$pdo->exec("TRUNCATE TABLE game_packages");

// B. ດຶງລາຍຊື່ເກມທັງໝົດ
$gamesList = callAPI('https://server-api-prod.ppshope.com/api/v1/games');
$countSaved = 0;

if (isset($gamesList['data'])) {
    
    // ກຽມຄຳສັ່ງ SQL (Prepare Statement) ເພື່ອຄວາມໄວ ແລະ ປອດໄພ
    $stmt = $pdo->prepare("INSERT INTO game_packages (idgame, game_name, package_name, amount) VALUES (?, ?, ?, ?)");

    foreach ($gamesList['data'] as $game) {
        // ກວດສອບສະເພາະເກມທີ່ Active
        if (isset($game['active']) && $game['active'] === true) {
            
            $targetIds = [];

            // ກວດສອບວ່າເປັນເກມດ່ຽວ ຫຼື ມີລູກ
            if (!empty($game['children'])) {
                foreach ($game['children'] as $child) {
                    if (isset($child['active']) && $child['active'] === true) {
                        $targetIds[] = $child['_id'];
                    }
                }
            } else {
                $targetIds[] = $game['_id'];
            }

            // ວົນລູບ ID ເພື່ອໄປດຶງແພັກເກັດ
            foreach ($targetIds as $gameId) {
                $packData = callAPI("https://server-api-prod.ppshope.com/api/v1/packets-admin?gameId=" . $gameId);

                if (isset($packData['data'])) {
                    foreach ($packData['data'] as $packet) {
                        // ດຶງຂໍ້ມູນທີ່ເຈົ້າຕ້ອງການ
                        $id_game_db = $packet['gameId']['_id'] ?? $gameId;
                        $name_game_db = $packet['gameId']['name'] ?? 'Unknown';
                        $name_pack_db = $packet['name']; // ຊື່ແພັກເກັດ (ເຊັ່ນ: 33, 68)
                        $amount_db    = $packet['amount']; // ລາຄາ

                        // ບັນທຶກລົງຖານຂໍ້ມູນ
                        $stmt->execute([$id_game_db, $name_game_db, $name_pack_db, $amount_db]);
                        $countSaved++;
                    }
                }
            }
        }
    }
}

echo "ບັນທຶກຂໍ້ມູນສຳເລັດແລ້ວ! ທັງໝົດ $countSaved ລາຍການ.";
?>