<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$userName = $_SESSION['name'] ?? 'Guest';
$userRole = $_SESSION['role'] ?? 'Unknown';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Veloo Inventory Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f4f6f8;
            margin: 0; padding: 0;
            color: #333;
        }
        header {
            background: #fff;
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .role-tag {
            background: <?= ($userRole === 'admin') ? '#1976d2' : '#9e9e9e'; ?>;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
        }
        main { padding: 25px; }
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        button, .btn {
            background: #1976d2;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        button:hover, .btn:hover { background: #125a9c; }
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table th, .table td {
            padding: 10px;
            border-bottom: 1px solid #eaeaea;
        }
    </style>
</head>
<body>
<header>
    <h1>Veloo Inventory</h1>
    <div>
        <span><?= htmlspecialchars($userName) ?></span>
        <span class="role-tag"><?= ucfirst($userRole) ?></span>
    </div>
</header>
<main>
