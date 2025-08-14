<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

require_once '../src/db.php';
require_once '../src/auth.php';

$store_id = (int)($_GET['id'] ?? 0);
if (!$store_id) { header('Location: dashboard.php'); exit; }
assertStoreAccess($pdo, $store_id);

$stmt = $pdo->prepare("SELECT * FROM stores WHERE id = ?");
$stmt->execute([$store_id]);
$store = $stmt->fetch();
if (!$store) { header('Location: dashboard.php'); exit; }
?>
<!DOCTYPE html>
<html>
<head>
  <title>Edit Store — <?= htmlspecialchars($store['name']) ?></title>
  <link rel="stylesheet" href="assets/styles.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
  <div class="container-narrow">
    <div class="form-card">
      <div class="form-head">
        <h2 class="form-title">Edit Store — <?= htmlspecialchars($store['name']) ?></h2>
        <div class="form-actions">
          <a class="btn-outline" href="inventory.php?store_id=<?= $store['id'] ?>">← Back</a>
        </div>
      </div>

      <form action="update-store.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= $store['id'] ?>">

        <div class="form-grid">
          <div class="field">
            <label>Store Name</label>
            <input class="input" type="text" name="name" value="<?= htmlspecialchars($store['name']) ?>" required>
          </div>

          <div class="field">
            <label>Phone</label>
            <input class="input" type="text" name="contact_phone" value="<?= htmlspecialchars($store['contact_phone']) ?>" required>
          </div>

          <div class="field" style="grid-column:1 / -1;">
            <label>Address</label>
            <textarea class="textarea" name="address" required><?= htmlspecialchars($store['address']) ?></textarea>
          </div>

          <div class="field">
            <label>Email</label>
            <input class="input" type="email" name="contact_email" value="<?= htmlspecialchars($store['contact_email']) ?>">
          </div>

          <div class="field">
            <label>Latitude</label>
            <input class="input" type="text" name="lat" value="<?= htmlspecialchars((string)($store['lat'] ?? '')) ?>">
          </div>

          <div class="field">
            <label>Longitude</label>
            <input class="input" type="text" name="lng" value="<?= htmlspecialchars((string)($store['lng'] ?? '')) ?>">
          </div>

          <div class="field">
            <label>Current Logo</label>
            <?php if ($store['logo_url']): ?>
              <img class="preview" src="<?= htmlspecialchars($store['logo_url']) ?>" alt="Logo">
            <?php else: ?>
              <div class="hint">No logo uploaded</div>
            <?php endif; ?>
          </div>

          <div class="field">
            <label>Replace Logo (optional)</label>
            <input class="input" type="file" name="logo" accept="image/*">
          </div>

          <div class="field top-gap" style="align-self:end;">
            <button class="btn" type="submit">Update Store</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
