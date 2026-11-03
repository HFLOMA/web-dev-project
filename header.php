<header>
  <div class="container">

    <a href="index.php" class="logo">Pace<span>Up</span></a>

    <div class="nav-right">
      <span class="desktop-only muted">
        Shield <?= (int)$user["shield_balance"] ?> ·
        Repair <?= (int)$user["freeze_balance"] ?> ·
        Insurance <?= (int)$user["insurance_balance"] ?>
      </span>

      <a href="shop.php" title="ร้านค้า">
        <svg class="icon" viewBox="0 0 24 24">
          <path d="M3 4h2l2 11h11l3-7H6"/>
          <circle cx="9" cy="20" r="1"/>
          <circle cx="17" cy="20" r="1"/>
        </svg>
      </a>

      <a href="profile.php" class="avatar" title="โปรไฟล์"><?= e(mb_substr($user["name"], 0, 1, "UTF-8")) ?></a>

      <button id="menu-btn" title="เมนู">
        <svg class="icon" viewBox="0 0 24 24">
          <path d="M4 7h16M4 12h16M4 17h16"/>
        </svg>
      </button>

      <nav id="menu" hidden>
        <a href="history.php">ประวัติธุรกรรม</a>
        <a href="logout.php" class="danger">ออกจากระบบ</a>
      </nav>
    </div>

  </div>
</header>

<script>
  document.getElementById("menu-btn").onclick = function () {
    const menu = document.getElementById("menu");
    menu.hidden = !menu.hidden;
  };
</script>
