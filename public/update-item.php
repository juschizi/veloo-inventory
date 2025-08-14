<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

require_once '../src/db.php';

// admin-only
$roleStmt = $pdo->prepare("SELECT role FROM admins WHERE id=?");
$roleStmt->execute([$_SESSION['admin_id']]);
$role = $roleStmt->fetchColumn();
if ($role !== 'admin') {
  header('Location: dashboard.php?msg=Permission%20denied'); exit;
}
$id           = (int)($_POST['id'] ?? 0);
$store_id     = (int)($_POST['store_id'] ?? 0);
$category_id  = (int)($_POST['category_id'] ?? 0);
$name         = trim($_POST['name'] ?? '');
$brand        = trim($_POST['brand'] ?? '');
$description  = trim($_POST['description'] ?? '');
$price        = $_POST['price'] ?? null;
$markup_price = $_POST['markup_price'] !== '' ? $_POST['markup_price'] : null;
$in_stock     = (int)($_POST['in_stock'] ?? 1);
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
$low_stock_threshold = isset($_POST['low_stock_threshold']) ? (int)$_POST['low_stock_threshold'] : 5;
$in_stock = $quantity > 0 ? 1 : 0;


if (!$id || !$store_id || !$category_id || !$name || $price === null) {
  header("Location: edit-item.php?id=$id&store_id=$store_id&error=1");
  exit;
}

$image_url = null;
if (!empty($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
  $image_url = saveCompressedImage($_FILES['image_file']);
  $dir = 'uploads/items';
  if (!is_dir($dir)) { mkdir($dir, 0755, true); }
  $filename = uniqid() . '_' . preg_replace('/[^A-Za-z0-9._-]/','_', $_FILES['image']['name']);
  $target = $dir . '/' . $filename;
  if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
    $image_url = $target;
  }
}
if ($image_url) {
  $sql = "UPDATE items SET category_id=?, name=?, brand=?, description=?, price=?, markup_price=?, in_stock=?, quantity=?, low_stock_threshold=?, image_url=?, last_updated=NOW()
          WHERE id=? AND store_id=?";
  $params = [$category_id,$name,$brand,$description,$price,$markup_price,$in_stock,$quantity,$low_stock_threshold,$image_url,$id,$store_id];
} else {
  $sql = "UPDATE items SET category_id=?, name=?, brand=?, description=?, price=?, markup_price=?, in_stock=?, quantity=?, low_stock_threshold=?, last_updated=NOW()
          WHERE id=? AND store_id=?";
  $params = [$category_id,$name,$brand,$description,$price,$markup_price,$in_stock,$quantity,$low_stock_threshold,$id,$store_id];
}


$stmt = $pdo->prepare($sql);
$stmt->execute($params);

header("Location: inventory.php?store_id=$store_id&msg=Item%20updated");
exit;
