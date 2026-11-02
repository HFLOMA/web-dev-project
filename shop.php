<?php
require_once "config/connect.php";

$userId = intval($_SESSION["user_id"]);

// ดึงข้อมูลผู้ใช้
try {
    $stmtUser = $pdo->prepare("SELECT name, email, coin_balance, shield_balance, current_streak FROM paceup__users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $user = $stmtUser->fetch();
} catch (Throwable $e) {
    $stmtUser = $pdo->prepare("SELECT name, email, shield_balance, current_streak FROM paceup__users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $user = $stmtUser->fetch();
    if ($user) $user['coin_balance'] = 150.00;
}

$userName      = $user ? $user["name"] : "ผู้ใช้งาน";
$coinBalance   = $user ? floatval($user["coin_balance"] ?? 150.00) : 150.00;
$shieldBalance = $user ? intval($user["shield_balance"] ?? 0) : 0;
$currentStreak = $user ? intval($user["current_streak"] ?? 0) : 0;

// ดึงรายการไอเทม
$stmtItems = $pdo->query("SELECT id, name, description, price, max_limit, is_active FROM paceup__items WHERE is_active = 1 ORDER BY id ASC");
$items = $stmtItems->fetchAll();

// ไอคอนไอเทมแบบ SVG
function getItemIcon($name) {
    if (stripos($name, 'shield') !== false || stripos($name, 'โล่') !== false) {
        return '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>';
    } else if (stripos($name, 'repair') !== false || stripos($name, 'ซ่อม') !== false) {
        return '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>';
    } else if (stripos($name, 'insurance') !== false || stripos($name, 'ประกัน') !== false) {
        return '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 8h10M7 12h10M7 16h6"/></svg>';
    }
    return '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ร้านค้าไอเทม - PaceUp</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

    <!-- Navbar -->
    <header>
        <!-- <div class="pc-navbar">
            <a href="shop.php" class="brand-box">
                <div class="logo-badge">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                </div>
                <div class="brand-text">Pace<span>Up</span></div>
            </a>

            <nav class="nav-menu">
                <ul>
                    <li><a href="#">หน้าแรก</a></li>
                    <li><a href="#">เช็คอินวิ่ง</a></li>
                    <li><a href="#">เป้าหมาย</a></li>
                    <li><a href="#">จัดอันดับ</a></li>
                    <li class="active"><a href="shop.php">ร้านค้า</a></li>
                    <li><a href="history.php">ประวัติ &amp; สถิติ</a></li>
                </ul>
            </nav>

            <div class="user-status-bar">
                <div class="status-chip chip-streak">
                    สตรีค: <?=$currentStreak?> วัน
                </div>
                <div class="status-chip chip-shield">
                    โล่: <span id="userShield"><?=$shieldBalance?></span>
                </div>
                <div class="status-chip chip-coin">
                    เหรียญ: <span id="userCoin"><?=number_format($coinBalance, 0)?></span>
                </div>
                <div class="chip-user">
                    <div class="user-avatar-circle"><?=mb_substr($userName, 0, 1, 'UTF-8')?></div>
                    <span><?=$userName?> <small style="opacity: 0.6; font-size: 11px;">(ID: <?=$userId?>)</small></span>
                </div>
            </div>
        </div> -->
    </header>

    <main class="main-container">

        <div style="margin-bottom: 24px;">
            <h1>ร้านค้าไอเทมดิจิทัล</h1>
            <p>เลือกซื้อไอเทมสำหรับช่วยรักษาสถิติการวิ่งต่อเนื่อง หรือซ่อมแซมสถิติที่ขาดไป</p>
        </div>

        <!-- ยอดเงินในกระเป๋า -->
        <section class="wallet-card-pc">
            <div class="wallet-details">
                <div>
                    <div class="wallet-col-title">ยอดเงินคงเหลือ</div>
                    <div class="wallet-col-amount" id="balanceText"><?=number_format($coinBalance, 0)?> เหรียญ</div>
                </div>
                <div class="wallet-divider"></div>
                <div>
                    <div class="wallet-col-title">โล่ที่มีอยู่</div>
                    <div class="wallet-col-sub">
                        <span id="balanceShieldText"><?=$shieldBalance?></span> ชิ้น
                    </div>
                </div>
                <div class="wallet-divider"></div>
                <div>
                    <div class="wallet-col-title">สถิติวิ่งต่อเนื่อง</div>
                    <div class="wallet-col-sub" style="color: #059669;">
                        <?=$currentStreak?> วัน
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
                <article class="product-card-pc" id="product_<?=$item['id']?>">
                    <div class="product-header-pc">
                        <div class="product-icon-box">
                            <?=getItemIcon($item['name'])?>
                        </div>
                        <div>
                            <div class="product-title"><?=htmlspecialchars($item['name'])?></div>
                            <span class="product-badge">
                                จำกัด <?=$item['max_limit'] ?: 5?> ชิ้น/ครั้ง
                            </span>
                        </div>
                    </div>
                    
                    <div class="product-desc">
                        <?=htmlspecialchars($item['description'])?>
                    </div>

                    <div class="product-footer">
                        <div class="price-row">
                            <span class="price-label">ราคา</span>
                            <span class="price-val"><?=number_format($item['price'], 0)?> เหรียญ</span>
                        </div>

                        <div class="action-row">
                            <div class="qty-stepper">
                                <button type="button" class="qty-btn" onclick="changeQty(<?=$item['id']?>, -1)">-</button>
                                <input type="text" id="qty_<?=$item['id']?>" value="1" readonly>
                                <button type="button" class="qty-btn" onclick="changeQty(<?=$item['id']?>, 1, <?=$item['max_limit'] ?: 9?>)">+</button>
                            </div>

                            <button type="button" class="btn-buy" onclick="openBuyModal(<?=$item['id']?>, '<?=htmlspecialchars($item['name'])?>', <?=$item['price']?>)">
                                ซื้อไอเทม
                            </button>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </section>

    </main>

    <footer>
        <p>PaceUp - ระบบติดตามการวิ่งและร้านค้าไอเทม (Web Programming Project)</p>
    </footer>

    <!-- Modal ยืนยันการซื้อ -->
    <div id="modalConfirm" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3>ยืนยันการชำระเงิน</h3>
            </div>

            <div class="modal-row">
                <span id="modalItemTitle">Streak Shield × 1</span>
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
                สำเร็จ -> ตัดเงิน + สิทธิ์ไอเทมทันที
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
        window.userCoinBalance = <?=json_encode($coinBalance)?>;
    </script>
    <script src="assets/js/shop.js"></script>
</body>
</html>
