<?php
session_start();
if (!isset($_SESSION['admin_id']) && isset($_SESSION['user_id'])) {
  $_SESSION['admin_id'] = (int)$_SESSION['user_id'];
}
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

require_once '../src/db.php';

// Roles
$isAdmin = ($_SESSION['role'] ?? 'staff') === 'admin';

// current admin (chip)
$me = $pdo->prepare("SELECT name, role FROM admins WHERE id=?");
$me->execute([$_SESSION['admin_id']]);
$admin = $me->fetch(PDO::FETCH_ASSOC);

// search
$q = trim($_GET['q'] ?? '');
$params = [];
$sql = "SELECT id, name, logo_url FROM stores 
        WHERE type = 'pharmacy' AND is_active=1 AND is_deleted=0";
if ($q !== '') { $sql .= " AND name LIKE ?"; $params[] = "%$q%"; }
$sql .= " ORDER BY name";
$stmt = $pdo->prepare($sql); 
$stmt->execute($params);
$pharmacies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// KPIs (pharmacy scope only)
$near_expiry = (int)$pdo->query("
  SELECT COUNT(*)
  FROM items i
  JOIN stores s ON s.id = i.store_id
  WHERE s.type='pharmacy' AND i.is_deleted=0
    AND i.expiry_date IS NOT NULL
    AND i.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
    AND i.expiry_date >= CURDATE()
    AND i.in_stock=1
")->fetchColumn();

$rx_in_stock = (int)$pdo->query("
  SELECT COUNT(*)
  FROM items i
  JOIN stores s ON s.id = i.store_id
  WHERE s.type='pharmacy' AND i.is_deleted=0
    AND i.requires_prescription=1
    AND i.in_stock=1
")->fetchColumn();

$out_of_stock = (int)$pdo->query("
  SELECT COUNT(*)
  FROM items i
  JOIN stores s ON s.id = i.store_id
  WHERE s.type='pharmacy' AND i.is_deleted=0
    AND i.in_stock=0
")->fetchColumn();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Veloo — Pharmacies</title>
  <link rel="stylesheet" href="assets/styles.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <script src="https://unpkg.com/feather-icons"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script>
    document.addEventListener('DOMContentLoaded', () => { feather.replace(); });
  </script>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

</head>
<body>
<header>
<!-- In your top bar / header (visible on mobile), e.g. above <main> -->
<button class="menu-toggle" aria-label="Open menu">
  <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true">
    <path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/>
  </svg>
</button>

<button class="sidebar-close" aria-label="Close menu">
  <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true">
    <path d="M6 6l12 12M6 18L18 6" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/>
  </svg>
</button>
  


<!-- Immediately inside <body>, before or after .app is fine -->
<div class="backdrop" hidden></div>
  </header>
<div class="app">

  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="brand">
      <img src="assets/default-store.png" style="width:28px;height:28px;border-radius:8px;" alt="">
      Veloo Admin
    </div>
    <nav class="nav">
      <a href="stores-dashboard.php"><i data-feather="home"></i><span>Stores</span></a>
      <a class="active" href="pharmacies-dashboard.php"><i data-feather="activity"></i><span>Pharmacies</span></a>
      <a href="deleted-items.php"><i data-feather="archive"></i><span>Deleted Items</span></a>
      <?php if ($isAdmin): ?>
        <a href="api/admin-keys.php"><i data-feather="key"></i><span>API Keys</span></a>
        <a href="api/explorer.php"><i data-feather="flask"></i><span>API Explorer</span></a>
        <a href="api/v1/docs.html"><i data-feather="book-open"></i><span>API Docs</span></a>
      <?php endif; ?>
      <a href="logout.php"><i data-feather="log-out"></i><span>Logout</span></a>
    </nav>

    <?php if ($isAdmin): ?>
      <div style="margin-top:24px;">
        <a class="btn" href="add-pharmacy.php">
          <i data-feather="plus-circle"></i><span>Add Pharmacy</span>
        </a>
      </div>
    <?php endif; ?>
  </aside>

  <!-- Main -->
  <main class="main">
    <div class="topbar">
      <div class="h-intro">Welcome back<?= $admin ? ', '.htmlspecialchars($admin['name']) : '' ?>.</div>
      <div class="userchip">
        <span class="avatar"></span>
        <div>
          <div style="font-weight:600;"><?= htmlspecialchars($admin['name'] ?? 'User') ?></div>
          <div class="role"><?= htmlspecialchars(ucfirst($admin['role'] ?? 'staff')) ?></div>
        </div>
      </div>
    </div>

    <!-- KPIs -->
    <div class="grid-cards" style="margin-bottom:14px;">
      <div class="card"><h4>Total Pharmacies</h4><div class="stat"><?= count($pharmacies) ?></div></div>
      <div class="card"><h4>Near Expiry (≤60d)</h4><div class="stat"><?= $near_expiry ?></div></div>
      <div class="card"><h4>Rx In Stock</h4><div class="stat"><?= $rx_in_stock ?></div></div>
      <div class="card"><h4>Out of Stock</h4><div class="stat"><?= $out_of_stock ?></div></div>
    </div>

    <!-- Search -->
    <form method="GET" class="controls" style="margin-bottom:10px;">
      <input class="input" type="text" name="q" placeholder="Search pharmacies…" value="<?= htmlspecialchars($q) ?>" style="max-width:380px;">
      <button class="btn" type="submit"><i data-feather="search"></i><span>Search</span></button>
      <?php if ($q !== ''): ?><a class="btn-outline" href="pharmacies-dashboard.php">Clear</a><?php endif; ?>
    </form>

    <!-- Grid -->
    <?php if (!$pharmacies): ?>
      <div class="card">No pharmacies match this search.</div>
    <?php else: ?>
      <div class="store-grid">
        <?php foreach ($pharmacies as $p): ?>
          <a class="store-card" href="inventory.php?store_id=<?= $p['id'] ?>">
            <img class="store-logo" src="<?= $p['logo_url'] ?: 'assets/items.png' ?>" alt="">
            <div>
              <div style="font-weight:600;"><?= htmlspecialchars($p['name']) ?></div>
              <div style="color:var(--muted); font-size:.9rem;">View inventory →</div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>

</div>
</body>

<script>
const sidebar  = document.querySelector('.sidebar');
const openBtn  = document.querySelector('.menu-toggle');
const closeBtn = document.querySelector('.sidebar-close');
const backdrop = document.querySelector('.backdrop');

openBtn.addEventListener('click', () => {
  sidebar.classList.add('open');
  backdrop.classList.add('show');
});

closeBtn.addEventListener('click', () => {
  sidebar.classList.remove('open');
  backdrop.classList.remove('show');
});

backdrop.addEventListener('click', () => {
  sidebar.classList.remove('open');
  backdrop.classList.remove('show');
});

</script>

</html>
