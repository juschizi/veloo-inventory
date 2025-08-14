<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
require_once '../src/db.php';

$roleStmt = $pdo->prepare("SELECT role FROM admins WHERE id=?");
$roleStmt->execute([$_SESSION['admin_id']]);
$role = $roleStmt->fetchColumn();
if ($role !== 'admin') { header('Location: dashboard.php?msg=Permission%20denied'); exit; }
?>
<!DOCTYPE html>
<html>
<head>
  <title>Add Store</title>
  <link rel="stylesheet" href="assets/styles.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
  <div class="container-narrow">
    <div class="form-card">
      <div class="form-head">
        <h2 class="form-title">Add New Store</h2>
        <div class="form-actions">
          <a class="btn-outline" href="dashboard.php">← Back</a>
        </div>
      </div>

      <form action="save-store.php" method="POST" enctype="multipart/form-data">
        <div class="form-grid">
          <div class="field">
            <label>Store Name</label>
            <input class="input" type="text" name="name" required>
          </div>

          <div class="field">
            <label>Phone</label>
            <input class="input" type="text" name="contact_phone" required>
          </div>

          <div class="field" style="grid-column:1 / -1;">
            <label>Address</label>
            <textarea class="textarea" name="address" required></textarea>
          </div>

          <div class="field">
            <label>Email</label>
            <input class="input" type="email" name="contact_email">
          </div>

          <div class="field">
            <label>Latitude (optional)</label>
            <input class="input" type="text" name="lat">
          </div>

          <div class="field">
            <label>Longitude (optional)</label>
            <input class="input" type="text" name="lng">
          </div>

          <div class="field">
            <label>Store Logo (optional)</label>
            <input class="input" type="file" name="logo" accept="image/*">
          </div>

          <div class="field top-gap" style="align-self:end;">
            <button class="btn" type="submit">Save Store</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
