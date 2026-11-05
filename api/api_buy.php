<?php
// API ซื้อไอเทม
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/connect.php';

// เช็ค session ล็อกอิน
if (empty($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "message" => "กรุณาเข้าสู่ระบบก่อน"]);
    exit;
}

$user_id = intval($_SESSION["user_id"]);
$item_id = intval($_POST["item_id"] ?? 0);
$qty     = intval($_POST["qty"] ?? 1);

if ($item_id <= 0 || $qty <= 0) {
    echo json_encode(["success" => false, "message" => "จำนวนสินค้าไม่ถูกต้อง"]);
    exit;
}

try {
    // ดึงข้อมูลไอเทม
    $stmt = $pdo->prepare("SELECT * FROM paceup__items WHERE id = ? AND is_active = 1");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch();

    if (!$item) {
        echo json_encode(["success" => false, "message" => "ไม่พบไอเทมนี้ในระบบ"]);
        exit;
    }

    $item_name = $item["name"];
    $price     = floatval($item["price"]);
    $limit     = intval($item["max_limit"] ?? 5);

    // ดึงข้อมูลผู้ใช้เพื่อเช็คเหรียญและจำนวนไอเทมเดิม
    $stmt = $pdo->prepare("SELECT * FROM paceup__users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(["success" => false, "message" => "ไม่พบข้อมูลผู้ใช้"]);
        exit;
    }

    // เช็คจำนวนไอเทมที่มีอยู่ในกระเป๋า
    $in_bag = 0;
    if ($item_id == 1) {
        $in_bag = intval($user["shield_balance"] ?? 0);
    } else if ($item_id == 2) {
        $in_bag = intval($user["freeze_balance"] ?? 0);
    } else if ($item_id == 3) {
        $in_bag = intval($user["insurance_balance"] ?? 0);
    }

    // ตรวจสอบ limit ในกระเป๋า
    if ($in_bag >= $limit) {
        echo json_encode(["success" => false, "message" => "ช่องเก็บของเต็ม: คุณมี $item_name ครบ $limit ชิ้นแล้ว"]);
        exit;
    }

    if ($in_bag + $qty > $limit) {
        $can_buy = $limit - $in_bag;
        echo json_encode(["success" => false, "message" => "สามารถซื้อ $item_name ได้อีกเพียง $can_buy ชิ้น"]);
        exit;
    }

    $total_price = $price * $qty;

    // โปรโมชั่น ซื้อชิ้นเดียวกัน 3 ชิ้นขึ้นไป ลด 10% เช่น 59 x 3 = 177 เหลือ 159.30
    if ($qty >= 3) {
        $total_price = $total_price * 0.9;
    }

    $coin = floatval($user["coin_balance"] ?? 0);

    // เช็คว่าเหรียญพอไหม
    if ($coin < $total_price) {
        echo json_encode(["success" => false, "message" => "เหรียญไม่พอซื้อไอเทมนี้"]);
        exit;
    }

    $pdo->beginTransaction();

    // ตัดเหรียญผู้ใช้
    $stmt = $pdo->prepare("UPDATE paceup__users SET coin_balance = coin_balance - ? WHERE id = ?");
    $stmt->execute([$total_price, $user_id]);

    // เพิ่มไอเทมตามประเภท
    if ($item_id == 1) {
        $pdo->prepare("UPDATE paceup__users SET shield_balance = shield_balance + ? WHERE id = ?")->execute([$qty, $user_id]);
    } else if ($item_id == 2) {
        $pdo->prepare("UPDATE paceup__users SET freeze_balance = freeze_balance + ? WHERE id = ?")->execute([$qty, $user_id]);
    } else if ($item_id == 3) {
        $pdo->prepare("UPDATE paceup__users SET insurance_balance = insurance_balance + ? WHERE id = ?")->execute([$qty, $user_id]);
    }

    // บันทึกคำสั่งซื้อ
    $stmt = $pdo->prepare("INSERT INTO paceup__orders (user_id, total_price, status, created_at) VALUES (?, ?, 'completed', NOW())");
    $stmt->execute([$user_id, $total_price]);
    $order_id = $pdo->lastInsertId();

    // บันทึกรายการสินค้าในคำสั่งซื้อ
    $stmt = $pdo->prepare("INSERT INTO paceup__order_items (order_id, item_id, qty) VALUES (?, ?, ?)");
    $stmt->execute([$order_id, $item_id, $qty]);

    $pdo->commit();

    $new_coin = $coin - $total_price;
    $new_inv  = $in_bag + $qty;

    echo json_encode([
        "success"       => true,
        "message"       => "สั่งซื้อสำเร็จเรียบร้อย",
        "item_name"     => $item_name,
        "qty"           => $qty,
        "total_price"   => $total_price,
        "new_coin"      => $new_coin,
        "new_shield"    => ($item_id == 1 ? $new_inv : intval($user["shield_balance"] ?? 0)),
        "new_repair"    => ($item_id == 2 ? $new_inv : intval($user["freeze_balance"] ?? 0)),
        "new_insurance" => ($item_id == 3 ? $new_inv : intval($user["insurance_balance"] ?? 0))
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(["success" => false, "message" => "เกิดข้อผิดพลาด: " . $e->getMessage()]);
}
?>