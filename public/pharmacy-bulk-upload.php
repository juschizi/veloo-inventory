<?php
// public/pharmacy-bulk-upload.php
session_start();
if (!isset($_SESSION['admin_id']) && isset($_SESSION['user_id'])) $_SESSION['admin_id'] = (int)$_SESSION['user_id'];
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

require_once '../src/db.php';
require_once '../src/auth.php';

$store_id = (int)($_GET['store_id'] ?? 0);
if ($store_id <= 0) { header('Location: pharmacy-dashboard.php'); exit; }

assertStoreAccess($pdo, $store_id);

// ensure this store is a pharmacy
$st = $pdo->prepare("SELECT id, name, logo_url, type FROM stores WHERE id=? AND is_active=1 AND is_deleted=0");
$st->execute([$store_id]);
$store = $st->fetch(PDO::FETCH_ASSOC);
if (!$store || $store['type'] !== 'pharmacy') {
  header('Location: pharmacy-dashboard.php?msg=Not%20a%20pharmacy'); exit;
}

// current admin chip
$me = $pdo->prepare("SELECT name, role FROM admins WHERE id=?");
$me->execute([$_SESSION['admin_id']]);
$admin = $me->fetch(PDO::FETCH_ASSOC);
$isAdmin = (($admin['role'] ?? 'staff') === 'admin');
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Pharmacy Bulk Import — <?= htmlspecialchars($store['name']) ?></title>
  <link rel="stylesheet" href="assets/styles.css">
  <script src="https://unpkg.com/feather-icons" onload="feather.replace()"></script>
</head>
<body>
<div class="app">

  <aside class="sidebar">
    <div class="brand">
      <img src="assets/default-store.png" style="width:28px;height:28px;border-radius:8px;" alt="">
      Veloo Admin
    </div>
    <nav class="nav">
      <a href="stores-dashboard.php"><i data-feather="home"></i><span>Stores</span></a>
      <a class="active" href="pharmacy-dashboard.php"><i data-feather="activity"></i><span>Pharmacies</span></a>
      <a href="inventory.php?store_id=<?= $store['id'] ?>"><i data-feather="box"></i><span>Inventory</span></a>
      <a href="deleted-items.php"><i data-feather="archive"></i><span>Deleted Items</span></a>
      <?php if ($isAdmin): ?>
        <a href="api/admin-keys.php"><i data-feather="key"></i><span>API Keys</span></a>
      <?php endif; ?>
      <a href="logout.php"><i data-feather="log-out"></i><span>Logout</span></a>
    </nav>
  </aside>

  <main class="main">
    <div class="topbar">
      <div class="h-intro">Bulk Import — <?= htmlspecialchars($store['name']) ?></div>
      <div class="userchip">
        <span class="avatar"></span>
        <div>
          <div style="font-weight:600;"><?= htmlspecialchars($admin['name'] ?? 'User') ?></div>
          <div class="role"><?= htmlspecialchars(ucfirst($admin['role'] ?? 'staff')) ?></div>
        </div>
      </div>
    </div>

    <div class="container-narrow">
      <div class="form-card">
        <div class="form-head">
          <h2 class="form-title">Upload CSV (Pharmacy)</h2>
          <div class="form-actions">
            <a class="btn-outline" href="inventory.php?store_id=<?= $store['id'] ?>"><i data-feather="arrow-left"></i><span>Back</span></a>
          </div>
        </div>

        <p class="muted" style="margin-top:-6px;">
          Download the template, fill it, then upload. Optional ZIP will map by <b>image_filename</b> column.
        </p>
        <p>
          <a class="btn-outline" href="pharmacy-bulk-template.php"><i data-feather="download"></i><span>Download CSV Template</span></a>
        </p>

        <form action="pharmacy-bulk-commit.php" method="POST" enctype="multipart/form-data">
          <input type="hidden" name="store_id" value="<?= $store['id'] ?>">

          <div class="form-grid">
            <div class="field">
              <label>CSV File</label>
              <input class="input" type="file" name="csv" accept=".csv" required>
              <div class="hint">Headers required. See template.</div>
            </div>

            <div class="field">
              <label>Images ZIP (optional)</label>
              <input class="input" type="file" id="images_zip" name="images_zip" accept=".zip" aria-label="Choose image file">
              <div class="hint">Filenames must match the <b>image_filename</b> column.</div>
            </div>

            <div class="field" style="grid-column:1 / -1;">
              <label class="checkbox">
                <input type="checkbox" name="update_existing" value="1">
                <span>Update existing items (match by SKU if present, else by Name+Brand)</span>
              </label>
            </div>

            <div class="field" style="grid-column:1 / -1;align-self:end;">
              <button class="btn" type="submit"><i data-feather="upload"></i><span>Import</span></button>
            </div>
          </div>
        </form>

        <div class="card" style="margin-top:1rem;">
          <h4>CSV Columns</h4>
          <div class="muted">
            Required: <code>category</code>, <code>price</code>, and at least one of <code>name</code> | <code>brand</code> | <code>generic_name</code>.<br>
            Optional: <code>sku</code>, <code>generic_name</code>, <code>dosage_form</code>, <code>strength</code>, <code>requires_prescription</code> (0/1),
            <code>expiry_date</code> (YYYY-MM-DD), <code>batch_number</code>, <code>markup_price</code>, <code>quantity</code>, <code>low_stock_threshold</code>,
            <code>description</code>, <code>image_filename</code>, <code>image_url</code>.
          </div>
        </div>

      </div>
    </div>
  </main>
</div>
</body>
</html>
