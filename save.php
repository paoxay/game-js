<?php
// ---------------------------------------------------------
// 1. ຕັ້ງຄ່າການເຊື່ອມຕໍ່ຖານຂໍ້ມູນ (Database Connection)
// ---------------------------------------------------------
$host = 'localhost';
$dbname = 'ppshop-js'; // ⚠️ ໃສ່ຊື່ DB ຂອງເຈົ້າ
$username = 'root';       // ⚠️ ໃສ່ User DB
$password = '';           // ⚠️ ໃສ່ Pass DB

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

// ---------------------------------------------------------
// 2. ຟັງຊັນຍິງ API (ໃຊ້ຊ້ຳໄດ້)
// ---------------------------------------------------------
function callAPI($url) {
    $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpZCI6IjY0NDYzNjVjNTJmMGZiMDU3YmU1ZDkxZCIsImltYWdlIjoiMmI0MWFjNjQtMzM2ZS00YmQwLWFmMjMtY2MxN2Y2Nzc1ODBkLnBuZyIsInVzZXJOYW1lIjoicGFveGFpMTk5NiIsImZ1bGxOYW1lIjoi4LuA4Lqb4Lq74LqyIOC7hOC6iuC6jeC6sOC6quC6suC6mSIsInJvbGUiOiJBRE1JTiIsImlhdCI6MTc2NDcxOTAxN30.X_YRHqog9VwtQKTX6Py3Oiv2Dh-9dTNkj4LhpoYNKtM'; // ⚠️ ຢ່າລືມອັບເດດ Token
    $encrypted = 'U2FsdGVkX1/Ey7TJrDxfjsnKiwtgAcinmtpZVeDYWubuMj7u5Z1SegOE02fq1x5j'; // ⚠️ ຢ່າລືມອັບເດດ X-Encrypted

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => array(
            'accept: application/json',
            'authorization: Bearer ' . $token,
            'x-encrypted: ' . $encrypted,
            'origin: https://admin.ppshope.com',
            'referer: https://admin.ppshope.com/'
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