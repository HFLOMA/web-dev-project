<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!function_exists('e')) {
    require_once __DIR__ . '/functions.php';
}

// ถ้าเชื่อมต่อฐานข้อมูลยังไม่พร้อม ให้โหลด connect.php
if (!isset($pdo)) {
    require_once __DIR__ . '/connect.php';
}

// ถ้าล็อกอินอยู่ ให้ดึงข้อมูลผู้ใช้เพื่อใช้แสดงใน navbar และกระเป๋าไอเทม
if (!empty($_SESSION["user_id"])) {
    if (empty($user) || !array_key_exists('lost_streak', $user)) {
        $stmt_nav = $pdo->prepare("SELECT * FROM paceup__users WHERE id = ?");
        $stmt_nav->execute([$_SESSION["user_id"]]);
        $user = $stmt_nav->fetch();
    }
}

// -------------------------------------------------------------
// ตัวแปรและเงื่อนไขสำหรับ "กระเป๋าไอเทมของฉัน" (My Inventory)
// -------------------------------------------------------------
$shield_qty     = intval($user['shield_balance'] ?? 0);
$repair_qty     = intval($user['freeze_balance'] ?? 0);
$insurance_qty  = intval($user['insurance_balance'] ?? 0);
$lost_streak    = intval($user['lost_streak'] ?? 0);
$streak_lost_at = $user['streak_lost_at'] ?? null;

// จำนวนไอเทมรวมในกระเป๋า เพื่อแสดงบน badge ที่ไอคอนกระเป๋าเป้
$total_items = $shield_qty + $repair_qty + $insurance_qty;

// เช็คเงื่อนไขไอเทมที่ 2: Streak Repair (Active)
// เงื่อนไขปุ่มส้ม: มีของ (>0) และ มีสตรีคที่หลุด (>0) และ เวลาที่หลุดยังไม่เกิน 48 ชม.
$can_repair = false;
$repair_reason = '';

$is_within_48h = false;
if (!empty($streak_lost_at)) {
    $time_diff = time() - strtotime($streak_lost_at);
    // 48 ชม. = 48 * 3600 วินาที = 172800 วินาที
    if ($time_diff >= 0 && $time_diff <= (48 * 3600)) {
        $is_within_48h = true;
    }
}

if ($repair_qty <= 0) {
    $repair_reason = 'ไม่มีไอเทมในกระเป๋า';
} elseif ($lost_streak <= 0) {
    $repair_reason = 'ไม่มีสตรีคที่กู้คืนได้';
} elseif (!$is_within_48h) {
    $repair_reason = 'เลยกำหนดเวลา 48 ชม. แล้ว';
} else {
    $can_repair = true;
}

// เช็คเงื่อนไขไอเทมที่ 3: Streak Halving Insurance (Active)
// เงื่อนไขปุ่มส้ม: มีของ (>0) และ มีสตรีคที่เพิ่งหลุดไปตั้งแต่ 40 วันขึ้นไป
$can_insurance = false;
$insurance_reason = '';

if ($insurance_qty <= 0) {
    $insurance_reason = 'ไม่มีไอเทมในกระเป๋า';
} elseif ($lost_streak < 40) {
    $insurance_reason = 'ใช้ได้เมื่อสตรีคหลุด 40 วันขึ้นไป';
} else {
    $can_insurance = true;
}
?>
<link rel="stylesheet" href="css/navbar.css?v=<?= time() ?>">
<header>
  <div class="container">

    <a href="index.php" class="logo">Pace<span>Up</span></a>

    <div class="nav-right">
      <?php if (!empty($_SESSION["user_id"])): ?>
        <span class="desktop-only muted">
          Shield <?= (int)($user["shield_balance"] ?? 0) ?> ·
          Repair <?= (int)($user["freeze_balance"] ?? 0) ?> ·
          Insurance <?= (int)($user["insurance_balance"] ?? 0) ?>
        </span>

        <!-- ปุ่มไอคอนกระเป๋าเป้ (My Inventory) -->
        <button type="button" id="inventory-btn" class="backpack-btn" title="กระเป๋าไอเทมของฉัน">
          <svg class="icon" viewBox="0 0 24 24">
            <path d="M4 10a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-9z"></path>
            <path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"></path>
            <path d="M8 21v-4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v4"></path>
            <line x1="10" y1="10" x2="14" y2="10"></line>
          </svg>
          <span class="backpack-badge"><?= $total_items ?></span>
        </button>

        <a href="shop.php" title="ร้านค้า">
          <svg class="icon" viewBox="0 0 24 24">
            <path d="M3 4h2l2 11h11l3-7H6"/>
            <circle cx="9" cy="20" r="1"/>
            <circle cx="17" cy="20" r="1"/>
          </svg>
        </a>

        <a href="index.php" class="avatar" title="หน้าหลัก" style="display: inline-flex !important; align-items: center !important; justify-content: center !important; width: 36px !important; height: 36px !important; min-width: 36px !important; min-height: 36px !important; border-radius: 50% !important; text-align: center !important; padding: 0 !important; margin: 0 !important; line-height: 1 !important;"><?= e(mb_substr($user["name"] ?? "U", 0, 1, "UTF-8")) ?></a>

        <span class="desktop-only" style="font-weight: 600; font-size: 14px;"><?= htmlspecialchars($user["name"] ?? "") ?></span>

        <button id="menu-btn" title="เมนู">
          <svg class="icon" viewBox="0 0 24 24">
            <path d="M4 7h16M4 12h16M4 17h16"/>
          </svg>
        </button>

        <nav id="menu" hidden>
          <a href="history.php">ประวัติธุรกรรม</a>
          <a href="logout.php" class="danger">ออกจากระบบ</a>
        </nav>
      <?php else: ?>
        <a href="login.php" style="text-decoration: none; font-size: 14px; font-weight: 600; color: var(--main, #e8603c);">เข้าสู่ระบบ</a>
      <?php endif; ?>
    </div>

  </div>
</header>

<?php if (!empty($_SESSION["user_id"])): ?>
<!-- หน้าต่าง Modal กระเป๋าไอเทมของฉัน (สไตล์ RPG คลีนๆ) -->
<div id="inventory-modal" class="inv-modal" style="display: none;">
  <div class="inv-box">

    <!-- ส่วนหัวหน้าต่าง Modal -->
    <div class="inv-header">
      <div class="inv-title">
        <span class="inv-icon">🎒</span>
        <h3>กระเป๋าไอเทมของฉัน</h3>
      </div>
      <button type="button" id="close-inventory-btn" class="inv-close" title="ปิดหน้าต่าง">&times;</button>
    </div>

    <!-- แถบแจ้งสถานะสตรีค -->
    <div class="inv-streak-info">
      <span>สตรีคที่หลุดล่าสุด: <strong><?= $lost_streak ?> วัน</strong></span>
      <?php if (!empty($streak_lost_at) && $lost_streak > 0): ?>
        <small>(หลุดเมื่อ: <?= date('d/m/Y H:i', strtotime($streak_lost_at)) ?>)</small>
      <?php endif; ?>
    </div>

    <!-- รายการไอเทม 3 ชิ้น -->
    <div class="inv-list">

      <!-- ไอเทมที่ 1: Streak Shield (Passive) -->
      <div class="inv-card">
        <div class="inv-card-img">
          <img src="img/streakShield.jpg" alt="Streak Shield" onerror="this.src='img/example.jpg'">
          <span class="inv-item-qty">x<?= $shield_qty ?></span>
        </div>
        <div class="inv-card-body">
          <div class="inv-card-title-row">
            <h4>Streak Shield</h4>
            <span class="tag-passive">Passive</span>
          </div>
          <p class="inv-desc">ป้องกันสตรีคหลุดอัตโนมัติ 1 วันเมื่อลืมวิ่งเช็คอิน</p>
          <div class="inv-passive-msg">
            🛡️ ใช้งานอัตโนมัติเมื่อลืมเช็คอิน
          </div>
        </div>
      </div>

      <!-- ไอเทมที่ 2: Streak Repair (Active) -->
      <div class="inv-card">
        <div class="inv-card-img">
          <img src="img/streakRepair.jpg" alt="Streak Repair" onerror="this.src='img/example.jpg'">
          <span class="inv-item-qty">x<?= $repair_qty ?></span>
        </div>
        <div class="inv-card-body">
          <div class="inv-card-title-row">
            <h4>Streak Repair</h4>
            <span class="tag-active">Active</span>
          </div>
          <p class="inv-desc">กู้คืนสตรีคที่เพิ่งหลุดไปให้กลับมา (ใช้ได้ภายใน 48 ชม.)</p>
          <div class="inv-btn-area">
            <?php if ($can_repair): ?>
              <!-- เข้าเงื่อนไข ปุ่มสีส้ม กดใช้งานได้ -->
              <button type="button" class="btn-rpg btn-orange" onclick="useInventoryItem('repair')">
                กดใช้งาน
              </button>
            <?php else: ?>
              <!-- ไม่เข้าเงื่อนไข ปุ่มสีเทา disabled -->
              <button type="button" class="btn-rpg btn-gray" disabled>
                กดใช้งาน
              </button>
              <span class="reason-label"><?= htmlspecialchars($repair_reason) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- ไอเทมที่ 3: Streak Halving Insurance (Active) -->
      <div class="inv-card">
        <div class="inv-card-img">
          <img src="img/streakHavlvingInsurance.jpg" alt="Streak Halving Insurance" onerror="this.src='img/example.jpg'">
          <span class="inv-item-qty">x<?= $insurance_qty ?></span>
        </div>
        <div class="inv-card-body">
          <div class="inv-card-title-row">
            <h4>Streak Halving Insurance</h4>
            <span class="tag-active">Active</span>
          </div>
          <p class="inv-desc">ประกันลดทอนสตรีค กู้คืนสตรีคกลับมาครึ่งหนึ่งเมื่อหลุด 40 วันขึ้นไป</p>
          <div class="inv-btn-area">
            <?php if ($can_insurance): ?>
              <!-- เข้าเงื่อนไข ปุ่มสีส้ม กดใช้งานได้ -->
              <button type="button" class="btn-rpg btn-orange" onclick="useInventoryItem('insurance')">
                กดใช้งาน
              </button>
            <?php else: ?>
              <!-- ไม่เข้าเงื่อนไข ปุ่มสีเทา disabled -->
              <button type="button" class="btn-rpg btn-gray" disabled>
                กดใช้งาน
              </button>
              <span class="reason-label"><?= htmlspecialchars($insurance_reason) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div>

  </div>
</div>
<?php endif; ?>

<script>
  // สคริปต์เมนูสามขีด (เดิม)
  (function() {
    const menuBtn = document.getElementById("menu-btn");
    const menu = document.getElementById("menu");
    if (menuBtn && menu) {
      menuBtn.onclick = function (e) {
        e.stopPropagation();
        menu.hidden = !menu.hidden;
      };
      document.addEventListener("click", function (e) {
        if (!menu.hidden && !menu.contains(e.target) && e.target !== menuBtn) {
          menu.hidden = true;
        }
      });
    }
  })();

  // สคริปต์ควบคุมการเปิด-ปิด กระเป๋าไอเทม (Inventory Modal)
  (function() {
    const invBtn = document.getElementById("inventory-btn");
    const invModal = document.getElementById("inventory-modal");
    const closeBtn = document.getElementById("close-inventory-btn");

    // เปิดกระเป๋าเมื่อคลิกที่ไอคอนกระเป๋าเป้
    if (invBtn && invModal) {
      invBtn.addEventListener("click", function(e) {
        e.stopPropagation();
        invModal.style.display = "flex";
      });
    }

    // ปิดกระเป๋าเมื่อคลิกปุ่มกากบาท (X)
    if (closeBtn && invModal) {
      closeBtn.addEventListener("click", function() {
        invModal.style.display = "none";
      });
    }

    // ปิดกระเป๋าเมื่อคลิกพื้นที่ด้านนอกกล่อง Modal (Overlay)
    if (invModal) {
      invModal.addEventListener("click", function(e) {
        if (e.target === invModal) {
          invModal.style.display = "none";
        }
      });
    }

    // ปิดกระเป๋าเมื่อกดปุ่ม Escape (Esc)
    document.addEventListener("keydown", function(e) {
      if (e.key === "Escape" && invModal && invModal.style.display === "flex") {
        invModal.style.display = "none";
      }
    });
  })();

  // ฟังก์ชันยิง API เมื่อกดใช้งานไอเทม (Active)
  function useInventoryItem(itemType) {
    const itemNames = {
      'repair': 'Streak Repair',
      'insurance': 'Streak Halving Insurance'
    };
    const title = itemNames[itemType] || 'ไอเทมนี้';

    if (!confirm('คุณต้องการใช้งาน ' + title + ' ใช่หรือไม่?')) {
      return;
    }

    const formData = new FormData();
    formData.append('item_type', itemType);

    fetch('api/api_use_item.php', {
      method: 'POST',
      body: formData
    })
    .then(function(res) {
      return res.json();
    })
    .then(function(data) {
      alert(data.message);
      if (data.success) {
        // รีเฟรชหน้าเพื่ออัปเดตสตรีคและจำนวนไอเทมล่าสุด
        location.reload();
      }
    })
    .catch(function(err) {
      console.error(err);
      alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์');
    });
  }
</script>
