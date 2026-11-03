<?php
// ตัวอย่าง connect.php ก๊อปไฟล์นี้เป็น connect.php แล้วใส่รหัสผ่าน DB ของตัวเอง
// connect.php ตัวจริงไม่ขึ้น github เพราะมีรหัสผ่าน

try {
    $pdo = new PDO("mysql:host=localhost;dbname=169DB_15;charset=utf8mb4", "169Muser15", "ใส่รหัสผ่านตรงนี้");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET time_zone = '+07:00'");
} catch (PDOException $e) {
    echo "เชื่อมฐานข้อมูลไม่ได้: " . $e->getMessage();
    exit;
}

date_default_timezone_set("Asia/Bangkok");
?>
