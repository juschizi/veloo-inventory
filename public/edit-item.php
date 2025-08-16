<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

require_once '../src/db.php';
require_once '../src/auth.php';

$item_id  = (int)($_GET['id'] ?? 0);
$store_id = (int)($_GET['store_id'] ?? 0);
if (!$item_id || !$store_id) { header('Location: dashboard.php'); exit; }

assertStoreAccess($pdo, $store_id);

/** Load the item (not deleted) */
$it = $pdo->prepare("SELECT * FROM items WHERE id = ? AND store_id = ? AND is_deleted = 0");
$it->execute([$item_id, $store_id]);
$item = $it->fetch();
if (!$item) { header("Location: inventory.php?store_id=$store_id"); exit; }

/** Get the store + its type so we can filter categories */
$st = $pdo->prepare("SELECT id, name, type FROM stores WHERE id = ? AND is_active = 1 AND is_deleted = 0");
$st->execute([$store_id]);
$store = $st->fetch();
if (!$store) { header('Location: dashboard.php'); exit; }
$storeType = $store['type'];  // 'store' | 'pharmacy'

/** Fetch ONLY categories for this store type */
$catStmt = $pdo->prepare("SELECT id, name FROM categories WHERE type = ? ORDER BY name");
$catStmt->execute([$storeType]);
$categories = $catStmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Edit Item — <?= htmlspecialchars($item['name']) ?></title>
  <link rel="stylesheet" href="assets/styles.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
  <div class="container-narrow">
    <div class="form-card">
      <div class="form-head">
        <h2 class="form-title">Edit Item — <?= htmlspecialchars($item['name']) ?></h2>
        <div class="form-actions">
          <a class="btn-outline" href="inventory.php?store_id=<?= $store_id ?>">← Back</a>
        </div>
      </div>

      <form action="update-item.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= $item['id'] ?>">
        <input type="hidden" name="store_id" value="<?= $store_id ?>">

        <div class="form-grid">
          <div class="field">
            <label>Category</label>
            <select class="select" name="category_id" required>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= ($cat['id']==$item['category_id'])?'selected':'' ?>>
                  <?= htmlspecialchars($cat['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label>Product Name</label>
            <input class="input" type="text" name="name" value="<?= htmlspecialchars($item['name']) ?>" required>
          </div>

          <div class="field">
            <label>Brand</label>
            <input class="input" type="text" name="brand" value="<?= htmlspecialchars($item['brand']) ?>">
          </div>

          <div class="field">
            <label>Store Price (₦)</label>
            <input class="input" type="number" step="0.01" name="price" value="<?= htmlspecialchars($item['price']) ?>" required>
          </div>

          <div class="field">
            <label>Markup Price (₦)</label>
            <input class="input" type="number" step="0.01" name="markup_price" value="<?= htmlspecialchars($item['markup_price']) ?>">
          </div>

          <div class="field">
            <label>Stock Available</label>
            <select class="select" name="in_stock">
              <option value="1" <?= $item['in_stock'] ? 'selected' : '' ?>>Yes</option>
              <option value="0" <?= !$item['in_stock'] ? 'selected' : '' ?>>No</option>
            </select>
          </div>

          <div class="field">
            <label>Quantity in stock</label>
            <input class="input" type="number" name="quantity" min="0" value="<?= (int)$item['quantity'] ?>" required>
          </div>

          <div class="field">
            <label>Low stock threshold</label>
            <input class="input" type="number" name="low_stock_threshold" min="0" value="<?= (int)$item['low_stock_threshold'] ?>" required>
          </div>

          <div class="field" style="grid-column:1 / -1;">
            <label>Description</label>
            <textarea class="textarea" name="description"><?= htmlspecialchars($item['description']) ?></textarea>
          </div>

          <div class="field">
            <label>Current Image</label>
            <?php if ($item['image_url']): ?>
              <img class="preview" src="<?= htmlspecialchars($item['image_url']) ?>" alt="">
            <?php else: ?>
              <div class="hint">No image</div>
            <?php endif; ?>
          </div>

          <div class="field">
            <label>Replace Image (optional)</label>
            <input class="input" type="file" name="image_file" accept="image/*">
          </div>

          <div class="field top-gap" style="align-self:end;">
            <button class="btn" type="submit">Update Item</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
