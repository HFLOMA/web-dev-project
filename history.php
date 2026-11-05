<?php
require_once "config/connect.php";
require_once "functions.php";

// ยังไม่ login เด้งไปหน้า login
if (empty($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
$user_id = $_SESSION["user_id"];

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

$user_name    = $user["name"] ?? "ผู้ใช้งาน";
$coin_balance = floatval($user["coin_balance"] ?? 0);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติธุรกรรม &amp; สถิติ - PaceUp</title>
    <link rel="stylesheet" href="css/navbar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

    <?php include "header.php"; ?>

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
                    <h2 id="chartHeading">ยอดใช้จ่ายรายสัปดาห์</h2>
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
        <p>© 2026 PaceUp. Keep your streak alive.</p>
    </footer>

    <script src="assets/js/history.js"></script>
</body>
</html>
