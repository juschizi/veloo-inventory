<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

require_once '../src/db.php';
require_once '../src/auth.php';

$isAdmin = ($_SESSION['role'] ?? 'staff') === 'admin';
if (!$isAdmin) { header('Location: dashboard.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $logo_url = trim($_POST['logo_url']);
    $license_number = trim($_POST['license_number']);
    $pharmacist_name = trim($_POST['pharmacist_name']);
    $contact_phone = trim($_POST['contact_phone']);
    $contact_email = trim($_POST['contact_email']);
    $is_247 = isset($_POST['is_247']) ? 1 : 0;
    $delivery_time = trim($_POST['delivery_time']);

    if ($name && $license_number) {
        $stmt = $pdo->prepare("INSERT INTO stores 
            (name, logo_url, type, license_number, pharmacist_name, contact_phone, contact_email, is_247, delivery_time, is_active, is_deleted)
            VALUES (?, ?, 'pharmacy', ?, ?, ?, ?, ?, ?, 1, 0)");
        $stmt->execute([
            $name, $logo_url, $license_number, $pharmacist_name, $contact_phone, $contact_email, $is_247, $delivery_time
        ]);
        header('Location: pharmacies-dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Add Pharmacy</title>
  <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
<div class="container-narrow">
  <div class="form-card">
    <div class="form-head">
      <h2 class="form-title">Add Pharmacy</h2>
      <div class="form-actions">
        <a class="btn-outline" href="pharmacies-dashboard.php">← Back</a>
      </div>
    </div>

    <form action="" method="POST">
      <div class="form-grid">
        <div class="field">
          <label>Pharmacy Name</label>
          <input class="input" type="text" name="name" required>
        </div>
        <div class="field">
          <label>Logo URL</label>
          <input class="input" type="text" name="logo_url" placeholder="Optional image link">
        </div>
        <div class="field">
          <label>License Number</label>
          <input class="input" type="text" name="license_number" required>
        </div>
        <div class="field">
          <label>Pharmacist in Charge</label>
          <input class="input" type="text" name="pharmacist_name" required>
        </div>
        <div class="field">
          <label>Contact Phone</label>
          <input class="input" type="text" name="contact_phone">
        </div>
        <div class="field">
          <label>Contact Email</label>
          <input class="input" type="email" name="contact_email">
        </div>
        <div class="field">
          <label>24/7 Operation</label>
          <input type="checkbox" name="is_247" value="1">
        </div>
        <div class="field">
          <label>Delivery Time Promise</label>
          <input class="input" type="text" name="delivery_time" placeholder="e.g., Under 30 mins">
        </div>
        <div class="field" style="grid-column:1 / -1;">
          <button class="btn" type="submit">Save Pharmacy</button>
        </div>
      </div>
    </form>
  </div>
</div>
</body>
</html>
