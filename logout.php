<?php
// logout.php - ล้าง session ทั้งหมดและกลับไปหน้า login
session_start();

unset($_SESSION["user_id"]);   // ลบตัวแปร session ทีละตัว
$_SESSION = [];
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

header("Location: login.php");
exit();
