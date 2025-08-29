<?php
// public/inventory.php
session_start();

// Normalize session (support legacy user_id)
if (!isset($_SESSION['admin_id']) && isset($_SESSION['user_id'])) {
  $_SESSION['admin_id'] = (int)$_SESSION['user_id'];
}
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

require_once '../src/db.php';
require_once '../src/auth.php';

// 1) Resolve store, enforce scope
$store_id = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 0;
if (!$store_id) { header('Location: dashboard.php'); exit; }
assertStoreAccess($pdo, $store_id);

// 2) Current admin (chip)
$meStmt = $pdo->prepare("SELECT name, role FROM admins WHERE id = ?");
$meStmt->execute([$_SESSION['admin_id']]);
$currentAdmin = $meStmt->fetch(PDO::FETCH_ASSOC);
$isAdmin = (($currentAdmin['role'] ?? 'staff') === 'admin');

// 3) Load store (note: type used to toggle pharmacy UX)
$storeStmt = $pdo->prepare("SELECT id, name, logo_url, type FROM stores WHERE id = ? AND is_active = 1 AND is_deleted = 0");
$storeStmt->execute([$store_id]);
$store = $storeStmt->fetch(PDO::FETCH_ASSOC);
if (!$store) { header('Location: dashboard.php'); exit; }
$isPharmacy = ($store['type'] === 'pharmacy');


// Get store type ('store' | 'pharmacy') and then load only matching categories
$typeStmt = $pdo->prepare("SELECT type FROM stores WHERE id = ?");
$typeStmt->execute([$store_id]);
$storeType = $typeStmt->fetchColumn();
if (!$storeType) { header('Location: dashboard.php?msg=Invalid%20store'); exit; }

$catStmt = $pdo->prepare("SELECT id, name FROM categories WHERE type = ? ORDER BY name");
$catStmt->execute([$storeType]);
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// Guard: if user passed a category_id from the other type, ignore it
$cat_filter = (int)($_GET['category_id'] ?? 0);
if ($cat_filter) {
  $chk = $pdo->prepare("SELECT type FROM categories WHERE id=?");
  $chk->execute([$cat_filter]);
  $catType = $chk->fetchColumn();
  if ($catType !== $storeType) {
    $cat_filter = 0; // neutralize invalid filter
  }
}

// 4) Filters

$q            = trim($_GET['q'] ?? '');
$cat_filter   = (int)($_GET['category_id'] ?? 0);
$stock_filter = $_GET['stock'] ?? '';         // '', 'in', 'low', 'out'
$exp_filter   = $_GET['expiry'] ?? '';        // '', 'soon', 'expired' (pharmacy only)

// 5) Query items (include pharmacy fields)
$params = [$store_id];
$sql = "SELECT id, name, brand, price, markup_price, in_stock, image_url,
               category_id, quantity, low_stock_threshold, description,
               generic_name, dosage_form, strength, requires_prescription, expiry_date
        FROM items
        WHERE store_id = ? AND is_deleted = 0";

if ($q !== '') {
  $sql .= " AND (name LIKE ? OR brand LIKE ? OR description LIKE ? OR generic_name LIKE ?)";
  $like = "%$q%";
  array_push($params, $like, $like, $like, $like);
}
if ($cat_filter) {
  $sql .= " AND category_id = ?";
  $params[] = $cat_filter;
}
if ($stock_filter === 'low') {
  $sql .= " AND quantity > 0 AND quantity <= low_stock_threshold";
} elseif ($stock_filter === 'in') {
  $sql .= " AND in_stock = 1";
} elseif ($stock_filter === 'out') {
  $sql .= " AND in_stock = 0";
}
if ($isPharmacy && $exp_filter !== '') {
  if ($exp_filter === 'soon') {
    $sql .= " AND expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY) AND expiry_date >= CURDATE()";
  } elseif ($exp_filter === 'expired') {
    $sql .= " AND expiry_date IS NOT NULL AND expiry_date < CURDATE()";
  }
}

$sql .= " ORDER BY name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($store['name']) ?> — Inventory</title>
  <link rel="stylesheet" href="assets/styles.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    /* Page-local tweaks on top of styles.css */
    .inv-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
    .store-head { display:flex; align-items:center; gap:.75rem; }
    .store-logo { width:42px; height:42px; object-fit:cover; border-radius:10px; background:#eef2ff; }
    .search-row { margin: 10px 0 8px; display:grid; gap:.6rem; grid-template-columns: 1fr 220px 160px <?= $isPharmacy ? '160px' : '0px' ?> 120px; }
    .grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(270px, 1fr)); gap:14px; }
    .card { background: var(--panel); border:1px solid var(--line); border-radius: var(--radius); padding:.9rem; display:flex; gap:.75rem; box-shadow:var(--shadow); }
    .card.low { border-color:#fed7aa; background:#fff7ed; }
    .card.out { border-color:#fecaca; background:#fff1f2; }
    .thumb { width:68px; height:68px; object-fit:cover; border-radius:10px; background:#f3f4f6; }
    .muted { color:var(--muted); font-size:.9rem; }
    .price { font-weight:700; }
    .qty { font-size:.8rem; color:var(--muted); }
    .badge { font-size:.75rem; padding:2px 8px; border-radius:999px; border:1px solid var(--line); }
    .badge.low { border-color:#f59e0b; background:#fffbeb; color:#92400e; }
    .badge.out { border-color:#ef4444; background:#fef2f2; color:#b91c1c; }
    .pharm-meta span { margin-right:6px; }
    @media (max-width: 900px){ .search-row{ grid-template-columns:1fr; } }
  </style>
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
      <a href="pharmacies-dashboard.php"><i data-feather="activity"></i><span>Pharmacies</span></a>
      <a class="active" href="inventory.php?store_id=<?= $store['id'] ?>"><i data-feather="box"></i><span>Inventory</span></a>
      <a href="deleted-items.php"><i data-feather="archive"></i><span>Deleted Items</span></a>
      <?php if ($isAdmin): ?>
        <a href="api/admin-keys.php"><i data-feather="key"></i><span>API Keys</span></a>
        <a href="api/explorer.php"><i data-feather="flask"></i><span>API Explorer</span></a>
        <a href="api/v1/docs.html"><i data-feather="book-open"></i><span>API Docs</span></a>
      <?php endif; ?>
      <a href="logout.php"><i data-feather="log-out"></i><span>Logout</span></a>
    </nav>
  </aside>

  <!-- Main -->
  <main class="main">
    <!-- Header -->
    <div class="inv-head">
      <div class="store-head">
        <img class="store-logo" src="<?= $store['logo_url'] ?: 'assets/default-store.png' ?>" alt="">
        <h2 style="margin:0"><?= htmlspecialchars($store['name']) ?> — <?= $isPharmacy ? 'Pharmacy Inventory' : 'Inventory' ?></h2>
      </div>
      <div class="controls">
        <a class="btn-outline" href="<?= $isPharmacy ? 'pharmacies-dashboard.php' : 'stores-dashboard.php' ?>"><i data-feather="arrow-left"></i><span>Back</span></a>
        <a class="btn-outline" href="edit-store.php?id=<?= $store['id'] ?>"><i data-feather="edit"></i><span>Edit <?= $isPharmacy ? 'Pharmacy' : 'Store' ?></span></a>
        <?php if ($isPharmacy): ?>
          <a class="btn" href="add-pharmacy-item.php?store_id=<?= $store['id'] ?>"><i data-feather="plus-circle"></i><span>Add Drug</span></a>
        <?php else: ?>
          <a class="btn" href="add-item.php?store_id=<?= $store['id'] ?>"><i data-feather="plus-circle"></i><span>Add Inventory</span></a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Logged-in chip -->
    <div class="userchip" style="margin-bottom:10px;">
      <span class="avatar"></span>
      <div>
        <div style="font-weight:600;"><?= htmlspecialchars($currentAdmin['name'] ?? 'User') ?></div>
        <div class="role"><?= htmlspecialchars(ucfirst($currentAdmin['role'] ?? 'staff')) ?></div>
      </div>
    </div>

    <!-- Bulk upload -->
   <div style="margin-bottom:10px;">
  <?php if ($isPharmacy): ?>
    <a class="btn-outline" href="pharmacy-bulk-upload.php?store_id=<?= $store['id'] ?>">
      <i data-feather="upload"></i><span>Bulk Upload</span>
    </a>
  <?php else: ?>
    <a class="btn-outline" href="bulk-upload.php?store_id=<?= $store['id'] ?>">
      <i data-feather="upload"></i><span>Bulk Upload</span>
    </a>
  <?php endif; ?>
</div>
    <!-- Filters -->
    <form class="search-row" method="GET" action="inventory.php">
      <input type="hidden" name="store_id" value="<?= $store['id'] ?>">
      <input class="input" type="text" name="q" placeholder="Search name, brand, generic…" value="<?= htmlspecialchars($q) ?>">
     <select class="input" name="category_id">
  <option value="">All Categories</option>
  <?php foreach ($categories as $cat): ?>
    <option value="<?= $cat['id'] ?>" <?= $cat_filter == $cat['id'] ? 'selected' : '' ?>>
      <?= htmlspecialchars($cat['name']) ?>
    </option>
  <?php endforeach; ?>
</select>

      <select class="input" name="stock">
        <option value="">All Stock</option>
        <option value="in"  <?= $stock_filter === 'in'  ? 'selected' : '' ?>>In Stock</option>
        <option value="low" <?= $stock_filter === 'low' ? 'selected' : '' ?>>Low Stock</option>
        <option value="out" <?= $stock_filter === 'out' ? 'selected' : '' ?>>Out of Stock</option>
      </select>
      <?php if ($isPharmacy): ?>
        <select class="input" name="expiry">
          <option value="">All Expiry</option>
          <option value="soon"    <?= $exp_filter === 'soon' ? 'selected' : '' ?>>Expiring ≤ 60d</option>
          <option value="expired" <?= $exp_filter === 'expired' ? 'selected' : '' ?>>Expired</option>
        </select>
      <?php endif; ?>
      <button class="btn" type="submit"><i data-feather="search"></i><span>Filter</span></button>
    </form>

    <?php if (isset($_GET['msg'])): ?>
      <div class="card"><?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>

    <?php if (!$items): ?>
      <div class="card">No items match your filters. <?= $isPharmacy ? 'Click “Add Drug”.' : 'Click “Add Inventory”.' ?></div>
    <?php else: ?>
      <div class="grid">
        <?php foreach ($items as $it): ?>
          <?php
            $isOut = ((int)$it['quantity'] === 0);
            $isLow = (!$isOut && (int)$it['quantity'] <= (int)$it['low_stock_threshold']);
            $cardClass  = $isOut ? 'card out' : ($isLow ? 'card low' : 'card');
            $badgeClass = $isOut ? 'badge out' : ($isLow ? 'badge low' : 'badge');
            $badgeText  = $isOut ? 'Out of Stock' : ($isLow ? 'Low Stock' : 'In Stock');
            $displayPrice = $it['markup_price'] ?? $it['price'];

            // Pharmacy expiry badge style
            $expBadge = '';
            if ($isPharmacy && !empty($it['expiry_date'])) {
              $expTS = strtotime($it['expiry_date']);
              if ($expTS !== false) {
                if ($expTS < strtotime('today')) {
                  $expBadge = '<span class="badge out">Expired</span>';
                } elseif ($expTS <= strtotime('+60 days')) {
                  $expBadge = '<span class="badge low">Exp ≤ 60d</span>';
                }
              }
            }
          ?>
          <div class="<?= $cardClass ?>">
            <img class="thumb" src="<?= $it['image_url'] ?: 'assets/item.png' ?>" alt="">
            <div style="flex:1">
              <div style="display:flex; gap:.5rem; align-items:center; flex-wrap:wrap;">
                <div style="font-weight:600"><?= htmlspecialchars($it['name']) ?></div>
                <span class="<?= $badgeClass ?>"><?= $badgeText ?></span>
                <span class="qty">Qty: <?= (int)$it['quantity'] ?> (Low≤<?= (int)$it['low_stock_threshold'] ?>)</span>
                <?= $expBadge ?>
                <?php if ($isPharmacy && (int)$it['requires_prescription'] === 1): ?>
                  <span class="badge">Rx</span>
                <?php endif; ?>
              </div>

              <?php if ($it['brand'] || $it['generic_name'] || $it['strength'] || $it['dosage_form']): ?>
                <div class="muted pharm-meta" style="margin-top:4px;">
                  <?php if ($it['brand']): ?><span><?= htmlspecialchars($it['brand']) ?></span><?php endif; ?>
                  <?php if ($it['generic_name']): ?><span><?= htmlspecialchars($it['generic_name']) ?></span><?php endif; ?>
                  <?php if ($it['strength']): ?><span><?= htmlspecialchars($it['strength']) ?></span><?php endif; ?>
                  <?php if ($it['dosage_form']): ?><span><?= htmlspecialchars($it['dosage_form']) ?></span><?php endif; ?>
                  <?php if ($isPharmacy && !empty($it['expiry_date'])): ?>
                    <span>Exp: <?= htmlspecialchars($it['expiry_date']) ?></span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>

              <div class="price" style="margin-top:4px;">₦<?= number_format($displayPrice, 2) ?>
                <span class="muted" style="margin-left:6px;">(store ₦<?= number_format($it['price'], 2) ?>)</span>
              </div>

              <div class="item-actions" style="margin-top:.5rem; display:flex; gap:.5rem;">
                <a class="btn" href="edit-item.php?id=<?= $it['id'] ?>&store_id=<?= $store['id'] ?>"><i data-feather="edit-2"></i><span>Edit</span></a>
                <form action="delete-item.php" method="POST" onsubmit="return confirm('Delete this item?');">
                  <input type="hidden" name="id" value="<?= $it['id'] ?>">
                  <input type="hidden" name="store_id" value="<?= $store['id'] ?>">
                  <button class="btn btn-danger" type="submit"><i data-feather="trash-2"></i><span>Delete</span></button>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
</div>

<!-- Feather icons (CDN + auto-init) -->
<script src="https://unpkg.com/feather-icons" onload="feather.replace()"></script>
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
</body>
</html>
