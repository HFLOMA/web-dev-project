<?php
// logout.php - ไฟล์ออกจากระบบ ล้าง Session และ Cookie ทั้งหมด
session_start();

// ลบตัวแปร session ที่ใช้ในระบบ
unset($_SESSION["user_id"]);
unset($_SESSION["username"]);
unset($_SESSION["fullname"]);
unset($_SESSION["role"]);

// ล้างข้อมูลทั้งหมดใน session และสั่งทำลาย session
session_unset();
session_destroy();

// ลบ Cookie ของ Session ออกจากเบราว์เซอร์
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// เด้งกลับไปยังหน้าร้านค้าเริ่มต้น
header("Location: shop.php");
exit;
?>
