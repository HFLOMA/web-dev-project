<?php
// API กดใช้งานไอเทมจากกระเป๋า (Streak Repair และ Streak Halving Insurance)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/connect.php';

// เช็คว่าล็อกอินหรือยัง
if (empty($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "message" => "กรุณาเข้าสู่ระบบก่อนใช้งาน"]);
    exit;
}

$user_id   = intval($_SESSION["user_id"]);
$item_type = trim($_POST["item_type"] ?? "");

if (empty($item_type)) {
    echo json_encode(["success" => false, "message" => "ไม่ได้ระบุประเภทไอเทม"]);
    exit;
}

try {
    // ดึงข้อมูลผู้ใช้ล่าสุดจากฐานข้อมูล
    $stmt = $pdo->prepare("SELECT * FROM paceup__users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(["success" => false, "message" => "ไม่พบข้อมูลผู้ใช้ในระบบ"]);
        exit;
    }

    $shield_qty     = intval($user['shield_balance'] ?? 0);
    $repair_qty     = intval($user['freeze_balance'] ?? 0);
    $insurance_qty  = intval($user['insurance_balance'] ?? 0);
    $lost_streak    = intval($user['lost_streak'] ?? 0);
    $streak_lost_at = $user['streak_lost_at'] ?? null;

    // -------------------------------------------------------------
    // กรณีที่ 1: กดใช้ไอเทม Streak Repair
    // -------------------------------------------------------------
    if ($item_type === "repair") {
        if ($repair_qty <= 0) {
            echo json_encode(["success" => false, "message" => "ไม่มีไอเทม Streak Repair ในกระเป๋า"]);
            exit;
        }

        if ($lost_streak <= 0) {
            echo json_encode(["success" => false, "message" => "ไม่มีสตรีคที่กู้คืนได้"]);
            exit;
        }

        // เช็คเงื่อนไขเวลา ต้องไม่เกิน 48 ชม.
        $is_within_48h = false;
        if (!empty($streak_lost_at)) {
            $time_diff = time() - strtotime($streak_lost_at);
            if ($time_diff >= 0 && $time_diff <= (48 * 3600)) {
                $is_within_48h = true;
            }
        }

        if (!$is_within_48h) {
            echo json_encode(["success" => false, "message" => "เกินเวลาที่กำหนด 48 ชม. แล้ว ไม่สามารถกู้คืนได้"]);
            exit;
        }

        // เริ่มทำธุรกรรมบันทึกลงฐานข้อมูล
        $pdo->beginTransaction();

        $recovered_streak = $lost_streak;

        // อัปเดตสตรีคกลับคืนมา ลดไอเทมลง 1 ชิ้น และเคลียร์ค่า lost_streak
        $update_sql = "UPDATE paceup__users
                       SET freeze_balance = freeze_balance - 1,
                           current_streak = ?,
                           lost_streak = 0,
                           streak_lost_at = NULL,
                           last_freeze_date = CURDATE() - INTERVAL 1 DAY
                       WHERE id = ?";   // นับว่าวันที่ขาดถูกซ่อมแล้ว ไม่งั้นหลุดซ้ำทันที
        $stmt_update = $pdo->prepare($update_sql);
        $stmt_update->execute([$recovered_streak, $user_id]);

        // บันทึกลงตารางประวัติการใช้ไอเทม (item_id = 2 คือ Repair)
        $log_sql = "INSERT INTO paceup__item_usages (user_id, item_id, usage_type, used_at) 
                    VALUES (?, 2, 'repair_streak', NOW())";
        $stmt_log = $pdo->prepare($log_sql);
        $stmt_log->execute([$user_id]);

        $pdo->commit();

        echo json_encode([
            "success" => true,
            "message" => "กู้คืนสตรีค $recovered_streak วัน สำเร็จเรียบร้อยแล้ว!"
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // กรณีที่ 2: กดใช้ไอเทม Streak Halving Insurance
    // -------------------------------------------------------------
    if ($item_type === "insurance") {
        if ($insurance_qty <= 0) {
            echo json_encode(["success" => false, "message" => "ไม่มีไอเทม Streak Halving Insurance ในกระเป๋า"]);
            exit;
        }

        if ($lost_streak < 40) {
            echo json_encode(["success" => false, "message" => "ใช้ได้เมื่อสตรีคหลุด 40 วันขึ้นไปเท่านั้น"]);
            exit;
        }

        // เริ่มทำธุรกรรมบันทึก
        $pdo->beginTransaction();

        // กู้คืนได้ครึ่งหนึ่ง ปัดเศษลง
        $recovered_streak = intval(floor($lost_streak / 2));

        // อัปเดตสตรีคคืนมาครึ่งหนึ่ง ลดไอเทมลง 1 ชิ้น
        $update_sql = "UPDATE paceup__users
                       SET insurance_balance = insurance_balance - 1,
                           current_streak = ?,
                           lost_streak = 0,
                           streak_lost_at = NULL,
                           last_freeze_date = CURDATE() - INTERVAL 1 DAY
                       WHERE id = ?";   // นับว่าวันที่ขาดถูกซ่อมแล้ว ไม่งั้นหลุดซ้ำทันที
        $stmt_update = $pdo->prepare($update_sql);
        $stmt_update->execute([$recovered_streak, $user_id]);

        // บันทึกลงตารางประวัติการใช้ไอเทม (item_id = 3 คือ Insurance)
        $log_sql = "INSERT INTO paceup__item_usages (user_id, item_id, usage_type, used_at) 
                    VALUES (?, 3, 'insurance_halving', NOW())";
        $stmt_log = $pdo->prepare($log_sql);
        $stmt_log->execute([$user_id]);

        $pdo->commit();

        echo json_encode([
            "success" => true,
            "message" => "ใช้ประกันลดทอนสตรีคสำเร็จ! กู้คืนสตรีคได้ $recovered_streak วัน"
        ]);
        exit;
    }

    echo json_encode(["success" => false, "message" => "ประเภทไอเทมไม่ถูกต้อง"]);
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(["success" => false, "message" => "เกิดข้อผิดพลาด: " . $e->getMessage()]);
    exit;
}

