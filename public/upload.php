<?php
require_once '../src/db.php';

$store_id = $_POST['store_id'];
$category_id = $_POST['category_id'];
$name = $_POST['name'];
$brand = $_POST['brand'];
$description = $_POST['description'];
$price = $_POST['price'];
$markup_price = $_POST['markup_price'] ?: null;
$in_stock = $_POST['in_stock'];

$image_url = null;

if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
    $uploads_dir = 'uploads';
    if (!is_dir($uploads_dir)) {
        mkdir($uploads_dir, 0755, true);
    }

    $filename = uniqid() . '_' . basename($_FILES['image']['name']);
    $target = $uploads_dir . '/' . $filename;

    if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
        $image_url = $target;
    }
}

$stmt = $pdo->prepare("INSERT INTO items 
    (store_id, category_id, name, brand, description, price, markup_price, image_url, in_stock)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmt->execute([
    $store_id,
    $category_id,
    $name,
    $brand,
    $description,
    $price,
    $markup_price,
    $image_url,
    $in_stock
]);

header("Location: add-item.php?success=1");
exit;
