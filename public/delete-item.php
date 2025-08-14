<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
require_once '../src/db.php'; require_once '../src/auth.php';

$id = (int)($_POST['id'] ?? 0);
$store_id = (int)($_POST['store_id'] ?? 0);
assertStoreAccess($pdo, $store_id);

$pdo->prepare("UPDATE items SET is_deleted = 1 WHERE id = ? AND store_id = ?")
    ->execute([$id, $store_id]);

header("Location: inventory.php?store_id=$store_id&msg=Item%20removed");
exit;
