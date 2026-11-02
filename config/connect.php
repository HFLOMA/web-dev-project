<?php
// เชื่อมต่อฐานข้อมูล MySQL
$db_host = "localhost";
$db_name = "169DB_15";
$db_user = "169Muser15";
$db_pass = "6s8ysrXj";

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // ตรวจสอบคอลัมน์เหรียญ
    try {
        $pdo->query("SELECT coin_balance FROM paceup__users LIMIT 1");
        $pdo->exec("UPDATE paceup__users SET coin_balance = 150.00 WHERE coin_balance IS NULL");
    } catch (Throwable $ex) {
        try {
            $pdo->exec("ALTER TABLE paceup__users ADD COLUMN coin_balance DECIMAL(10,2) NOT NULL DEFAULT 150.00");
            $pdo->exec("UPDATE paceup__users SET coin_balance = 150.00 WHERE coin_balance IS NULL");
        } catch (Throwable $alterEx) {}
    }

} catch (PDOException $e) {
    die("เชื่อมต่อฐานข้อมูล $db_name ไม่ได้: " . $e->getMessage());
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// สลับ user ทดสอบ
if (isset($_GET["switch_user"])) {
    $swId = intval($_GET["switch_user"]);
    $checkSw = $pdo->prepare("SELECT id, name, email FROM paceup__users WHERE id = ?");
    $checkSw->execute([$swId]);
    $swUser = $checkSw->fetch();
    if ($swUser) {
        $_SESSION["user_id"] = intval($swUser["id"]);
        $_SESSION["username"] = $swUser["email"];
        $_SESSION["fullname"] = $swUser["name"];
        $_SESSION["role"] = "user";
    }
}

// รีเซ็ตเหรียญ
if (isset($_GET["reset_coin"])) {
    $targetRId = isset($_SESSION["user_id"]) ? intval($_SESSION["user_id"]) : 2;
    try {
        $pdo->prepare("UPDATE paceup__users SET coin_balance = 150.00 WHERE id = ?")->execute([$targetRId]);
    } catch (Throwable $eR) {}
}

// เช็ค user ใน session
try {
    $targetUserId = isset($_SESSION["user_id"]) ? intval($_SESSION["user_id"]) : 0;
    $stmtCheck = $pdo->prepare("SELECT id, name, email FROM paceup__users WHERE id = ?");
    $stmtCheck->execute([$targetUserId]);
    $existingUser = $stmtCheck->fetch();

    if (!$existingUser) {
        $orderUser = $pdo->query("SELECT u.id, u.name, u.email 
            FROM paceup__users u 
            JOIN paceup__orders o ON u.id = o.user_id 
            ORDER BY u.id DESC LIMIT 1")->fetch();

        if ($orderUser) {
            $firstUser = $orderUser;
        } else {
            $firstUser = $pdo->query("SELECT id, name, email FROM paceup__users ORDER BY id ASC LIMIT 1")->fetch();
        }

        if ($firstUser) {
            $_SESSION["user_id"] = intval($firstUser["id"]);
            $_SESSION["username"] = $firstUser["email"];
            $_SESSION["fullname"] = $firstUser["name"];
            $_SESSION["role"] = "user";
        } else {
            $_SESSION["user_id"] = 1;
        }
    }
} catch (Throwable $e) {
    if (empty($_SESSION["user_id"])) {
        $_SESSION["user_id"] = 1;
    }
}
?>
