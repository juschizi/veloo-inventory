<?php
// public/add-pharmacy-item.php
session_start();
if (!isset($_SESSION['admin_id']) && isset($_SESSION['user_id'])) $_SESSION['admin_id'] = (int)$_SESSION['user_id'];
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

require_once '../src/db.php';
require_once '../src/auth.php';

$store_id = (int)($_GET['store_id'] ?? 0);
if ($store_id <= 0) { header('Location: pharmacies-dashboard.php'); exit; }
assertStoreAccess($pdo, $store_id);

$st = $pdo->prepare("SELECT id, name, logo_url, type FROM stores WHERE id=? AND is_active=1 AND is_deleted=0");
$st->execute([$store_id]);
$store = $st->fetch(PDO::FETCH_ASSOC);
if (!$store || $store['type'] !== 'pharmacy') { header('Location: pharmacies-dashboard.php'); exit; }

// --- Get store type ---
$typeStmt = $pdo->prepare("SELECT type FROM stores WHERE id = ?");
$typeStmt->execute([$store_id]); // or $pharmacy_id if that’s the variable name
$storeType = $typeStmt->fetchColumn();

if (!$storeType) {
    header('Location: dashboard.php?msg=Invalid%20store%20type');
    exit;
}

// --- Fetch only categories matching store type ---
$catStmt = $pdo->prepare("SELECT id, name FROM categories WHERE type = ? ORDER BY name");
$catStmt->execute([$storeType]);
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Add Drug — <?= htmlspecialchars($store['name']) ?></title>
  <link rel="stylesheet" href="assets/styles.css">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

</head>
<body>
  <div class="container-narrow">
    <div class="form-card">
      <div class="form-head">
        <h2 class="form-title">Add Drug — <?= htmlspecialchars($store['name']) ?></h2>
        <div class="form-actions">
          <a class="btn-outline" href="inventory.php?store_id=<?= $store['id'] ?>"><i data-feather="arrow-left"></i><span>Back</span></a>
        </div>
      </div>

      <form action="save-pharmacy-item.php" method="POST" enctype="multipart/form-data">
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
            <label>Brand (Trade Name)</label>
            <input class="input" type="text" name="brand" required>
          </div>

          <div class="field">
            <label>Generic Name</label>
            <input class="input" type="text" name="generic_name" placeholder="e.g., Paracetamol">
          </div>

          <div class="field">
            <label>Dosage Form</label>
            <select class="select" name="dosage_form">
              <option value="">Select</option>
              <option>tablet</option><option>capsule</option><option>syrup</option>
              <option>injection</option><option>ointment</option><option>cream</option>
              <option>drops</option><option>device</option><option>other</option>
            </select>
          </div>

          <div class="field">
            <label>Strength</label>
            <input class="input" type="text" name="strength" placeholder="e.g., 500 mg">
          </div>

          <div class="field">
            <label>Requires Prescription (Rx)</label>
            <select class="select" name="requires_prescription">
              <option value="0" selected>No</option>
              <option value="1">Yes</option>
            </select>
          </div>

          <div class="field">
            <label>Expiry Date</label>
            <input class="input" type="date" name="expiry_date">
          </div>

          <div class="field">
            <label>Batch Number</label>
            <input class="input" type="text" name="batch_number">
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
            <label>Quantity in stock</label>
            <input class="input" type="number" name="quantity" min="0" value="0" required>
          </div>

          <div class="field">
            <label>Low stock threshold</label>
            <input class="input" type="number" name="low_stock_threshold" min="0" value="5" required>
          </div>

          <div class="field" style="grid-column:1 / -1;">
            <label>Description / Notes</label>
            <textarea class="textarea" name="description"></textarea>
          </div>

          <div class="field">
            <label>Product Image</label>
            <input class="input" type="file" name="image_file" accept="image/*">
            <div class="hint">JPG/PNG/WebP · ≤ 4MB · will be resized</div>
          </div>

          <div class="field top-gap" style="align-self:end;">
            <button class="btn" type="submit"><i data-feather="save"></i><span>Save Drug</span></button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <script src="https://unpkg.com/feather-icons" onload="feather.replace()"></script>
</body>
</html>
