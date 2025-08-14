<?php
require_once '../src/db.php';
$email = 'admin@app-veloo.com';
$plain = 'Admin123!';

$stmt = $pdo->prepare("SELECT password_hash FROM admins WHERE email=? AND is_active=1");
$stmt->execute([$email]);
$hash = $stmt->fetchColumn();

var_dump([
  'db' => $pdo->query("SELECT DATABASE()")->fetchColumn(),
  'hash_len' => strlen($hash ?? ''),
  'hash_prefix' => substr($hash ?? '', 0, 7),
  'verify' => password_verify($plain, $hash ?? ''),
]);
