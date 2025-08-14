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
$name = $_POST['name'];
$address = $_POST['address'];
$contact_phone = $_POST['contact_phone'];
$contact_email = $_POST['contact_email'];
$lat = $_POST['lat'] ?: null;
$lng = $_POST['lng'] ?: null;

$logo_url = null;

if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
    $uploads_dir = 'uploads/stores';
    if (!is_dir($uploads_dir)) {
        mkdir($uploads_dir, 0755, true);
    }

    $filename = uniqid() . '_' . basename($_FILES['logo']['name']);
    $target = $uploads_dir . '/' . $filename;

    if (move_uploaded_file($_FILES['logo']['tmp_name'], $target)) {
        $logo_url = $target;
    }
}

$stmt = $pdo->prepare("INSERT INTO stores (name, address, contact_phone, contact_email, lat, lng, logo_url) 
VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([
  $name, $address, $contact_phone, $contact_email, $lat, $lng, $logo_url
]);

header('Location: dashboard.php');
exit;
