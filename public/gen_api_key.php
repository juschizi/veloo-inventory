<?php
require_once '../src/db.php';

$name = 'Veloo App';
$token_plain = bin2hex(random_bytes(16)); // show this ONCE to your app
$hash = password_hash($token_plain, PASSWORD_BCRYPT);

$st = $pdo->prepare("INSERT INTO api_keys (name, token_hash, qpm_limit) VALUES (?,?,?)");
$st->execute([$name, $hash, 120]); // 120 req/min for your app

echo "API_KEY=".$token_plain.PHP_EOL; // copy this into your app config
