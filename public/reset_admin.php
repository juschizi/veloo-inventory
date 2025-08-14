<?php
require_once '../src/db.php';

$email = 'chizi@example.com';
$newPlain = 'Test1234!';

$newHash = password_hash($newPlain, PASSWORD_BCRYPT);

$st = $pdo->prepare("UPDATE admins SET password_hash = ?, is_active = 1, role='admin' WHERE email = ?");
$st->execute([$newHash, $email]);

echo "OK len=" . strlen($newHash) . " prefix=" . substr($newHash,0,7);
