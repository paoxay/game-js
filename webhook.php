<?php
require_once __DIR__ . '/config.php';
// ==================================================================
// 1. ການຕັ້ງຄ່າລະບົບ (CONFIG) - ແກ້ໄຂບ່ອນນີ້!
// ==================================================================
$cfg = app_cfg();
$fbCfg = $cfg['facebook'];


// ==================================================================
// 2. ລະບົບ LOGGING (ບັນທຶກການທຳງານ)
// ==================================================================
// ສ່ວນນີ້ຈະສ້າງຟາຍ log.txt ໃຫ້ເຈົ້າເປີດເບິ່ງໄດ້ວ່າບ໋ອດທຳງານແນວໃດ

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

// ຖ້າມີຂໍ້ມູນສົ່ງມາ ໃຫ້ບັນທຶກລົງ log.txt
if (!empty($inputJSON)) {
    $date = date("Y-m-d H:i:s");
    $logMsg = "[$date] 📩 ຂໍ້ມູນເຂົ້າ: " . $inputJSON . "\n" . str_repeat("-", 50) . "\n";
    file_put_contents('log.txt', $logMsg, FILE_APPEND);
}


// ==================================================================
// 3. ຢືນຢັນ WEBHOOK (VERIFY TOKEN)
// ==================================================================
// ສ່ວນນີ້ທຳງານຕອນເຈົ້າກົດປຸ່ມ "Verify and Save" ໃນ Facebook

if (isset($_GET['hub_mode']) && isset($_GET['hub_verify_token'])) {
    if ($_GET['hub_mode'] === 'subscribe' && $_GET['hub_verify_token'] === $fbCfg['verify_token']) {
        http_response_code(200);
        echo $_GET['hub_challenge'];
        exit;
    } else {
        http_response_code(403);
        echo 'Verification failed';
        exit;
    }
}


// ==================================================================
// 4. ຮັບຂໍ້ຄວາມ ແລະ ປະມວນຜົນ (MAIN LOGIC)
// ==================================================================

if (isset($input['entry'][0]['messaging'][0]['message']['text'])) {
    
    // ດຶງຂໍ້ມູນຜູ້ສົ່ງ ແລະ ຂໍ້ຄວາມ
    $senderId = $input['entry'][0]['messaging'][0]['sender']['id'];
    $messageText = trim($input['entry'][0]['messaging'][0]['message']['text']);

    // ເອີ້ນຟັງຊັນປະມວນຜົນ
    processMessage($senderId, $messageText);
}


// ==================================================================
// 5. ຟັງຊັນຕ່າງໆ (FUNCTIONS)
// ==================================================================

function processMessage($senderId, $text) {
    // A. ເຊື່ອມຕໍ່ຖານຂໍ້ມູນ
    try {
        $pdo = app_db_pdo();
    } catch (PDOException $e) {
        sendMessage($senderId, "❌ ເກີດຂໍ້ຜິດພາດໃນການເຊື່ອມຕໍ່ຖານຂໍ້ມູນ");
        // ບັນທຶກ Error ລົງ Log
        file_put_contents('log.txt', "DB ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
        return;
    }

    // B. ຄົ້ນຫາຂໍ້ມູນ
    $cleanSearch = str_replace([' ', '+'], '', $text);
    $sql = "SELECT * FROM game_packages 
            WHERE REPLACE(REPLACE(game_name, ' ', ''), '+', '') LIKE ? 
            ORDER BY game_name ASC, sort_order ASC, amount ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute(["%$cleanSearch%"]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // C. ຖ້າບໍ່ພົບຂໍ້ມູນ
    if (empty($results)) {
        // ສາມາດເປີດແຖວລຸ່ມນີ້ ຖ້າຢາກໃຫ້ບ໋ອດຕອບວ່າ "ບໍ່ພົບ"
        // sendMessage($senderId, "❌ ບໍ່ພົບຂໍ້ມູນເກມ: " . $text); 
        return; 
    }

    // D. ສ້າງຂໍ້ຄວາມ ແລະ ຕັດແບ່ງ (Chunk Logic)
    $msgQueue = []; // ກຽມແຖວລໍຖ້າສົ່ງ
    $currentMsg = "🏷️ ຜົນການຄົ້ນຫາ: {$text}\n➖➖➖➖➖➖➖➖\n";
    $lastGameName = "";

    foreach ($results as $row) {
        $line = "";
        
        // ໃສ່ຫົວຂໍ້ເກມ (ຖ້າປ່ຽນເກມໃໝ່)
        if ($lastGameName != $row['game_name']) {
            $lastGameName = $row['game_name'];
            $line .= "\n🎮 " . $lastGameName . "\n";
        }

        $name = !empty($row['custom_name']) ? $row['custom_name'] : $row['package_name'];
        
        // ຄຳນວນລາຄາ
        $price = number_format(ceil($row['amount'] / 1000) * 1000);
        
        // ຄຳນວນລາຄາບັດ (+60%)
        $rawCard = $row['amount'] + ($row['amount'] * 0.60);
        $priceCard = number_format(ceil($rawCard / 10000) * 10000);

        $line .= "💎 {$name} : {$price}₭ (💳 {$priceCard}₭)\n";

        // *** ຈຸດສຳຄັນ: ກວດສອບຄວາມຍາວ ***
        // ຖ້າຂໍ້ຄວາມປັດຈຸບັນ + ແຖວໃໝ່ ຍາວເກີນ 1800 ຕົວອັກສອນ
        if (mb_strlen($currentMsg . $line) > 1800) {
            $msgQueue[] = $currentMsg; // ເກັບຂໍ້ຄວາມເກົ່າເຂົ້າຄິວ
            $currentMsg = $line;       // ເລີ່ມຂໍ້ຄວາມໃໝ່ດ້ວຍແຖວນີ້
        } else {
            $currentMsg .= $line;      // ຖ້າບໍ່ເກີນ ກໍຕໍ່ທ້າຍໄປເລື້ອຍໆ
        }
    }
    
    // ເກັບຂໍ້ຄວາມສ່ວນທີ່ເຫຼືອ (ກ່ອງສຸດທ້າຍ)
    if (!empty($currentMsg)) {
        $msgQueue[] = $currentMsg;
    }

    // E. ວົນ Loop ສົ່ງຂໍ້ຄວາມທັງໝົດໃນຄິວ
    foreach ($msgQueue as $msg) {
        sendMessage($senderId, $msg);
    }
}

function sendMessage($recipientId, $messageText) {
    $fbCfg = app_cfg()['facebook'];
    $url = "https://graph.facebook.com/v16.0/me/messages?access_token=" . $fbCfg['page_access_token'];
    
    $data = [
        'recipient' => ['id' => $recipientId],
        'message' => ['text' => $messageText]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $result = curl_exec($ch);
    
    // ບັນທຶກຜົນການສົ່ງລົງ Log (ເພື່ອເຊັກວ່າສົ່ງຜ່ານບໍ່)
    if ($result) {
        file_put_contents('log.txt', "📤 ສົ່ງຂໍ້ຄວາມສຳເລັດ: " . substr($messageText, 0, 50) . "...\n", FILE_APPEND);
    } else {
        file_put_contents('log.txt', "❌ ສົ່ງຜິດພາດ: " . curl_error($ch) . "\n", FILE_APPEND);
    }
    
    curl_close($ch);
}
?>