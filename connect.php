<?php
// กำหนดค่าการเชื่อมต่อของ TiDB Cloud (นำค่ามาจากหน้าต่าง Connect บน TiDB)
$host = "gateway01.ap-southeast-1.prod.aws.tidbcloud.com"; // แก้เป็น Host ของ TiDB คุณ
$port = "4000";                                           // พอร์ต TiDB คือ 4000
$db   = "test";                                           // ชื่อฐานข้อมูลที่คุณสร้างตารางไว้
$user = "3GyDNCxitUW82ds.root";                                  // Username จาก TiDB
$pass = "ranWn23WByCb6rKQ";                             // Password จาก TiDB
$charset = "utf8mb4";

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    // บังคับเปิดการเชื่อมต่อแบบปลอดภัย (SSL) ตามข้อกำหนดของ TiDB Cloud
    PDO::MYSQL_ATTR_SSL_CA       => true,
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("SET time_zone = '+07:00'");
} catch (PDOException $e) {
    die("เชื่อมต่อฐานข้อมูลไม่ได้: " . $e->getMessage());
}

date_default_timezone_set("Asia/Bangkok");
?>