<?php
// เริ่มเก็บ session 
session_start();
include "connect.php";
include "functions.php";

$user_id = $_SESSION["user_id"] ?? 1;   


// ส่งผลกลับเป็น JSON แล้วจบเลย
function reply($status, $message)
{
    echo json_encode(["status" => $status, "message" => $message], JSON_UNESCAPED_UNICODE);
    exit;
}


// ขั้นตอนการตรวจ 
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header("Content-Type: application/json; charset=utf-8");

    // 1) ตรวจรูป ต้องมีไฟล์ เป็น JPG หรือ PNG 
    if (!isset($_FILES["photo"]) || $_FILES["photo"]["error"] != 0) {
        reply("rejected", "ยังไม่ได้เลือกรูป");
    }
    $photo = $_FILES["photo"];
    $type  = mime_content_type($photo["tmp_name"]);
    if ($type != "image/jpeg" && $type != "image/png") {
        reply("rejected", "รองรับเฉพาะไฟล์ JPG หรือ PNG");
    }
    if ($photo["size"] > 1024 * 1024) {
        reply("rejected", "ไฟล์ใหญ่เกิน 1MB");
    }

    //ตรวจระยะทางและเวลาที่กรอก
    $distance = (float) $_POST["distance"];
    $duration = (int) $_POST["minutes"] * 60;   // แปลงนาทีเป็นวินาที
    if ($distance <= 0 || $distance > 100 || $duration <= 0) {
        reply("pending", "กรอกระยะทาง (0.1–100 กม.) และเวลาให้ถูกต้อง");
    }

    // เช็คระยะที่กรอกกับในรูป ผลสแกนส่งมาเป็น JSON เช่น {"km":6.51,"minutes":46,"time":"45:43"}
    $scan = json_decode($_POST["scan"] ?? "", true);   // แปลง JSON เป็น array
    $ocr_distance = (float) ($scan["km"] ?? 0);
    if ($ocr_distance <= 0) {
        reply("rejected", "อ่านระยะทางจากรูปไม่ได้ ใช้ภาพสรุปผลวิ่งจาก Garmin Connect แบบตัวอย่าง");
    }
    if (abs($distance - $ocr_distance) > 0.1) {
        reply("rejected", "ระยะที่กรอก ($distance กม.) ไม่ตรงกับในรูป ($ocr_distance กม.)");
    }

    // ตรวจ hash รูปไม่ซํ้า
    $hash = hash_file("sha256", $photo["tmp_name"]);
    $stmt = $pdo->prepare("SELECT id FROM paceup__check_ins WHERE image_hash = ?");
    $stmt->bindParam(1, $hash);
    $stmt->execute();
    if ($stmt->fetch()) {
        reply("rejected", "รูปนี้เคยถูกใช้เช็คอินแล้ว");
    }

    //1วันเช็คอินได้1ครั้ง
    $today = date("Y-m-d");
    $stmt = $pdo->prepare("SELECT id FROM paceup__check_ins WHERE user_id = ? AND DATE(created_at) = ? AND status <> 'rejected'");
    $stmt->bindParam(1, $user_id);
    $stmt->bindParam(2, $today);
    $stmt->execute();
    if ($stmt->fetch()) {
        reply("pending", "วันนี้คุณเช็คอินไปแล้ว พรุ่งนี้มาใหม่นะ");
    }

    //เก็บรูปในโฟลเดอร์ uploads
    $extension = ($type == "image/png") ? "png" : "jpg";
    $image_url = "uploads/$hash.$extension";
    move_uploaded_file($photo["tmp_name"], $image_url);

    // บันทึกเช็คอิน
    $stmt = $pdo->prepare("INSERT INTO paceup__check_ins (user_id, image_url, image_hash, ocr_distance, user_input_distance, duration, status)
                           VALUES (?, ?, ?, ?, ?, ?, 'approved')");
    $stmt->bindParam(1, $user_id);
    $stmt->bindParam(2, $image_url);
    $stmt->bindParam(3, $hash);
    $stmt->bindParam(4, $ocr_distance);   // ระยะจากรูป
    $stmt->bindParam(5, $distance);       // ระยะที่ user กรอก
    $stmt->bindParam(6, $duration);
    $stmt->execute();

    // อัปเดต streak
    check_streak($pdo, $user_id);
    $user      = get_user($pdo, $user_id);
    $yesterday = date("Y-m-d", strtotime("yesterday"));
    $last      = max($user["last_checkin_date"], $user["last_freeze_date"]);

    if ($user["current_streak"] > 0 && $last == $yesterday) {
        $streak = $user["current_streak"] + 1;
    } else {
        $streak = 1;
    }

    $stmt = $pdo->prepare("UPDATE paceup__users SET current_streak = ?, last_checkin_date = ? WHERE id = ?");
    $stmt->bindParam(1, $streak);
    $stmt->bindParam(2, $today);
    $stmt->bindParam(3, $user_id);
    $stmt->execute();

    reply("approved", "เช็คอินสำเร็จ " . number_format($distance, 2) . " กม. · Streak $streak วัน");
}


$user = get_user($pdo, $user_id);
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PaceUp — เช็คอิน</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/home.css">
</head>
<body>

<?php include "header.php"; ?>

<main class="container">
  <a href="index.php" class="link">‹ หน้าหลัก</a>
  <h1 class="mt">เช็คอินวันนี้</h1>
  <p class="muted">อัปรูปสรุปผลวิ่งจาก Garmin Connect ระบบจะอ่านระยะทางกับเวลาให้</p>

  <div class="grid cols-2 mt">

    <!-- ฟอร์มเช็คอิน -->
    <form class="card" id="checkin-form">

      <!-- กดที่กรอบเพื่อเลือกรูป label ผูกกับ input ด้วย for="photo" -->
      <label class="upload" for="photo">
        <span id="upload-text">แตะเพื่อเลือกรูปผลวิ่ง<br>JPG / PNG ไม่เกิน 1MB</span>
        <img id="preview" alt="" hidden>
      </label>
      <input type="file" id="photo" name="photo" accept="image/png, image/jpeg" hidden>

      <div class="fields mt">
        <label class="field">
          ระยะทาง (กม.)
          <input type="number" name="distance" step="0.01" min="0.1" max="100" placeholder="5.00" required>
        </label>
        <label class="field">
          เวลา (นาที)
          <input type="number" name="minutes" min="1" placeholder="30" required>
        </label>
      </div>

      <!-- ช่องซ่อน เก็บผลสแกนเป็น JSON ส่งไปให้ php เช็ค -->
      <input type="hidden" name="scan" id="scan">

      <!-- กดได้หลังอ่านรูปเสร็จ -->
      <button class="btn full mt" id="send-btn" disabled>ส่งเช็คอิน</button>

      <!-- แสดงผลเช็คอิน -->
      <div id="result" class="mt"></div>
    </form>


    <!-- รูปตัวอย่าง กับ กติกา -->
    <section class="card">
      <h3>ตัวอย่างรูปที่ใช้ได้</h3>
      <img class="example-img" src="img/example.jpg" alt="ตัวอย่างภาพสรุปผลวิ่งจาก Garmin Connect">
      <p class="example-caption">ภาพสรุปผลวิ่งจากแอป Garmin Connect</p>
      <ul class="rules mt">
        <li>ใช้ได้เฉพาะภาพสรุปผลวิ่งจาก Garmin Connect แบบตัวอย่าง</li>
        <li>ระบบอ่านระยะทางและเวลาจากรูปให้อัตโนมัติ</li>
        <li>ระยะที่กรอกต้องตรงกับในรูป (ต่างได้ไม่เกิน 0.1 กม.)</li>
        <li>เช็คอินได้วันละ 1 ครั้ง</li>
        <li>รูปเดิมใช้ซ้ำไม่ได้</li>
      </ul>
    </section>

  </div>
</main>


<!-- Tesseract.js ตัวอ่านตัวหนังสือในรูป โหลดจากเน็ต ไม่ต้องใช้ key -->
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract.min.js"></script>
<!-- scan.js หาระยะทางกับเวลาในรูป Garmin -->
<script src="js/scan.js"></script>

<script>
  const form    = document.getElementById("checkin-form");
  const sendBtn = document.getElementById("send-btn");

  // โชว์ข้อความผล approved เขียว pending เหลือง rejected แดง
  function showMessage(status, text) {
    document.getElementById("result").innerHTML =
      '<p class="alert" data-status="' + status + '">' + text + '</p>';
  }

  // เลือกรูปแล้ว โชว์รูป แล้วอ่านรูป
  document.getElementById("photo").onchange = function () {
    const file = this.files[0];

    // โชว์รูปในกรอบ
    const preview = document.getElementById("preview");
    preview.src = URL.createObjectURL(file);
    preview.hidden = false;
    document.getElementById("upload-text").hidden = true;

    // ระหว่างอ่านรูป ปิดปุ่มส่งไว้ก่อน
    sendBtn.disabled = true;
    document.getElementById("scan").value = "";
    showMessage("pending", "กำลังอ่านรูป… (ประมาณ 3–5 วินาที)");

    // ให้ Tesseract อ่านรูป แล้วส่งข้อความให้ readGarmin หาตัวเลข
    Tesseract.recognize(file, "eng").then(function (result) {
      const run = readGarmin(result.data.text);

      if (run == null) {
        showMessage("rejected", "อ่านรูปไม่ได้ ใช้ภาพสรุปผลวิ่งจาก Garmin Connect แบบตัวอย่าง");
        return;
      }

      // อ่านได้ เติมระยะกับเวลาลงฟอร์มให้
      document.getElementById("scan").value = JSON.stringify(run);   // แปลงผลสแกนเป็น JSON
      form.distance.value = run.km;
      form.minutes.value  = run.minutes;
      sendBtn.disabled = false;
      showMessage("approved", "อ่านได้ " + run.km + " กม. · เวลา " + run.time + " ตรวจตัวเลขแล้วกดส่งได้เลย");
    }).catch(function () {
      // อ่านไม่ได้ เช่น ไม่มีเน็ต
      showMessage("rejected", "อ่านรูปไม่สำเร็จ ลองเช็คอินเทอร์เน็ตแล้วเลือกรูปใหม่");
    });
  };

  // กดส่ง ส่งฟอร์มด้วย XMLHttpRequest ได้ JSON กลับมา
  document.getElementById("checkin-form").onsubmit = function (event) {
    event.preventDefault();   // หน้าไม่โหลดใหม่

    const xhr = new XMLHttpRequest();
    xhr.open("POST", "checkin.php");

    xhr.onload = function () {
      const answer = JSON.parse(xhr.responseText);
      showMessage(answer.status, answer.message);

      if (answer.status == "approved") {
        document.getElementById("send-btn").style.display = "none";
        document.getElementById("result").innerHTML += '<a href="index.php" class="btn light full mt">กลับหน้าหลัก</a>';
      }
    };

    showMessage("pending", "กำลังบันทึก…");
    xhr.send(new FormData(this));   // FormData = ทุกช่องในฟอร์ม รวมรูป
  };
</script>

</body>
</html>
