<?php
// สร้าง session เก็บ id
session_start();
include "connect.php";
include "functions.php";

$user_id = $_SESSION["user_id"] ?? 1;

$message = check_streak($pdo, $user_id);
$user    = get_user($pdo, $user_id);


// รวมระยะทางที่วิ่งในสัปดานี้
$monday = date("Y-m-d", strtotime("monday this week"));
$stmt = $pdo->prepare("SELECT SUM(ocr_distance) FROM paceup__check_ins
                       WHERE user_id = ? AND status = 'approved' AND created_at >= ?");
$stmt->bindParam(1, $user_id);
$stmt->bindParam(2, $monday);
$stmt->execute();
$week_km = $stmt->fetchColumn() ?? 0;

//นับวันที่วื่งในweekนี้
$stmt = $pdo->prepare("SELECT DISTINCT WEEKDAY(created_at) + 1 AS day_no FROM paceup__check_ins
                       WHERE user_id = ? AND status = 'approved' AND created_at >= ?");
$stmt->bindParam(1, $user_id);
$stmt->bindParam(2, $monday);
$stmt->execute();

// เก็บเลขวันที่วิ่ง เช่น 1,2,3,7
$run_days = [];
while ($row = $stmt->fetch()) {
    $run_days[] = $row["day_no"];
}

//คํานวน เปอร์เซนที่ วิ่งไปแล้ว
$goal    = $user["target_distance"];
$percent = $goal > 0 ? min(100, round($week_km / $goal * 100)) : 0;
$left    = max(0, $goal - $week_km);

//สถิติรวมทั้งหมด
$stmt = $pdo->prepare("SELECT COUNT(*) AS runs, SUM(ocr_distance) AS km FROM paceup__check_ins
                       WHERE user_id = ? AND status = 'approved'");
$stmt->bindParam(1, $user_id);
$stmt->execute();
$total = $stmt->fetch();


//จัดรูป sql  day,distance,minutes,pace,status ตัวอย่าง 17/10 , 6.51, 45,7.0,approved 
$stmt = $pdo->prepare("SELECT DATE_FORMAT(created_at, '%d/%m') AS day,
                      ocr_distance,
                      duration DIV 60 AS minutes,
                      ROUND(duration / 60 / ocr_distance, 1) AS pace,
                      status
                      FROM paceup__check_ins WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bindParam(1, $user_id);
$stmt->execute();
$checkins = $stmt->fetchAll();


//จัดอันดับตาม ระยาทาง หรือ streak 
$rank_type = $_GET["rank"] ?? "distance";
if ($rank_type == "streak") {
    $stmt = $pdo->query("SELECT name, current_streak AS value FROM paceup__users ORDER BY current_streak DESC");
    $unit = "วัน";
} else {
    $stmt = $pdo->query("SELECT `ชื่อผู้ใช้` AS name, `ระยะทางรวม` AS value FROM paceup__view_runner_stats");
    $unit = "กม.";
}
$ranking = $stmt->fetchAll();


//คํานวน ระยะทาง ที่ ต้อง วิ่งของ user
$g = get_goal($user);


// $message   ข้อความ Streak     เช่น ["approved", "ใช้ Shield ..."] หรือ null ถ้าไม่มีอะไร
// $user      ข้อมูลผู้ใช้ 1 แถว   เช่น $user["name"], $user["current_streak"], $user["shield_balance"]
// $week_km   กม. สัปดาห์นี้       เช่น 6.51
// $run_days  วันที่วิ่งแล้ว        เช่น [1, 3, 5]  (1 = จันทร์ ... 7 = อาทิตย์)
// $goal      เป้าสัปดาห์ (กม.)     เช่น 50
// $percent   % ที่ทำได้           เช่น 13
// $left      กม. ที่เหลือ          เช่น 43.49
// $total     สถิติรวม            เช่น ["runs" => 3, "km" => 21.78]
// $checkins  เช็คอินล่าสุด 5 แถว   แต่ละแถวมี day, ocr_distance, minutes, pace, status
// $rank_type แบบอันดับ           "distance" หรือ "streak"
// $unit      หน่วยในอันดับ        "กม." หรือ "วัน"
// $ranking   ตารางอันดับ          แต่ละแถวมี name, value
// $g         เป้าตามสูตร          $g["level"], $g["base"], $g["bmi"], $g["bmi_rate"], $g["goal"]

?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PaceUp</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/home.css">
</head>
<body>

<?php include "header.php"; ?>

<main class="container">

  <!-- แสดงชื่อ -->
  <section class="hero">
    <h1>วันนี้วิ่งหรือยัง <span class="user-name"><?= e($user["name"]) ?></span>?</h1>
    <a href="checkin.php" class="btn big">เช็คอินวันนี้</a>
  </section>

  <!-- แจ้งเตือนเรื่อง Streak -->
  <?php if ($message): ?>
    <p class="alert mt" data-status="<?= $message[0] ?>"><?= $message[1] ?></p>
  <?php endif; ?>


  <!-- ปุ่มแท็บ  -->
  <div class="tabs">
    <button class="active" data-tab="overview">ภาพรวม</button>
    <button data-tab="rank">อันดับ</button>
    <button data-tab="goal">เป้าหมาย</button>
  </div>


  <!-- ภาพรวม  -->
  <section class="panel" id="overview">
    <div class="grid cols-3">

      <div class="card stat">
        <p class="label">Streak</p>
        <p class="num"><?= $user["current_streak"] ?> <small>วัน</small></p>

        <div class="days">
          <?php $day_names = [1 => "จ", 2 => "อ", 3 => "พ", 4 => "พฤ", 5 => "ศ", 6 => "ส", 7 => "อา"]; ?>
          <!-- วงกลม วันไหนอยู่ใน $run_days ได้ class done วงกลมสีส้ม-->
          <?php foreach ($day_names as $number => $name): ?>
            <span class="<?= in_array($number, $run_days) ? "done" : "" ?>"><?= $name ?></span>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- แสดงระยะทางรวมทั้งสัปดา พร้อม หลอด -->
      <div class="card stat">
        <p class="label">ระยะทางสัปดาห์นี้</p>
        <p class="num"><?= number_format($week_km, 1) ?> <small>กม.</small></p>
        <div class="progress"><div id="goal-bar" style="width: <?= $percent ?>%"></div></div>
      </div>

      <!-- แสดง เปอร์เซน ที่วิ่งไปแล้ว -->
      <div class="card stat">
        <p class="label">ทำตามเป้า</p>
        <p class="num"><?= $percent ?>%</p>
        <p class="muted">อีก <?= number_format($left, 1) ?> กม.</p>
      </div>

    </div>

    <!-- แสดงประวัติ และสถานะ  -->
    <div class="card mt">
      <h3>เช็คอินล่าสุด</h3>
      <div class="table-wrap">
        <table>
          <tr><th>วันที่</th><th>ระยะทาง</th><th>เวลา</th><th>Pace</th><th>สถานะ</th></tr>
          <?php foreach ($checkins as $c): ?>
            <tr>
              <td><?= $c["day"] ?></td>
              <td><?= $c["ocr_distance"] ?> กม.</td>
              <td><?= $c["minutes"] ?> นาที</td>
              <td><?= $c["pace"] ?> นาที/กม.</td>
              <td><span class="pill" data-status="<?= $c["status"] ?>"><?= $c["status"] == "approved" ? "ผ่าน" : "ไม่ผ่าน" ?></span></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
      <?php if (count($checkins) == 0): ?>
        <p class="muted mt">ยังไม่มีเช็คอิน</p>
      <?php endif; ?>
    </div>
  </section>


  <!-- จัดอันดับ เลือก streak and อันดับ -->
  <section class="panel card" id="rank" hidden>
    <h3>อันดับ</h3>

    
    <form class="mt" method="get">
      <select class="small" name="rank" onchange="this.form.submit()">
        <option value="distance">ระยะทาง</option>
        <option value="streak" <?= $rank_type == "streak" ? "selected" : "" ?>>Streak</option>
      </select>
    </form>

    <ol class="list mt">
      <?php foreach ($ranking as $i => $r): ?>
        <li <?= $r["name"] == $user["name"] ? 'id="me"' : "" ?>>
          <b><?= $i + 1 ?></b>
          <span class="name"><?= e($r["name"]) ?></span>
          <span><?= $r["value"] ?> <?= $unit ?></span>
        </li>
      <?php endforeach; ?>
    </ol>
  </section>


  <!-- เป้าหมาย  -->
  <section class="panel card" id="goal" hidden>
    <p class="label">เป้าสัปดาห์นี้</p>
    <p class="num"><?= number_format($goal, 0) ?> <small>กม.</small></p>

    <ul class="list mt">
      <li><span class="name">ประสบการณ์: <?= $g["level"] ?></span><span><?= $g["base"] ?> กม.</span></li>
      <li><span class="name">BMI: <?= number_format($g["bmi"], 1) ?></span><span>× <?= $g["bmi_rate"] ?></span></li>
      <li><b class="name">เป้าตามสูตร</b><b><?= $g["goal"] ?> กม.</b></li>
    </ul>
    <p class="muted mt">เป้านี้เป็นคำแนะนำทั่วไป ไม่ใช่คำแนะนำทางการแพทย์</p>
  </section>


 <!-- แสดงค่าด้านล่าง ระยะรวม ครั้งทที่เช็คอิน Streak(ปัจจุบัน) -->
  <section class="bignums">
    <div><b class="link"><?= number_format($total["km"] ?? 0, 1) ?></b><span class="muted">กิโลเมตรที่วิ่งไปแล้ว</span></div>
    <div><b><?= $total["runs"] ?></b><span class="muted">ครั้งที่เช็คอินสำเร็จ</span></div>
    <div><b><?= $user["current_streak"] ?></b><span class="muted">วัน Streak ปัจจุบัน</span></div>
  </section>

</main>


<script>
  //สลับแท็บ ภาพรวม อันดับ เป้าหมาย

  // เก็บปุ่มแท็บทั้ง 3 ปุ่มไว้ในตัวแปร buttons
  //  ".tabs button" = ปุ่มที่อยู่ข้างใน <div class="tabs">
  const buttons = document.querySelectorAll(".tabs button");

  // เก็บเนื้อหาแท็บทั้ง 3 ส่วนไว้ในตัวแปร panels
  //  ".panel" = <section> ที่มี class="panel" (overview, rank, goal)
  const panels = document.querySelectorAll(".panel");


  // ฟังก์ชันเปิดแท็บ: ส่งชื่อแท็บเข้ามา เช่น showTab("rank")
  function showTab(name) {

    // ขั้นที่ 1: เปลี่ยนสีปุ่ม
    //   ปุ่มที่ data-tab ตรงกับชื่อ → ใส่ class active (ปุ่มสีส้ม)
    //   ปุ่มอื่น                   → เอา class active ออก (ปุ่มสีเทา)
    for (let i = 0; i < buttons.length; i++) {
      if (buttons[i].dataset.tab == name) {
        buttons[i].classList.add("active");
      } else {
        buttons[i].classList.remove("active");
      }
    }

    // ขั้นที่ 2: สลับเนื้อหา
    //   section ที่ id ตรงกับชื่อ → แสดง (hidden = false)
    //   section อื่น              → ซ่อน (hidden = true)
    for (let i = 0; i < panels.length; i++) {
      if (panels[i].id == name) {
        panels[i].hidden = false;
      } else {
        panels[i].hidden = true;
      }
    }
  }


  // ผูกปุ่ม: กดปุ่มไหน ก็เรียก showTab ด้วยชื่อแท็บของปุ่มนั้น
  for (let i = 0; i < buttons.length; i++) {
    buttons[i].onclick = function () {
      showTab(buttons[i].dataset.tab);
    };
  }


  // ตอนเปิดหน้า: เลือกแท็บแรกที่จะแสดง
  //   URL มี ?rank= (เพิ่งเปลี่ยนแบบอันดับ) → เปิดแท็บอันดับค้างไว้
  //   ไม่มี                              → เปิดแท็บภาพรวม
  <?php $first_tab = isset($_GET["rank"]) ? "rank" : "overview"; ?>
  showTab("<?= $first_tab ?>");
</script>

</body>
</html>
