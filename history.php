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
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติธุรกรรม &amp; สถิติ - PaceUp</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <li><a href="shop.php">ร้านค้า</a></li>
                    <li class="active"><a href="history.php">ประวัติ &amp; สถิติ</a></li>
                </ul>
            </nav>

            <div class="user-status-bar">
                <div class="status-chip chip-streak">
                    สตรีค: <?=$currentStreak?> วัน
                </div>
                <div class="status-chip chip-shield">
                    โล่: <span><?=$shieldBalance?></span>
                </div>
                <div class="status-chip chip-coin">
                    เหรียญ: <span><?=number_format($coinBalance, 0)?></span>
                </div>
                <div class="chip-user">
                    <div class="user-avatar-circle"><?=mb_substr($userName, 0, 1, 'UTF-8')?></div>
                    <span><?=$userName?> <small style="opacity: 0.6; font-size: 11px;">(ID: <?=$userId?>)</small></span>
                </div>
            </div>
        </div> -->
    </header>

    <main class="main-container">

        <div class="page-top-bar">
            <div>
                <h1>ประวัติธุรกรรม &amp; สถิติเชิงลึก</h1>
                <p>สรุปยอดค่าใช้จ่าย สินค้าขายดี และกราฟแท่งแนวโน้มรายสัปดาห์</p>
            </div>
            <a href="shop.php" class="btn-history-link">
                &lt; กลับหน้าร้านค้า
            </a>
        </div>

        <!-- ตัวกรองข้อมูล -->
        <section class="filter-bar">
            <div class="filter-title">
                <span>ตัวกรอง:</span>
            </div>
            
            <div class="filter-selects">
                <select id="filterPeriod" class="select-input" onchange="loadHistoryData(1)">
                    <option value="all" selected>ทั้งหมด</option>
                    <option value="30">30 วันล่าสุด</option>
                    <option value="7">7 วันล่าสุด</option>
                </select>

                <select id="filterType" class="select-input" onchange="loadHistoryData(1)">
                    <option value="all" selected>ทุกประเภท</option>
                    <option value="buy">เฉพาะซื้อไอเทม</option>
                    <option value="topup">เฉพาะเติมเงิน</option>
                </select>
            </div>
        </section>

        <!-- สรุปสถิติ 4 กล่อง -->
        <section class="kpi-row">
            <div class="kpi-box">
                <div class="kpi-title">ใช้จ่ายรวม</div>
                <div class="kpi-val" id="kpiSpent">- เหรียญ</div>
            </div>

            <div class="kpi-box">
                <div class="kpi-title">ซื้อไปทั้งหมด</div>
                <div class="kpi-val" id="kpiItems">- ชิ้น</div>
            </div>

            <div class="kpi-box">
                <div class="kpi-title">เติมเงินรวม</div>
                <div class="kpi-val" id="kpiTopup">- เหรียญ</div>
            </div>

            <div class="kpi-box">
                <div class="kpi-title">ไอเทมขายดีสุด</div>
                <div class="kpi-val" id="kpiBestSeller" style="font-size: 18px;">-</div>
            </div>
        </section>

        <!-- กราฟ + ตาราง -->
        <section class="dashboard-grid">
            
            <div class="panel-card">
                <div class="panel-head">
                    <h2>ยอดใช้จ่ายรายสัปดาห์</h2>
                    <span style="font-size: 12px; color: #94a3b8; font-weight: 600;">(กราฟแท่ง Chart.js)</span>
                </div>
                <div style="height: 300px; position: relative;">
                    <canvas id="expenseChart"></canvas>
                </div>
            </div>

            <div class="panel-card">
                <div class="panel-head">
                    <h2>รายการคำสั่งซื้อล่าสุด</h2>
                    <span id="totalRecordsText" style="font-size: 13px; color: #64748b; font-weight: 600;"></span>
                </div>
                
                <table class="tx-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">วันที่</th>
                            <th>รายการ</th>
                            <th style="text-align: right; width: 110px;">ยอด (เหรียญ)</th>
                        </tr>
                    </thead>
                    <tbody id="transactionBody">
                        <tr>
                            <td colspan="3" style="text-align: center; color: #94a3b8; padding: 30px;">
                                กำลังโหลดข้อมูล...
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="pagination-wrap" id="paginationControls"></div>
            </div>

        </section>

    </main>

    <footer>
        <p>PaceUp - ระบบติดตามการวิ่งและร้านค้าไอเทม (Web Programming Project)</p>
    </footer>

    <script src="assets/js/history.js"></script>
</body>
</html>
