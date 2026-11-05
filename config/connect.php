<?php
// ใช้ connect.php ตัวเดียวกับหน้าอื่น (รหัส DB อยู่ในนั้น ไฟล์นี้ไม่มีรหัส ขึ้น github ได้)
require_once __DIR__ . "/../connect.php";

// fetch() ได้เป็นชื่อคอลัมน์อย่างเดียว เช่น $row["name"]
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
