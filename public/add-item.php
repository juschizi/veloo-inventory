<?php
// public/add-item.php

// --- Strictly start session before ANY output ---
session_start();

// --- Accept either admin_id or user_id; normalize for backward compatibility ---
if (!isset($_SESSION['admin_id']) && isset($_SESSION['user_id'])) {
    // Old pages check admin_id; if only user_id exists, mirror it
    $_SESSION['admin_id'] = (int)$_SESSION['user_id'];
}

// --- Auth gate: require a logged-in session ---
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../src/db.php';
require_once '../src/auth.php';

// --- Resolve store, then enforce scope ---
$store_id = (int)($_GET['store_id'] ?? 0);
if ($store_id <= 0) {
    header('Location: dashboard.php?msg=Missing%20store_id');
    exit;
}

// Staff can only act on their assigned store (assertStoreAccess handles this)
assertStoreAccess($pdo, $store_id);

// --- Load store (active & not deleted) ---
$st = $pdo->prepare("SELECT id, name, logo_url FROM stores WHERE id = ? AND is_active = 1 AND is_deleted = 0");
$st->execute([$store_id]);
$store = $st->fetch(PDO::FETCH_ASSOC);
if (!$store) {
    header('Location: dashboard.php?msg=Store%20not%20found%20or%20inactive');
    exit;
}

// --- Get store type ---
$typeStmt = $pdo->prepare("SELECT type FROM stores WHERE id = ?");
$typeStmt->execute([$store_id]);
$storeType = $typeStmt->fetchColumn();

if (!$storeType) {
    header('Location: dashboard.php?msg=Invalid%20store%20type');
    exit;
}

// --- Fetch only categories matching store type ---
$catStmt = $pdo->prepare("SELECT id, name FROM categories WHERE type = ? ORDER BY name");
$catStmt->execute([$storeType]);
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
// --- Current admin (for top chip, optional) ---
$meStmt = $pdo->prepare("SELECT name, role FROM admins WHERE id = ?");
$meStmt->execute([$_SESSION['admin_id']]);
$currentAdmin = $meStmt->fetch(PDO::FETCH_ASSOC);
$isAdmin = (($currentAdmin['role'] ?? 'staff') === 'admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Add Item — <?= htmlspecialchars($store['name']) ?></title>
  <link rel="stylesheet" href="assets/styles.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    .page-head { display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin-bottom:12px; }
    .store-head { display:flex; align-items:center; gap:.6rem; }
    .store-logo { width:40px; height:40px; border-radius:10px; object-fit:cover; background:#eef2ff; }
  </style>
</head>
<body>
<div class="app">

  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="brand">
      <img src="assets/default-store.png" style="width:28px;height:28px;border-radius:8px;" alt="">
      Veloo Admin
    </div>
    <nav class="nav">
      <a href="dashboard.php"><i data-feather="home"></i><span>Home</span></a>
      <a href="inventory.php?store_id=<?= $store['id'] ?>"><i data-feather="box"></i><span>Inventory</span></a>
      <?php if ($isAdmin): ?>
        <a href="deleted-items.php"><i data-feather="archive"></i><span>Deleted Items</span></a>
        <a href="api/admin-keys.php"><i data-feather="key"></i><span>API Keys</span></a>
        <a href="api/explorer.php"><i data-feather="activity"></i><span>API Explorer</span></a>
        <a href="api/v1/docs.html"><i data-feather="book-open"></i><span>API Docs</span></a>
      <?php endif; ?>
      <a href="logout.php"><i data-feather="log-out"></i><span>Logout</span></a>
    </nav>
  </aside>

  <!-- Main -->
  <main class="main">
    <div class="page-head">
      <div class="store-head">
        <img class="store-logo" src="<?= $store['logo_url'] ?: 'assets/default-store.png' ?>" alt="">
        <h2 class="form-title" style="margin:0;">Add Inventory — <?= htmlspecialchars($store['name']) ?></h2>
      </div>
      <div class="controls">
        <a class="btn-outline" href="inventory.php?store_id=<?= $store['id'] ?>">
          <i data-feather="arrow-left"></i><span>Back</span>
        </a>
      </div>
    </div>

    <!-- Optional user chip -->
    <div class="userchip" style="margin-bottom:10px;">
      <span class="avatar"></span>
      <div>
        <div style="font-weight:600;"><?= htmlspecialchars($currentAdmin['name'] ?? 'User') ?></div>
        <div class="role"><?= htmlspecialchars(ucfirst($currentAdmin['role'] ?? 'staff')) ?></div>
      </div>
    </div>

    <div class="container-narrow">
      <div class="form-card">
        <form action="save-item.php" method="POST" enctype="multipart/form-data">
          <input type="hidden" name="store_id" value="<?= $store['id'] ?>">

          <div class="form-grid">
            <div class="field">
              <label>Category</label>
              <select class="select" name="category_id" required>
                <option value="">Select Category</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="field">
              <label>Product Name</label>
              <input class="input" type="text" name="name" required>
            </div>

            <div class="field">
              <label>Brand</label>
              <input class="input" type="text" name="brand">
            </div>

            <div class="field">
              <label>Store Price (₦)</label>
              <input class="input" type="number" step="0.01" name="price" required>
            </div>

            <div class="field">
              <label>Markup Price (₦) — optional</label>
              <input class="input" type="number" step="0.01" name="markup_price">
            </div>

            <div class="field">
              <label>Stock Available</label>
              <select class="select" name="in_stock">
                <option value="1" selected>Yes</option>
                <option value="0">No</option>
              </select>
            </div>

            <div class="field">
              <label>Quantity in stock</label>
              <input class="input" type="number" name="quantity" min="0" value="0" required>
            </div>

            <div class="field">
              <label>Low stock threshold</label>
              <input class="input" type="number" name="low_stock_threshold" min="0" value="5" required>
            </div>

            <div class="field" style="grid-column:1 / -1;">
              <label>Description</label>
              <textarea class="textarea" name="description" placeholder="Optional notes…"></textarea>
            </div>

            <div class="field">
              <label>Product Image</label>
              <input class="input" type="file" name="image_file" accept="image/*">
              <div class="hint">JPG/PNG/WebP · ≤ 4MB · will be resized</div>
            </div>

            <div class="field top-gap" style="align-self:end;">
              <button class="btn" type="submit"><i data-feather="save"></i><span>Save Item</span></button>
            </div>
          </div>
        </form>
      </div>
    </div>

  </main>
</div>

<!-- Feather icons (minimal, no emojis) -->
<script src="https://unpkg.com/feather-icons" onload="feather.replace()"></script>
</body>
</html>
