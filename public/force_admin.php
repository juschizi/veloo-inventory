<?php
require_once '../src/db.php';

// Known-good admin
$email = 'admin@app-veloo.com';
$plain = 'Admin123!';

// Upsert admin (insert if new, else update)
$hash = password_hash($plain, PASSWORD_BCRYPT);
$stmt = $pdo->prepare("
  INSERT INTO admins (name, email, password_hash, role, is_active)
  VALUES ('Main Admin', ?, ?, 'admin', 1)
  ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role='admin', is_active=1
");
$stmt->execute([$email, $hash]);

// Show sanity info
$row = $pdo->prepare("SELECT email, role, is_active, CHAR_LENGTH(password_hash) len, LEFT(password_hash,7) pref FROM admins WHERE email=?");
$row->execute([$email]);
var_dump($row->fetch());
