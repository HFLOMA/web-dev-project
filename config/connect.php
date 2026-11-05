<?php
// 1. เริ่ม Session ก่อนเป็นลำดับแรกเพื่อป้องกัน Headers already sent error
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. เรียกไฟล์เชื่อมต่อฐานข้อมูล (ไฟล์นี้ไม่มีรหัสผ่าน ปลอดภัยสำหรับขึ้น GitHub)
require_once __DIR__ . "/../connect.php";

// 3. กำหนด Default Fetch Mode เมื่อตัวแปร $pdo ถูกสร้างสำเร็จ
if (isset($pdo) && $pdo instanceof PDO) {
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
}
?>