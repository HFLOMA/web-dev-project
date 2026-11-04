<?php
require_once "config/connect.php";
require_once "functions.php";

$user_id = $_SESSION["user_id"] ?? 1;

// ดึงข้อมูลผู้ใช้
$stmt = $pdo->prepare("SELECT * FROM paceup__users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    $user = [
        "name" => "ผู้ใช้งาน",
        "coin_balance" => 150,
        "shield_balance" => 0,
        "freeze_balance" => 0,
        "insurance_balance" => 0,
        "current_streak" => 0
    ];
}

$coin_balance      = floatval($user["coin_balance"] ?? 0);
$shield_balance    = intval($user["shield_balance"] ?? 0);
$freeze_balance    = intval($user["freeze_balance"] ?? 0);
$insurance_balance = intval($user["insurance_balance"] ?? 0);
$current_streak    = intval($user["current_streak"] ?? 0);

// ดึงรายการสินค้าที่เปิดขาย
$stmt = $pdo->query("SELECT * FROM paceup__items WHERE is_active = 1 ORDER BY id ASC");
$items = $stmt->fetchAll();

// รูปภาพสินค้าแต่ละชิ้น
function getItemImage($name) {
    if (stripos($name, 'shield') !== false || stripos($name, 'โล่') !== false) {
        return 'img/streakShield.jpg';
    } else if (stripos($name, 'repair') !== false || stripos($name, 'ซ่อม') !== false) {
        return 'img/streakRepair.jpg';
    } else if (stripos($name, 'insurance') !== false || stripos($name, 'ประกัน') !== false) {
        return 'img/streakHavlvingInsurance.jpg';
    }
    return 'img/example.jpg';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ร้านค้าไอเทม - PaceUp</title>
    <link rel="stylesheet" href="css/navbar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

    <?php include "header.php"; ?>

    <main class="main-container">

        <div style="margin-bottom: 24px;">
            <h1>ร้านค้าไอเทมดิจิทัล</h1>
            <p>เลือกซื้อไอเทมสำหรับช่วยรักษาสถิติการวิ่งต่อเนื่อง หรือซ่อมแซมสถิติที่ขาดไป</p>
        </div>

        <!-- กล่องกระเป๋าเงินและไอเทมที่มี -->
        <section class="wallet-card-pc">
            <div class="wallet-details">
                <div>
                    <div class="wallet-col-title">ยอดเงินคงเหลือ</div>
                    <div class="wallet-col-amount" id="balanceText"><?= number_format($coin_balance, 0) ?> เหรียญ</div>
                </div>
                <div class="wallet-divider"></div>
                <div>
                    <div class="wallet-col-title">สถิติวิ่งต่อเนื่อง</div>
                    <div class="wallet-col-sub" style="color: #059669; font-weight: 700;">
                        <?= $current_streak ?> วัน
                    </div>
                </div>
                <div class="wallet-divider"></div>
                <div>
                    <div class="wallet-col-title">ไอเทมในกระเป๋า</div>
                    <div class="wallet-col-sub" style="font-size: 13px; line-height: 1.6;">
                        <span>Streak Shield: <strong id="balanceShieldText"><?= $shield_balance ?></strong>/<?= $items[0]['max_limit'] ?? 5 ?></span> · 
                        <span>Streak Repair: <strong id="balanceRepairText"><?= $freeze_balance ?></strong>/<?= $items[1]['max_limit'] ?? 3 ?></span> · 
                        <span>Streak Insurance: <strong id="balanceInsuranceText"><?= $insurance_balance ?></strong>/<?= $items[2]['max_limit'] ?? 2 ?></span>
                    </div>
                </div>
            </div>

            <div class="wallet-actions">
                <button type="button" class="btn-topup" onclick="openTopupModal()">
                    เติมเงิน (จำลอง)
                </button>
                <a href="history.php" class="btn-history-link">
                    ดูประวัติธุรกรรม
                </a>
            </div>
        </section>

        <!-- รายการสินค้า -->
        <section>
            <h2>รายการสินค้าพร้อมจำหน่าย</h2>
            <div class="items-grid-pc">
                <?php foreach ($items as $item): ?>
                    <?php 
                        $item_id   = intval($item['id']);
                        $item_name = $item['name'];
                        $price     = floatval($item['price']);
                        $limit     = intval($item['max_limit'] ?? 5);
                        $img       = getItemImage($item_name);

                        // เช็คจำนวนที่มีในกระเป๋า
                        $in_bag = 0;
                        if ($item_id == 1) {
                            $in_bag = $shield_balance;
                        } else if ($item_id == 2) {
                            $in_bag = $freeze_balance;
                        } else if ($item_id == 3) {
                            $in_bag = $insurance_balance;
                        }

                        $is_full = ($in_bag >= $limit);
                        $can_buy = max(0, $limit - $in_bag);
                    ?>
                    <article class="product-card-pc" id="product_<?= $item_id ?>">
                        <div class="product-header-pc">
                            <div class="product-icon-box">
                                <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($item_name) ?>" class="product-img">
                            </div>
                            <div style="flex: 1;">
                                <div class="product-title"><?= htmlspecialchars($item_name) ?></div>
                                <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 4px;">
                                    <span class="inventory-chip <?= $is_full ? 'chip-full' : '' ?>">
                                        🎒 ในกระเป๋า: <strong><?= $in_bag ?></strong>/<?= $limit ?> ชิ้น
                                    </span>
                                    <span class="product-badge">
                                        จำกัดพกพา <?= $limit ?> ชิ้น
                                    </span>
                                </div>
                            </div>
                        </div>

                        <?php if ($is_full): ?>
                            <div class="condition-alert alert-full">
                                <span>🎒</span>
                                <span>ช่องเก็บของเต็ม (มีครบ <?= $limit ?> ชิ้นแล้ว)</span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="product-desc">
                            <?= htmlspecialchars($item['description']) ?>
                        </div>

                        <div class="product-footer">
                            <div class="price-row">
                                <span class="price-label">ราคา</span>
                                <span class="price-val"><?= number_format($price, 0) ?> เหรียญ</span>
                            </div>

                            <div class="action-row">
                                <?php if ($is_full): ?>
                                    <div class="qty-stepper disabled">
                                        <button type="button" class="qty-btn" disabled>-</button>
                                        <input type="text" id="qty_<?= $item_id ?>" value="0" readonly>
                                        <button type="button" class="qty-btn" disabled>+</button>
                                    </div>

                                    <button type="button" class="btn-buy btn-disabled" disabled>
                                        🎒 ช่องเก็บของเต็ม
                                    </button>
                                <?php else: ?>
                                    <div class="qty-stepper">
                                        <button type="button" class="qty-btn" onclick="changeQty(<?= $item_id ?>, -1, <?= $can_buy ?>)">-</button>
                                        <input type="text" id="qty_<?= $item_id ?>" value="1" readonly>
                                        <button type="button" class="qty-btn" onclick="changeQty(<?= $item_id ?>, 1, <?= $can_buy ?>)">+</button>
                                    </div>

                                    <button type="button" class="btn-buy" onclick="openBuyModal(<?= $item_id ?>, '<?= htmlspecialchars($item_name) ?>', <?= $price ?>, '<?= htmlspecialchars($img) ?>')">
                                        ซื้อไอเทม
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

    </main>

    <footer>
        <p>© 2026 PaceUp. Keep your streak alive.</p>
    </footer>

    <!-- Modal ยืนยันการซื้อ -->
    <div id="modalConfirm" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3>ยืนยันการชำระเงิน</h3>
            </div>

            <div class="modal-row" style="align-items: center;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <img id="modalItemImg" src="" alt="" style="width: 44px; height: 44px; border-radius: 12px; object-fit: cover; border: 1px solid #e2e8f0; display: none;">
                    <span id="modalItemTitle">Streak Shield × 1</span>
                </div>
                <strong id="modalItemPrice">59 เหรียญ</strong>
            </div>

            <div class="modal-row">
                <span>คงเหลือหลังซื้อ</span>
                <strong id="modalRemainCoin">100 เหรียญ</strong>
            </div>

            <div id="coinWarningText" style="display: none; color: #dc2626; font-size: 13px; margin: 12px 0; text-align: center; font-weight: 600; background: #fef2f2; padding: 10px; border-radius: 8px;">
                เหรียญไม่เพียงพอสำหรับการซื้อ กรุณาเติมเงินก่อนทำรายการ
            </div>

            <div class="modal-note">
                สำเร็จ -> ตัดเงิน + เพิ่มไอเทมเข้ากระเป๋าทันที
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeBuyModal()">ยกเลิก</button>
                <button type="button" class="btn-confirm" id="btnConfirmBuy" onclick="submitPurchase()">ยืนยันการซื้อ</button>
            </div>
        </div>
    </div>

    <!-- Modal เติมเงินจำลอง -->
    <div id="modalTopup" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3>เติมเงินจำลอง</h3>
            </div>

            <form id="topupForm" onsubmit="event.preventDefault(); submitTopup();">
                <div style="margin-bottom: 16px;">
                    <label for="topupAmount" style="display:block; margin-bottom: 8px; font-weight: 600; font-size: 14px;">ระบุจำนวนเหรียญที่ต้องการเติม:</label>
                    <input type="number" id="topupAmount" name="amount" required min="10" max="5000" pattern="^[0-9]+$" placeholder="เช่น 100, 200, 500" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 10px;">
                </div>

                <div style="display: flex; gap: 8px; margin-bottom: 20px;">
                    <button type="button" class="btn-cancel" style="padding: 7px 12px; font-size: 13px;" onclick="setTopupPreset(100)">100</button>
                    <button type="button" class="btn-cancel" style="padding: 7px 12px; font-size: 13px;" onclick="setTopupPreset(200)">200</button>
                    <button type="button" class="btn-cancel" style="padding: 7px 12px; font-size: 13px;" onclick="setTopupPreset(500)">500</button>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeTopupModal()">ยกเลิก</button>
                    <button type="submit" class="btn-confirm">เติมเงินทันที</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        window.userCoinBalance = <?= json_encode($coin_balance) ?>;
    </script>
    <script src="assets/js/shop.js"></script>
</body>
</html>
