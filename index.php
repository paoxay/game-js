<?php
require_once __DIR__ . '/config.php';
// 1. ເຊື່ອມຕໍ່ຖານຂໍ້ມູນ
try {
    $pdo = app_db_pdo();
} catch (PDOException $e) { die("DB Error: " . $e->getMessage()); }

// 2. ດຶງຂໍ້ມູນ
$sql = "SELECT * FROM game_packages ORDER BY game_name ASC, sort_order ASC, amount ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. ຈັດກຸ່ມ
$groupedGames = [];
foreach ($results as $row) {
    $gameName = $row['game_name'];
    $displayName = !empty($row['custom_name']) ? $row['custom_name'] : $row['package_name'];
    $row['display_name'] = $displayName;
    $groupedGames[$gameName][] = $row;
}
$totalGames = count($groupedGames);

// ⚙️ ຕັ້ງຄ່າເປີເຊັນ (ໃຫ້ຕົງກັບ get_prices.php)
$pricingCfg = app_cfg()['pricing'];
$transfer_percent = $pricingCfg['transfer_percent'];
$card_percent = $pricingCfg['card_percent'];
?>

<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Lao:wght@300;500;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Noto Sans Lao', sans-serif; background: #f4f6f9; }
        .card { border-radius: 12px; border:none; box-shadow: 0 4px 10px rgba(0,0,0,0.05); height: 100%; }
        .card:hover { transform: translateY(-5px); transition: 0.3s; }
        .preview-box { background: #2d3436; color: #fff; padding: 10px; border-radius: 6px; font-size: 12px; font-family: monospace; white-space: pre-wrap; max-height: 200px; overflow-y: auto; }
        .preview-label { font-size: 12px; font-weight: 700; color: #6c757d; margin-bottom: 6px; }
        .btn-circle { border-radius: 50px; }
        .modal-backdrop { z-index: 1040 !important; }
        .modal { z-index: 1050 !important; }
        .save-status { min-height: 20px; font-size: 13px; }
    </style>
</head>
<body>

    <nav class="navbar navbar-dark bg-primary sticky-top mb-4 shadow-sm">
        <div class="container d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <a class="navbar-brand fw-bold me-0" href="#"><i class="fas fa-tools"></i> ລະບົບຈັດການຊື່ສິນຄ້າ</a>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="auto_update.php" class="btn btn-warning btn-sm btn-circle px-3 fw-bold" target="_blank" onclick="return confirmSyncUpdate()">
                    <i class="fas fa-rotate"></i> ຊິ້ງລາຄາອັບເດດ
                </a>
                <a href="exports/price_knowledge.md" class="btn btn-light btn-sm btn-circle px-3 fw-bold" target="_blank">
                    <i class="fas fa-file-lines"></i> MD
                </a>
                <a href="exports/price_knowledge.txt" class="btn btn-light btn-sm btn-circle px-3 fw-bold" target="_blank">
                    <i class="fas fa-file-alt"></i> TXT
                </a>
                <a href="exports/price_knowledge.html" class="btn btn-light btn-sm btn-circle px-3 fw-bold" target="_blank">
                    <i class="fas fa-globe"></i> HTML
                </a>
                <span class="badge bg-white text-primary rounded-pill"><?php echo $totalGames; ?> ເກມ</span>
            </div>
        </div>
    </nav>

    <div class="container pb-5">
        <div class="row justify-content-center mb-4">
            <div class="col-md-6">
                <div class="input-group shadow-sm">
                    <span class="input-group-text bg-white border-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" id="search" class="form-control border-0 py-2" placeholder="ຄົ້ນຫາຊື່ເກມ..." onkeyup="filterGames()">
                </div>
            </div>
        </div>
        
        <div class="row g-3" id="gameGrid">
            <?php 
            $modalIndex = 0;
            foreach ($groupedGames as $gameName => $items): 
                $modalIndex++; 
                $modalID = "modal-edit-" . $modalIndex; 

                // --- ສ້າງ Preview Text ໃຫ້ຄືກັບ Bot ---
                $msgNormal = "";
                $msgCard = "";

                foreach($items as $item) {
                    // 1. ຄຳນວນລາຄາໂອນ (ຕາມ TRANSFER_PERCENT ແລະປັດເສດ 1000)
                    $rawTransferAmount = $item['amount'] + ($item['amount'] * ($transfer_percent / 100));
                    $roundedAmount = ceil($rawTransferAmount/1000)*1000;
                    $price = number_format($roundedAmount);
                    
                    // 2. ຄຳນວນລາຄາບັດ (ຕາມ CARD_PERCENT)
                    $rawCardAmount = $roundedAmount + ($roundedAmount * ($card_percent / 100));
                    
                    // 🔥 ແກ້ໄຂ: ປັດເສດລາຄາບັດໃຫ້ເຕັມ 1000 (ຄືກັບ get_prices.php)
                    $cardAmountRounded = ceil($rawCardAmount / 10000) * 10000;
                    $cardPrice = number_format($cardAmountRounded);

                    $msgNormal .= "💎 {$item['display_name']} : {$price}₭\n";
                    $msgCard .= "💎 {$item['display_name']} : {$cardPrice}₭\n";
                }

                $previewTextPart1 = "🏷️ ປະຈຸບັນ (ລາຄາໂອນ)\n🎮 {$gameName}\n{$msgNormal}";
                $previewTextPart2 = "💳 ລາຄາບັດເຕີມເງິນ\n🎮 {$gameName}\n{$msgCard}";
                
                // ສ້າງ Link API
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                $host = $_SERVER['HTTP_HOST'];
                $path = dirname($_SERVER['PHP_SELF']);
                $path = rtrim($path, '/');
                $apiLink = "$protocol://$host$path/get_prices.php?game=" . rawurlencode(trim($gameName));
            ?>
            
            <div class="col-md-6 col-lg-4 game-card" data-name="<?php echo strtolower($gameName); ?>">
                <div class="card">
                    <div class="card-header bg-white fw-bold text-primary d-flex justify-content-between align-items-center border-0 pt-3">
                        <span class="text-truncate pe-2"><?php echo $gameName; ?></span>
                        <button type="button" class="btn btn-sm btn-warning btn-circle px-3" 
                                data-bs-toggle="modal" 
                                data-bs-target="#<?php echo $modalID; ?>">
                            <i class="fas fa-edit"></i> ແກ້ໄຂ
                        </button>
                    </div>

                    <div class="card-body">
                        <p class="small text-muted mb-2 fw-bold">ຕົວຢ່າງຜົນຮັບ (Bot Preview):</p>
                        <div class="preview-label">ສ່ວນທີ 1: ລາຄາໂອນ</div>
                        <div class="preview-box mb-2"><?php echo $previewTextPart1; ?></div>
                        <div class="preview-label">ສ່ວນທີ 2: ລາຄາບັດ</div>
                        <div class="preview-box mb-3"><?php echo $previewTextPart2; ?></div>
                        
                        <div class="input-group input-group-sm mb-2">
                            <input type="text" class="form-control bg-light" value="<?php echo $apiLink; ?>" readonly>
                            <button class="btn btn-outline-secondary" onclick="copyLink('<?php echo $apiLink; ?>', this)">
                                <i class="far fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="<?php echo $modalID; ?>" tabindex="-1" aria-hidden="true" data-game-name="<?php echo htmlspecialchars($gameName, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header bg-light">
                                <h5 class="modal-title text-primary"><i class="fas fa-edit"></i> ແກ້ໄຂ: <?php echo $gameName; ?></h5>
                                <button type="button" class="btn btn-success btn-sm btn-circle px-3 me-2" onclick="saveAllNames('<?php echo $modalID; ?>')">
                                    <i class="fas fa-floppy-disk"></i> Save All
                                </button>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body bg-white">
                                <div class="save-status text-muted mb-2" id="status-<?php echo $modalID; ?>"></div>
                                <div class="table-responsive">
                                    <table class="table table-borderless align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th width="40%">ຊື່ເດີມ (Original)</th>
                                                <th width="45%">ຊື່ໃໝ່ (Custom Name)</th>
                                                <th width="15%" class="text-center">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($items as $pkg): ?>
                                            <tr class="border-bottom">
                                                <td class="small text-muted"><?php echo $pkg['package_name']; ?></td>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm" 
                                                        id="input-<?php echo $pkg['id']; ?>" 
                                                        data-package-id="<?php echo $pkg['id']; ?>"
                                                        value="<?php echo $pkg['custom_name']; ?>" 
                                                        placeholder="ຕົວຢ່າງ: 100 💎"
                                                        onkeydown="if(event.key === 'Enter') saveName(<?php echo $pkg['id']; ?>)">
                                                </td>
                                                <td class="text-center">
                                                    <button class="btn btn-sm btn-primary btn-circle save-single-btn" onclick="saveName(<?php echo $pkg['id']; ?>)">
                                                        <i class="fas fa-save"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-success btn-sm" onclick="saveAllNames('<?php echo $modalID; ?>')">Save All</button>
                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ປິດ</button>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmSyncUpdate() {
            return confirm('ຢືນຢັນການຊິ້ງລາຄາຈາກ API ຫຼືບໍ່?');
        }

        function filterGames() {
            let input = document.getElementById('search').value.toLowerCase();
            document.querySelectorAll('.game-card').forEach(c => {
                c.style.display = c.getAttribute('data-name').includes(input) ? '' : 'none';
            });
        }

        function copyLink(text, btn) {
            navigator.clipboard.writeText(text);
            let icon = btn.querySelector('i');
            icon.className = 'fas fa-check text-success';
            setTimeout(() => icon.className = 'far fa-copy', 1500);
        }

        function setStatus(modalId, message, type = 'muted') {
            const status = document.getElementById('status-' + modalId);
            if (!status) {
                return;
            }

            status.className = 'save-status mb-2 text-' + type;
            status.textContent = message;
        }

        function getModalIdFromElement(element) {
            const modal = element.closest('.modal');
            return modal ? modal.id : null;
        }

        function setButtonLoading(button, isLoading) {
            const icon = button.querySelector('i');
            if (!icon) {
                return;
            }

            if (isLoading) {
                button.disabled = true;
                icon.className = 'fas fa-spinner fa-spin';
                return;
            }

            button.disabled = false;
            icon.className = 'fas fa-save';
        }

        function markButtonSuccess(button) {
            const icon = button.querySelector('i');
            if (!icon) {
                return;
            }

            button.classList.remove('btn-primary');
            button.classList.add('btn-success');
            icon.className = 'fas fa-check';

            setTimeout(() => {
                button.classList.remove('btn-success');
                button.classList.add('btn-primary');
                icon.className = 'fas fa-save';
                button.disabled = false;
            }, 1200);
        }

        function saveName(id) {
            const input = document.getElementById('input-' + id);
            const val = input.value;
            const btn = input.parentElement.nextElementSibling.querySelector('button');
            const modalId = getModalIdFromElement(input);

            setButtonLoading(btn, true);
            setStatus(modalId, 'ກຳລັງບັນທຶກ...', 'muted');
            
            fetch('save_name.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${id}&custom_name=${encodeURIComponent(val)}`
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    markButtonSuccess(btn);
                    setStatus(modalId, 'ບັນທຶກສຳເລັດ', 'success');
                } else {
                    setButtonLoading(btn, false);
                    alert('ເກີດຂໍ້ຜິດພາດໃນການບັນທຶກ!');
                    setStatus(modalId, 'ບັນທຶກບໍ່ສຳເລັດ', 'danger');
                }
            })
            .catch(err => {
                setButtonLoading(btn, false);
                alert('Error: ' + err);
                setStatus(modalId, 'ບັນທຶກບໍ່ສຳເລັດ', 'danger');
            });
        }

        function saveAllNames(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) {
                return;
            }

            const inputs = Array.from(modal.querySelectorAll('input[data-package-id]'));
            const items = inputs.map(input => ({
                id: Number(input.dataset.packageId),
                custom_name: input.value
            }));

            const buttons = modal.querySelectorAll('.save-single-btn');
            buttons.forEach(button => setButtonLoading(button, true));
            setStatus(modalId, 'ກຳລັງບັນທຶກທັງໝົດ...', 'muted');

            fetch('save_name.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ items })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    buttons.forEach(button => markButtonSuccess(button));
                    setStatus(modalId, `ບັນທຶກສຳເລັດ ${data.updated || items.length} ລາຍການ`, 'success');
                } else {
                    buttons.forEach(button => setButtonLoading(button, false));
                    setStatus(modalId, 'ບັນທຶກທັງໝົດບໍ່ສຳເລັດ', 'danger');
                    alert('ເກີດຂໍ້ຜິດພາດໃນການບັນທຶກທັງໝົດ!');
                }
            })
            .catch(err => {
                buttons.forEach(button => setButtonLoading(button, false));
                setStatus(modalId, 'ບັນທຶກທັງໝົດບໍ່ສຳເລັດ', 'danger');
                alert('Error: ' + err);
            });
        }
    </script>
</body>
</html>