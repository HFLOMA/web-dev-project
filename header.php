<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!function_exists('e')) {
    require_once __DIR__ . '/functions.php';
}

// ถ้ามี session แต่ยังไม่มีตัวแปร user ให้ดึงจากฐานข้อมูล
if (!empty($_SESSION["user_id"]) && empty($user)) {
    if (isset($pdo)) {
        $stmt_nav = $pdo->prepare("SELECT * FROM paceup__users WHERE id = ?");
        $stmt_nav->execute([$_SESSION["user_id"]]);
        $user = $stmt_nav->fetch();
    }
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

        <a href="shop.php" title="ร้านค้า">
          <svg class="icon" viewBox="0 0 24 24">
            <path d="M3 4h2l2 11h11l3-7H6"/>
            <circle cx="9" cy="20" r="1"/>
            <circle cx="17" cy="20" r="1"/>
          </svg>
        </a>

        <a href="profile.php" class="avatar" title="โปรไฟล์" style="display: inline-flex !important; align-items: center !important; justify-content: center !important; width: 36px !important; height: 36px !important; min-width: 36px !important; min-height: 36px !important; border-radius: 50% !important; text-align: center !important; padding: 0 !important; margin: 0 !important; line-height: 1 !important;"><?= e(mb_substr($user["name"] ?? "U", 0, 1, "UTF-8")) ?></a>

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

<script>
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
</script>
