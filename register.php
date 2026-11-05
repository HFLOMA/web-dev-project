<?php
include "connect.php";

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['username'];
    $email = $_POST['email'];
    $pass = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $gender = $_POST['gender'];
    $birthdate = $_POST['dob'];
    $height = $_POST['height'];
    $weight = $_POST['weight'];
    $experience = $_POST['experience'];   // beginner / regular / serious ใช้คิดเป้าวิ่ง

    try {
        $stmt = $pdo->prepare("INSERT INTO paceup__users (name, email, pass, gender, birthdate, weight, height, experience) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $pass, $gender, $birthdate, $weight, $height, $experience]);
        
        header("Location: login.php");
        exit();

    } catch (PDOException $e) {
        // ตรวจสอบว่าเป็น Error อีเมลซ้ำในระบบหรือไม่
        if ($e->getCode() == 23000) {
            $error = "อีเมลนี้ถูกใช้งานไปแล้ว กรุณาใช้อีเมลอื่น";
        } else {
            $error = "เกิดข้อผิดพลาดในการสมัครสมาชิก: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background-color: #f4f7fe; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); width: 100%; max-width: 420px; }
        h3 { margin-top: 0; margin-bottom: 15px; color: #111; }
        .form-group { margin-bottom: 12px; }
        .form-row { display: flex; gap: 10px; }
        .form-row .form-group { flex: 1; }
        label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: #444; }
        input, select { width: 100%; padding: 9px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-size: 13px; }
        .calc-box { border: 1px solid #222; border-radius: 8px; padding: 12px; background: #fafafa; margin: 15px 0; font-size: 13px; }
        .calc-row { display: flex; justify-content: space-between; margin-bottom: 5px; font-weight: bold; }
        .btn { width: 100%; background: #181818; color: #fff; border: none; padding: 12px; border-radius: 6px; font-size: 14px; cursor: pointer; font-weight: bold; }
        .btn:hover { background: #333; }
        .hint { font-size: 10px; color: #771; margin-top: 2px; }
        /* จอใหญ่ 768px ขึ้นไป การ์ดมีที่ว่างมากขึ้น */
        @media (min-width: 768px) { .card { padding: 35px; } }
    </style>
    <script>
        function calculateBMI() {
            let height = parseFloat(document.getElementById('height').value) / 100;
            let weight = parseFloat(document.getElementById('weight').value);
            if (height > 0 && weight > 0) {
                let bmi = (weight / (height * height)).toFixed(1);
                let status = "ปกติ";
                if (bmi < 18.5) status = "ผอม";
                else if (bmi >= 23) status = "ท้วม/อ้วน";
                
                document.getElementById('bmiValue').innerText = bmi + " (" + status + ")";
            }
        }
        window.onload = calculateBMI;

        // JS เช็คก่อนส่ง: ตรวจสอบรหัสผ่าน 2 ช่องต้องตรงกัน (นำเงื่อนไขเช็คอายุออกแล้ว)
        function checkForm() {
            let pass = document.getElementById('password').value;
            let pass2 = document.getElementById('password2').value;
            let box = document.getElementById('formError');

            if (pass != pass2) {
                box.innerText = "รหัสผ่าน 2 ช่องไม่ตรงกัน";
                box.style.display = "block";
                return false;
            }
            return true;
        }
    </script>
</head>
<body>

<main class="card">
    <?php if (!empty($error)) : ?>
        <div style="color: #c62828; background: #ffebee; padding: 10px; border-radius: 6px; font-size: 13px; margin-bottom: 15px; text-align: center;">
            <?= $error ?>
        </div>
    <?php endif; ?>
    <h3>สมัครสมาชิก / แก้ไขโปรไฟล์</h3>
    <!-- กล่องแจ้ง error จาก JS ซ่อนไว้ก่อน -->
    <p id="formError" style="display: none; color: #c62828; font-size: 12px;"></p>
    <form method="POST" onsubmit="return checkForm()">
        <div class="form-group">
            <label>ชื่อผู้ใช้</label>
            <input type="text" name="username" required>
        </div>
        <div class="form-group">
            <label>อีเมล</label>
            <input type="email" name="email" 
                placeholder="you@email.com" 
                pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$" 
                title="กรุณากรอกรูปแบบอีเมลให้ถูกต้อง เช่น name@example.com" 
                required>
        </div>
        <div class="form-group">
            <label>รหัสผ่าน</label>
            <input type="password" id="password" name="password" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" required>
            <div class="hint">อย่างน้อย 8 ตัว มีตัวพิมพ์ใหญ่ เล็ก และตัวเลข</div>
        </div>
        <div class="form-group">
            <label>ยืนยันรหัสผ่าน</label>
            <input type="password" id="password2" required>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>เพศ</label>
                <select name="gender">
                    <option value="male">ชาย</option>
                    <option value="female">หญิง</option>
                </select>
            </div>
            <div class="form-group">
                <label>วันเกิด</label>
                <input type="date" id="dob" name="dob" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>ส่วนสูง (ซม.)</label>
                <input type="number" id="height" name="height" value="170" oninput="calculateBMI()" required>
            </div>
            <div class="form-group">
                <label>น้ำหนัก (กก.)</label>
                <input type="number" id="weight" name="weight" value="60" oninput="calculateBMI()" required>
            </div>
        </div>
        <div class="form-group">
            <label>ประสบการณ์วิ่ง</label>
            <select name="experience">
                <option value="beginner">มือใหม่</option>
                <option value="regular">วิ่งประจำ</option>
                <option value="serious">จริงจัง</option>
            </select>
        </div>

        <div class="calc-box">
            <div class="calc-row">
                <span>คำนวณ BMI </span>
                <span id="bmiValue">20.8</span>
            </div>
        </div>

        <button type="submit" class="btn">บันทึก</button>
    </form>
</main>

</body>
</html>