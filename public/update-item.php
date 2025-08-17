<?php
// public/update-item.php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

require_once '../src/db.php';
// If you want staff to be able to edit their assigned store, also include:
// require_once '../src/auth.php';

//
// OPTIONAL: admin-only wall. If you want staff editing too, remove this block
//
$roleStmt = $pdo->prepare("SELECT role FROM admins WHERE id=?");
$roleStmt->execute([$_SESSION['admin_id']]);
$role = $roleStmt->fetchColumn();
if ($role !== 'admin') {
  // If you prefer staff with store scoping, comment this out and use assertStoreAccess below.
  // http_response_code(403); exit('Forbidden');
}

//
// Helper to save + compress image. Returns relative web path (e.g. "uploads/items/abc.jpg") or null.
//
function saveCompressedImage(array $file, string $relDir = 'uploads/items', int $maxDim = 900, int $quality = 82): ?string {
  if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;

  // Ensure directory exists
  $absDir = __DIR__ . '/' . trim($relDir, '/');
  if (!is_dir($absDir) && !mkdir($absDir, 0755, true)) {
    error_log('Failed to create upload dir: ' . $absDir);
    return null;
  }

  // Basic MIME/type guard
  $info = @getimagesize($file['tmp_name']);
  if (!$info) return null;
  [$w, $h, $type] = $info;

  // Try GD compression path
  $ext = 'jpg';
  $src = null;
  if (function_exists('imagecreatefromjpeg') && function_exists('imagecreatetruecolor')) {
    switch ($type) {
      case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($file['tmp_name']); break;
      case IMAGETYPE_PNG:  $src = @imagecreatefrompng($file['tmp_name']);  break;
      case IMAGETYPE_WEBP: if (function_exists('imagecreatefromwebp')) $src = @imagecreatefromwebp($file['tmp_name']); break;
    }
  }

  $fname = uniqid('item_', true) . '.' . $ext;
  $absPath = $absDir . '/' . $fname;
  $relPath = trim($relDir, '/') . '/' . $fname;

  if ($src) {
    $scale = min($maxDim / max($w, $h), 1);
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

    // Always output JPEG
    if (!imagejpeg($dst, $absPath, $quality)) {
      // Fallback to plain move
      imagedestroy($src); imagedestroy($dst);
      $safeName = uniqid('item_', true) . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file['name']);
      $absPath = $absDir . '/' . $safeName;
      $relPath = trim($relDir, '/') . '/' . $safeName;
      if (!move_uploaded_file($file['tmp_name'], $absPath)) return null;
      return $relPath;
    }

    imagedestroy($src); imagedestroy($dst);
    return $relPath;
  }

  // If we got here, no GD or unsupported type: just move with a sanitized name
  $safeName = uniqid('item_', true) . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file['name']);
  $absPath = $absDir . '/' . $safeName;
  $relPath = trim($relDir, '/') . '/' . $safeName;
  if (!move_uploaded_file($file['tmp_name'], $absPath)) return null;
  return $relPath;
}

//
// Read & validate form
//
$id           = (int)($_POST['id'] ?? 0);
$store_id     = (int)($_POST['store_id'] ?? 0);
$category_id  = (int)($_POST['category_id'] ?? 0);
$name         = trim($_POST['name'] ?? '');
$brand        = trim($_POST['brand'] ?? '');
$description  = trim($_POST['description'] ?? '');
$price        = $_POST['price'] ?? null;
$markup_price = ($_POST['markup_price'] ?? '') !== '' ? (float)$_POST['markup_price'] : null;
$in_stock     = (int)($_POST['in_stock'] ?? 1);
$quantity     = isset($_POST['quantity']) ? max(0, (int)$_POST['quantity']) : 0;
$low_stock_threshold = isset($_POST['low_stock_threshold']) ? max(0, (int)$_POST['low_stock_threshold']) : 5;

// Derive in_stock from quantity (your convention)
$in_stock = $quantity > 0 ? 1 : 0;

// Guard
if (!$id || !$store_id || !$category_id || $name === '' || $price === null || !is_numeric($price)) {
  header("Location: edit-item.php?id=$id&store_id=$store_id&error=1");
  exit;
}

// If you want staff scope enforcement instead of admin-only, add:
// require_once '../src/auth.php';
// assertStoreAccess($pdo, $store_id);

//
// Handle optional new image (input name="image_file")
//
$newImagePath = null;
if (!empty($_FILES['image_file']['name']) && ($_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
  $newImagePath = saveCompressedImage($_FILES['image_file'], 'uploads/items');
}

//
// Build and run update
//
if ($newImagePath) {
  $sql = "UPDATE items SET
            category_id=?, name=?, brand=?, description=?,
            price=?, markup_price=?, in_stock=?, quantity=?, low_stock_threshold=?,
            image_url=?, last_updated=NOW()
          WHERE id=? AND store_id=?";
  $params = [
    $category_id, $name, $brand ?: null, $description ?: null,
    (float)$price, $markup_price, $in_stock, $quantity, $low_stock_threshold,
    $newImagePath, $id, $store_id
  ];
} else {
  $sql = "UPDATE items SET
            category_id=?, name=?, brand=?, description=?,
            price=?, markup_price=?, in_stock=?, quantity=?, low_stock_threshold=?,
            last_updated=NOW()
          WHERE id=? AND store_id=?";
  $params = [
    $category_id, $name, $brand ?: null, $description ?: null,
    (float)$price, $markup_price, $in_stock, $quantity, $low_stock_threshold,
    $id, $store_id
  ];
}

try {
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  header("Location: inventory.php?store_id=$store_id&msg=Item%20updated");
  exit;
} catch (Throwable $e) {
  // Log server-side and show a friendly message
  error_log('update-item failed: ' . $e->getMessage());
  header("Location: edit-item.php?id=$id&store_id=$store_id&error=update_failed");
  exit;
}
