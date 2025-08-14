<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

require_once '../src/db.php';
require_once '../src/auth.php';

$store_id = (int)($_POST['id'] ?? 0);
if (!$store_id) { header('Location: dashboard.php'); exit; }

/** Enforce scope */
assertStoreAccess($pdo, $store_id);

/** proceed with your update logic… */

$id            = (int)($_POST['id'] ?? 0);
$name          = trim($_POST['name'] ?? '');
$address       = trim($_POST['address'] ?? '');
$contact_phone = trim($_POST['contact_phone'] ?? '');
$contact_email = trim($_POST['contact_email'] ?? '');
$lat = isset($_POST['lat']) && $_POST['lat'] !== '' ? (float)$_POST['lat'] : null;
$lng = isset($_POST['lng']) && $_POST['lng'] !== '' ? (float)$_POST['lng'] : null;


if (!$id || !$name || !$address || !$contact_phone) {
  header("Location: edit-store.php?id=$id&error=1");
  exit;
}

$logo_url = null;
if (!empty($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
  $dir = 'uploads/stores';
  if (!is_dir($dir)) { mkdir($dir, 0755, true); }
  $filename = uniqid() . '_' . preg_replace('/[^A-Za-z0-9._-]/','_', $_FILES['logo']['name']);
  $target = $dir . '/' . $filename;
  if (move_uploaded_file($_FILES['logo']['tmp_name'], $target)) {
    $logo_url = $target;
  }
}

if ($logo_url) {
  $sql = "UPDATE stores SET name=?, address=?, contact_phone=?, contact_email=?, lat=?, lng=?, logo_url=? WHERE id=?";
  $params = [$name, $address, $contact_phone, $contact_email, $lat, $lng, $logo_url, $id];
} else {
  $sql = "UPDATE stores SET name=?, address=?, contact_phone=?, contact_email=?, lat=?, lng=? WHERE id=?";
  $params = [$name, $address, $contact_phone, $contact_email, $lat, $lng, $id];
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

header("Location: inventory.php?store_id=$id&msg=Store%20updated");
exit;
