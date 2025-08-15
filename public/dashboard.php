<?php
session_start();
if (!isset($_SESSION['admin_id'])) { 
    header('Location: login.php'); 
    exit; 
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Main Dashboard — Veloo</title>
  <link rel="stylesheet" href="assets/styles.css">
  <style>
    .main-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 2rem;
      margin-top: 3rem;
    }
    .main-card {
      background: white;
      border-radius: 12px;
      padding: 2rem;
      text-align: center;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      cursor: pointer;
      text-decoration: none;
      color: inherit;
    }
    .main-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 4px 14px rgba(0,0,0,0.12);
    }
    .main-card h2 {
      margin: 1rem 0 0.5rem;
      font-size: 1.4rem;
    }
    .main-card p {
      font-size: 0.95rem;
      color: #555;
    }
    .main-card .icon {
      font-size: 3rem;
      margin-bottom: 1rem;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>Veloo Admin Dashboard</h1>
    <div class="main-grid">
      <a href="stores-dashboard.php" class="main-card">
        <div class="icon">🛍️</div>
        <h2>Stores</h2>
        <p>Manage general goods and retail inventory</p>
      </a>
      <a href="pharmacies-dashboard.php" class="main-card">
        <div class="icon">💊</div>
        <h2>Pharmacieswww</h2>
        <p>Manage drug inventory, prescriptions, and pharmacy orders</p>
      </a>
    </div>
  </div>
</body>
</html>
