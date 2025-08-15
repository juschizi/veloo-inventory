<?php
// src/db.php

// Load environment variables from .env file
$env = parse_ini_file(__DIR__ . '/../.env');

// Get each variable (fall back to default if not set)
$DB_HOST = $env['DB_HOST'] ?? 'localhost';
$DB_PORT = $env['DB_PORT'] ?? 3306;
$DB_NAME = $env['DB_NAME'] ?? '';
$DB_USER = $env['DB_USER'] ?? '';
$DB_PASS = $env['DB_PASS'] ?? '';

$dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_general_ci"
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo "<h3>Database connection failed</h3>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    exit;
}
