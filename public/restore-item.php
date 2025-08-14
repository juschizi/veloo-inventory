<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

require_once '../src/db.php';

// ADMIN-ONLY
$roleStmt = $pdo->prepare("SELECT role FROM admins WHERE id=?");
$roleStmt->execute([$_SESSION['admin_id']]);
$role = $roleStmt->fetchColumn();
if ($role !== 'admin') { http_response_code(403); exit('Forbidden'); }

$id = (int)($_POST['id'] ?? 0);
if (!$id) { header('Location: deleted-items.php'); exit; }

// Flip the flag back
$pdo->prepare("UPDATE items SET is_deleted = 0, last_updated = NOW() WHERE id = ?")->execute([$id]);

// (Optional) find the store to bounce back to its inventory after restore
$st = $pdo->prepare("SELECT store_id FROM items WHERE id=?");
$st->execute([$id]);
$storeId = (int)($st->fetchColumn() ?: 0);

if ($storeId) {
  header("Location: inventory.php?store_id=$storeId&msg=Item%20restored");
} else {
  header("Location: deleted-items.php?msg=Item%20restored");
}
exit;
