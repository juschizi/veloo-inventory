<?php
// public/save-pharmacy-item.php
session_start();
if (!isset($_SESSION['admin_id']) && isset($_SESSION['user_id'])) $_SESSION['admin_id'] = (int)$_SESSION['user_id'];
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

require_once '../src/db.php';
require_once '../src/auth.php';

$store_id = (int)($_POST['store_id'] ?? 0);
assertStoreAccess($pdo, $store_id);

// Ensure store is a pharmacy
$st = $pdo->prepare("SELECT type FROM stores WHERE id=? AND is_active=1 AND is_deleted=0");
$st->execute([$store_id]);
$type = $st->fetchColumn();
if ($type !== 'pharmacy') { header("Location: stores-dashboard.php?msg=Not%20a%20pharmacy"); exit; }

$category_id = (int)($_POST['category_id'] ?? 0);
$brand       = trim($_POST['brand'] ?? '');
$generic     = trim($_POST['generic_name'] ?? '');
$dosage_form = trim($_POST['dosage_form'] ?? '');
$strength    = trim($_POST['strength'] ?? '');
$rx          = (int)($_POST['requires_prescription'] ?? 0);
$expiry      = $_POST['expiry_date'] ?? null;
$batch       = trim($_POST['batch_number'] ?? '');
$price       = (float)($_POST['price'] ?? 0);
$markup      = strlen($_POST['markup_price'] ?? '') ? (float)$_POST['markup_price'] : null;
$qty         = (int)($_POST['quantity'] ?? 0);
$lowthr      = (int)($_POST['low_stock_threshold'] ?? 5);
$desc        = trim($_POST['description'] ?? '');
$name        = $brand !== '' ? $brand : ($generic !== '' ? $generic : 'Unnamed Drug');

$in_stock = $qty > 0 ? 1 : 0;

// Image (optional)
$image_url = null;
if (!empty($_FILES['image_file']['name']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg','jpeg','png','webp'])) {
        $rel = 'uploads/items/' . uniqid('drug_') . '.' . $ext;
        $dest = __DIR__ . '/../' . $rel;
        if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0755, true);
        if (move_uploaded_file($_FILES['image_file']['tmp_name'], $dest)) $image_url = $rel;
    }
}

$sql = "INSERT INTO items
  (store_id, category_id, name, brand, generic_name, dosage_form, strength, requires_prescription,
   expiry_date, batch_number, price, markup_price, in_stock, quantity, low_stock_threshold, description, image_url)
  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

$st = $pdo->prepare($sql);
$st->execute([
  $store_id, $category_id, $name, $brand ?: null, $generic ?: null, $dosage_form ?: null, $strength ?: null, $rx,
  ($expiry ?: null), ($batch ?: null), $price, $markup, $in_stock, $qty, $lowthr, ($desc ?: null), $image_url
]);

header("Location: inventory.php?store_id={$store_id}&msg=Drug%20added");
exit;
