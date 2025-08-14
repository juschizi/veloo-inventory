<?php
session_start();
require_once '../src/db.php';

$email = 'admin@app-veloo.com';     // change if needed
$newPlain = 'Admin123!';            // <-- set the password you want

$newHash = password_hash($newPlain, PASSWORD_BCRYPT);
$st = $pdo->prepare("UPDATE admins SET password_hash = ?, is_active = 1, role='admin' WHERE email = ?");
$st->execute([$newHash, $email]);

echo "OK len=" . strlen($newHash) . " prefix=" . substr($newHash,0,7);
