<?php
// Always start session first — no output before this
session_start();

// Normalize session so staff and admin both work
if (!isset($_SESSION['admin_id']) && isset($_SESSION['user_id'])) {
    $_SESSION['admin_id'] = (int)$_SESSION['user_id'];
}

// If no valid login, bounce to login
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../src/db.php';
require_once '../src/auth.php';

// Sanitize incoming POST
$store_id = (int)($_POST['store_id'] ?? 0);
assertStoreAccess($pdo, $store_id);

$category_id  = (int)($_POST['category_id'] ?? 0);
$name         = trim($_POST['name'] ?? '');
$brand        = trim($_POST['brand'] ?? '');
$price        = (float)($_POST['price'] ?? 0);
$markup_price = strlen($_POST['markup_price']) ? (float)$_POST['markup_price'] : null;
$in_stock     = (int)($_POST['in_stock'] ?? 1);
$quantity     = (int)($_POST['quantity'] ?? 0);
$low_stock    = (int)($_POST['low_stock_threshold'] ?? 5);
$description  = trim($_POST['description'] ?? '');

// 1) Fetch store type
$storeStmt = $pdo->prepare("SELECT type FROM stores WHERE id = ?");
$storeStmt->execute([$store_id]);
$storeType = $storeStmt->fetchColumn();
if (!$storeType) {
    header("Location: dashboard.php?msg=Invalid%20store");
    exit;
}

// 2) Ensure category type matches store type
$catStmt = $pdo->prepare("SELECT type FROM categories WHERE id = ?");
$catStmt->execute([$category_id]);
$catType = $catStmt->fetchColumn();
if (!$catType) {
    header("Location: add-item.php?store_id={$store_id}&msg=Category%20not%20found");
    exit;
}
if ($catType !== $storeType) {
    header("Location: add-item.php?store_id={$store_id}&msg=Category%20type%20mismatch");
    exit;
}

// Handle image upload (optional)
$image_url = null;
if (!empty($_FILES['image_file']['name']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg','jpeg','png','webp'])) {
        $newName = 'uploads/items/' . uniqid('item_') . '.' . $ext;
        if (!is_dir(__DIR__ . '/../public/uploads/items')) {
            mkdir(__DIR__ . '/../public/uploads/items', 0755, true);
        }
        if (move_uploaded_file($_FILES['image_file']['tmp_name'], __DIR__ . '/../public/' . $newName)) {
            $image_url = $newName;
        }
    }
}

// Insert into DB
$st = $pdo->prepare("
    INSERT INTO items (
        store_id, category_id, name, brand, price, markup_price,
        in_stock, quantity, low_stock_threshold, description, image_url
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$st->execute([
    $store_id, $category_id, $name, $brand, $price, $markup_price,
    $in_stock, $quantity, $low_stock, $description, $image_url
]);

// Redirect back to inventory with success
header("Location: inventory.php?store_id={$store_id}&added=1");
exit;
