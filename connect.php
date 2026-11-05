<?php

$host = "202.44.40.193";   
$port = "3306";            // พอร์ต MySQL มาตรฐาน
$db   = "169DB_15";        // ชื่อฐานข้อมูลบนเครื่องอาจารย์
$user = "169Muser15";      // Username ฐานข้อมูลอาจารย์[cite: 1]
$pass = "6s8ysrXj";        // Password ฐานข้อมูลอาจารย์
$charset = "utf8mb4";

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("SET time_zone = '+07:00'");
} catch (PDOException $e) {
    die("เชื่อมต่อฐานข้อมูลไม่ได้: " . $e->getMessage());
}

date_default_timezone_set("Asia/Bangkok");
?>