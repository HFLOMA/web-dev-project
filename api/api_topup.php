<?php
// API เติมเงินจำลอง
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/connect.php';

error_reporting(0);
ini_set('display_errors', 0);

if (empty($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "message" => "กรุณาเข้าสู่ระบบก่อน"]);
    exit;
}

$userId = intval($_SESSION["user_id"]);
$amount = isset($_POST["amount"]) ? floatval($_POST["amount"]) : 0;

// ให้ตรงกับช่องในฟอร์ม min 10 max 5000
if ($amount < 10 || $amount > 5000) {
    echo json_encode(["success" => false, "message" => "กรุณาระบุจำนวนเหรียญระหว่าง 10 ถึง 5,000 เหรียญ"]);
    exit;
}

try {
    $pdo->beginTransaction();

    // ดึงยอดเหรียญปัจจุบัน
    $currentCoin = 150.00;
    try {
        $stmtUser = $pdo->prepare("SELECT coin_balance FROM paceup__users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $user = $stmtUser->fetch();
        if ($user && isset($user["coin_balance"]) && $user["coin_balance"] !== null) {
            $currentCoin = floatval($user["coin_balance"]);
        }
    } catch (Throwable $e) {}

    // เพิ่มเหรียญ
    try {
        $stmtUpdate = $pdo->prepare("UPDATE paceup__users SET coin_balance = COALESCE(coin_balance, 150.00) + ? WHERE id = ?");
        $stmtUpdate->execute([$amount, $userId]);
    } catch (Throwable $e) {}

    // บันทึก order เป็น topup
    try {
        $stmtOrder = $pdo->prepare("INSERT INTO paceup__orders (user_id, total_price, status, created_at) VALUES (?, ?, 'topup', NOW())");
        $stmtOrder->execute([$userId, $amount]);
    } catch (Throwable $eOrder) {
        $stmtMax = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM paceup__orders");
        $orderId = intval($stmtMax->fetchColumn());
        $stmtOrder = $pdo->prepare("INSERT INTO paceup__orders (id, user_id, total_price, status, created_at) VALUES (?, ?, ?, 'topup', NOW())");
        $stmtOrder->execute([$orderId, $userId, $amount]);
    }

    $pdo->commit();

    $newCoin = $currentCoin + $amount;
    try {
        $stmtLatest = $pdo->prepare("SELECT coin_balance FROM paceup__users WHERE id = ?");
        $stmtLatest->execute([$userId]);
        $latestUser = $stmtLatest->fetch();
        if ($latestUser && isset($latestUser["coin_balance"]) && $latestUser["coin_balance"] !== null) {
            $newCoin = floatval($latestUser["coin_balance"]);
        }
    } catch (Throwable $e) {}

    echo json_encode([
        "success"      => true,
        "message"      => "เติมเงินสำเร็จ +" . number_format($amount, 0) . " เหรียญ",
        "added_amount" => $amount,
        "new_coin"     => $newCoin
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        "success" => false,
        "message" => "เติมเงินไม่สำเร็จ: " . $e->getMessage()
    ]);
}
?>
