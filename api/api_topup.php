<?php
// API เติมเงินจำลอง (สำหรับหน้า shop.php)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/connect.php';

// เช็คว่าล็อกอินหรือยัง
if (empty($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "message" => "กรุณาเข้าสู่ระบบก่อน"]);
    exit;
}

$user_id = intval($_SESSION["user_id"]);
$amount  = isset($_POST["amount"]) ? floatval($_POST["amount"]) : 0;

// ตรวจสอบจำนวนเหรียญที่กรอก (ต้องอยู่ระหว่าง 10 - 5,000)
if ($amount < 10 || $amount > 5000) {
    echo json_encode(["success" => false, "message" => "กรุณาระบุจำนวนเหรียญระหว่าง 10 ถึง 5,000 เหรียญ"]);
    exit;
}

try {
    // เริ่มทำธุรกรรม (Transaction)
    $pdo->beginTransaction();

    // 1. เพิ่มเหรียญให้กับผู้ใช้
    $stmt = $pdo->prepare("UPDATE paceup__users SET coin_balance = coin_balance + ? WHERE id = ?");
    $stmt->execute([$amount, $user_id]);

    // 2. บันทึกประวัติการเติมเงินลงตาราง paceup__orders (status = 'topup')
    $stmt = $pdo->prepare("INSERT INTO paceup__orders (user_id, total_price, status, created_at) VALUES (?, ?, 'topup', NOW())");
    $stmt->execute([$user_id, $amount]);

    // ยืนยันการบันทึก
    $pdo->commit();

    // ดึงยอดเงินคงเหลือล่าสุดมาส่งกลับไปหน้าเว็บ
    $stmt = $pdo->prepare("SELECT coin_balance FROM paceup__users WHERE id = ?");
    $stmt->execute([$user_id]);
    $new_coin = floatval($stmt->fetchColumn() ?: 0);

    echo json_encode([
        "success"      => true,
        "message"      => "เติมเงินสำเร็จ +" . number_format($amount, 0) . " เหรียญ",
        "added_amount" => $amount,
        "new_coin"     => $new_coin
    ]);

} catch (Exception $e) {
    // หากเกิดข้อผิดพลาดให้ยกเลิกการทำงาน
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        "success" => false,
        "message" => "เติมเงินไม่สำเร็จ: " . $e->getMessage()
    ]);
}
?>