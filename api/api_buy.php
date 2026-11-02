<?php
// API ซื้อไอเทม
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/connect.php';

error_reporting(0);
ini_set('display_errors', 0);

if (empty($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "message" => "กรุณาเข้าสู่ระบบก่อน"]);
    exit;
}

$userId = intval($_SESSION["user_id"]);
$itemId = isset($_POST["item_id"]) ? intval($_POST["item_id"]) : 0;
$qty    = isset($_POST["qty"]) ? intval($_POST["qty"]) : 1;

if ($itemId <= 0 || $qty <= 0) {
    echo json_encode(["success" => false, "message" => "จำนวนสินค้าไม่ถูกต้อง"]);
    exit;
}

try {
    // ดึงข้อมูลไอเทม
    $stmtItem = $pdo->prepare("SELECT id, name, description, price, max_limit, is_active FROM paceup__items WHERE id = ? AND is_active = 1");
    $stmtItem->execute([$itemId]);
    $item = $stmtItem->fetch();

    if (!$item) {
        echo json_encode(["success" => false, "message" => "ไม่พบไอเทมนี้ในระบบ"]);
        exit;
    }

    if (!empty($item["max_limit"]) && $qty > $item["max_limit"]) {
        echo json_encode(["success" => false, "message" => "ซื้อได้ไม่เกินครั้งละ " . $item["max_limit"] . " ชิ้น"]);
        exit;
    }

    $totalPrice = floatval($item["price"]) * $qty;

    // ดึงข้อมูลผู้ใช้
    $stmtUser = $pdo->prepare("SELECT id, name, shield_balance, freeze_balance, current_streak FROM paceup__users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $user = $stmtUser->fetch();

    if (!$user) {
        echo json_encode(["success" => false, "message" => "ไม่พบข้อมูลผู้ใช้"]);
        exit;
    }

    // ตรวจสอบยอดเงิน
    $currentCoin = 150.00;
    $hasCoinCol = false;
    try {
        $stmtCoin = $pdo->prepare("SELECT coin_balance FROM paceup__users WHERE id = ?");
        $stmtCoin->execute([$userId]);
        $coinRow = $stmtCoin->fetch();
        $hasCoinCol = true;

        if ($coinRow && $coinRow["coin_balance"] !== null) {
            $currentCoin = floatval($coinRow["coin_balance"]);
        } else {
            $pdo->prepare("UPDATE paceup__users SET coin_balance = 150.00 WHERE id = ?")->execute([$userId]);
            $currentCoin = 150.00;
        }
    } catch (Throwable $eCol) {
        try {
            $pdo->exec("ALTER TABLE paceup__users ADD COLUMN coin_balance DECIMAL(10,2) NOT NULL DEFAULT 150.00");
            $pdo->prepare("UPDATE paceup__users SET coin_balance = 150.00 WHERE id = ?")->execute([$userId]);
            $hasCoinCol = true;
            $currentCoin = 150.00;
        } catch (Throwable $eAlter) {
            $hasCoinCol = false;
        }
    }

    // เช็คเงินพอซื้อไหม
    if ($currentCoin < $totalPrice) {
        echo json_encode([
            "success" => false,
            "message" => "เหรียญไม่พอซื้อ (มี " . number_format($currentCoin, 0) . " เหรียญ แต่ต้องใช้ " . number_format($totalPrice, 0) . " เหรียญ)"
        ]);
        exit;
    }

    $pdo->beginTransaction();

    // ตัดเงิน
    if ($hasCoinCol) {
        $stmtDeduct = $pdo->prepare("UPDATE paceup__users SET coin_balance = COALESCE(coin_balance, 150.00) - ? WHERE id = ?");
        $stmtDeduct->execute([$totalPrice, $userId]);
    }

    // เพิ่มสิทธิ์ไอเทม
    $itemName = $item["name"];
    if (stripos($itemName, 'shield') !== false || stripos($itemName, 'โล่') !== false) {
        $shieldAdd = 1 * $qty;
        $stmtShield = $pdo->prepare("UPDATE paceup__users SET shield_balance = COALESCE(shield_balance, 0) + ? WHERE id = ?");
        $stmtShield->execute([$shieldAdd, $userId]);
    } else if (stripos($itemName, 'repair') !== false || stripos($itemName, 'ซ่อม') !== false) {
        $stmtRepair = $pdo->prepare("UPDATE paceup__users SET current_streak = COALESCE(current_streak, 0) + ? WHERE id = ?");
        $stmtRepair->execute([$qty, $userId]);

        try {
            $stmtUsage = $pdo->prepare("INSERT INTO paceup__item_usages (user_id, item_id, usage_type, used_at) VALUES (?, ?, 'repair', NOW())");
            $stmtUsage->execute([$userId, $itemId]);
        } catch (Throwable $eU) {}
    } else if (stripos($itemName, 'insurance') !== false || stripos($itemName, 'ประกัน') !== false) {
        $stmtIns = $pdo->prepare("UPDATE paceup__users SET freeze_balance = COALESCE(freeze_balance, 0) + ? WHERE id = ?");
        $stmtIns->execute([$qty, $userId]);

        try {
            $stmtUsage = $pdo->prepare("INSERT INTO paceup__item_usages (user_id, item_id, usage_type, used_at) VALUES (?, ?, 'insurance', NOW())");
            $stmtUsage->execute([$userId, $itemId]);
        } catch (Throwable $eU) {}
    }

    // บันทึก order
    $orderId = 0;
    try {
        $stmtOrder = $pdo->prepare("INSERT INTO paceup__orders (user_id, total_price, status, created_at) VALUES (?, ?, 'completed', NOW())");
        $stmtOrder->execute([$userId, $totalPrice]);
        $orderId = $pdo->lastInsertId();
    } catch (Throwable $eOrder) {
        $stmtMax = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM paceup__orders");
        $orderId = intval($stmtMax->fetchColumn());
        $stmtOrder = $pdo->prepare("INSERT INTO paceup__orders (id, user_id, total_price, status, created_at) VALUES (?, ?, ?, 'completed', NOW())");
        $stmtOrder->execute([$orderId, $userId, $totalPrice]);
    }

    if (!$orderId || intval($orderId) === 0) {
        $stmtLast = $pdo->prepare("SELECT id FROM paceup__orders WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        $stmtLast->execute([$userId]);
        $orderId = $stmtLast->fetchColumn();
    }

    // บันทึก order_items
    if ($orderId) {
        try {
            $stmtOrderItem = $pdo->prepare("INSERT INTO paceup__order_items (order_id, item_id, qty) VALUES (?, ?, ?)");
            $stmtOrderItem->execute([$orderId, $itemId, $qty]);
        } catch (Throwable $eOi) {
            $stmtMaxOi = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM paceup__order_items");
            $nextOiId = intval($stmtMaxOi->fetchColumn());
            $stmtOrderItem = $pdo->prepare("INSERT INTO paceup__order_items (id, order_id, item_id, qty) VALUES (?, ?, ?, ?)");
            $stmtOrderItem->execute([$nextOiId, $orderId, $itemId, $qty]);
        }
    }

    $pdo->commit();

    // ดึงยอดเงินล่าสุด
    $latestCoin = $currentCoin - $totalPrice;
    $latestShield = intval($user["shield_balance"]);

    try {
        $stmtLatest = $pdo->prepare("SELECT coin_balance, shield_balance FROM paceup__users WHERE id = ?");
        $stmtLatest->execute([$userId]);
        $latestUser = $stmtLatest->fetch();
        if ($latestUser) {
            if ($latestUser["coin_balance"] !== null) {
                $latestCoin = floatval($latestUser["coin_balance"]);
            }
            $latestShield = intval($latestUser["shield_balance"]);
        }
    } catch (Throwable $e) {}

    echo json_encode([
        "success"     => true,
        "message"     => "สั่งซื้อสำเร็จเรียบร้อย",
        "item_name"   => $item["name"],
        "qty"         => $qty,
        "total_price" => $totalPrice,
        "user_id"     => $userId,
        "user_name"   => $user["name"],
        "new_coin"    => $latestCoin,
        "new_shield"  => $latestShield
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        "success" => false,
        "message" => "เกิดข้อผิดพลาด: " . $e->getMessage()
    ]);
}
?>
