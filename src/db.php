<?php
$pdo = new PDO(
    "mysql:host=localhost;dbname=veloo_inventory;charset=utf8mb4",
    'root',
    'sadlips89',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);
