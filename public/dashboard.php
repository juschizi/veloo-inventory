<?php
session_start();
if (!isset($_SESSION['admin_id'])) { 
    header('Location: login.php'); 
    exit; 
}

require_once '../src/db.php';

// who’s logged in? (for role-based nav if you want later)
$meStmt = $pdo->prepare("SELECT name, role FROM admins WHERE id = ?");
$meStmt->execute([$_SESSION['admin_id']]);
$me = $meStmt->fetch(PDO::FETCH_ASSOC);
$isAdmin = (($me['role'] ?? 'staff') === 'admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Main Dashboard — Veloo</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <link rel="stylesheet" href="assets/styles.css">
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

  <style>
    /* Page-local tweaks on top of styles.css */
    .page { max-width:1100px; margin:24px auto; padding:0 16px; }
    .hero { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
    .grid-sections { display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:18px; margin-top:14px; }
    .section-card {
      background:var(--panel, #fff); border:1px solid var(--line, #e5e7eb); border-radius:12px;
      padding:18px; text-decoration:none; color:inherit; box-shadow:var(--shadow, 0 1px 2px rgba(0,0,0,.04));
      display:flex; align-items:center; gap:14px; transition:transform .06s ease, box-shadow .1s ease;
    }
    .section-card:hover{ transform:translateY(-1px); box-shadow:0 2px 3px rgba(0,0,0,.05), 0 10px 22px rgba(0,0,0,.08); }
    .ico-lg { width:40px; height:40px; }
    .section-title { font-weight:600; }
    .section-sub { color:var(--muted, #6b7280); font-size:.92rem; margin-top:2px; }
  </style>
</head>
<body>
  <!-- Mobile controls -->
  <button class="menu-toggle" aria-label="Open menu">
    <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true">
      <path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/>
    </svg>
  </button>
  <div class="backdrop" hidden></div>

  <div class="app">
    <!-- Sidebar -->
    <aside class="sidebar">
      <button class="sidebar-close" aria-label="Close menu">
        <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M6 6l12 12M6 18L18 6" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/>
        </svg>
      </button>

      <div class="brand">
        <img src="assets/default-store.png" style="width:28px;height:28px;border-radius:8px;" alt="">
        Veloo Admin
      </div>
      <nav class="nav">
        <a class="active" href="dashboard.php"><i data-feather="home"></i><span>Home</span></a>
        <a href="stores-dashboard.php"><i data-feather="shopping-bag"></i><span>Stores</span></a>
        <a href="pharmacies-dashboard.php"><i data-feather="activity"></i><span>Pharmacies</span></a>
        <a href="deleted-items.php"><i data-feather="archive"></i><span>Deleted Items</span></a>
        <?php if ($isAdmin): ?>
          <a href="api/admin-keys.php"><i data-feather="key"></i><span>API Keys</span></a>
          <a href="api/explorer.php"><i data-feather="flask"></i><span>API Explorer</span></a>
          <a href="api/v1/docs.html"><i data-feather="book-open"></i><span>API Docs</span></a>
        <?php endif; ?>
        <a href="logout.php"><i data-feather="log-out"></i><span>Logout</span></a>
      </nav>
    </aside>

    <!-- Main -->
    <main class="main">
      <div class="page">
        <div class="hero">
          <h1 style="margin:0;">Veloo Admin Dashboard</h1>
          <div class="userchip">
            <span class="avatar"></span>
            <div>
              <div style="font-weight:600;"><?= htmlspecialchars($me['name'] ?? 'User') ?></div>
              <div class="role"><?= htmlspecialchars(ucfirst($me['role'] ?? 'staff')) ?></div>
            </div>
          </div>
        </div>

        <div class="grid-sections">
          <a href="stores-dashboard.php" class="section-card">
            <i data-feather="shopping-bag" class="ico-lg"></i>
            <div>
              <div class="section-title">Stores</div>
              <div class="section-sub">Manage general goods and retail inventory</div>
            </div>
          </a>

          <a href="pharmacies-dashboard.php" class="section-card">
            <i data-feather="activity" class="ico-lg"></i>
            <div>
              <div class="section-title">Pharmacies</div>
              <div class="section-sub">Manage drugs, prescriptions, expiry & more</div>
            </div>
          </a>
        </div>
      </div>
    </main>
  </div>

  <!-- Feather & drawer JS -->
  <script src="https://unpkg.com/feather-icons" onload="feather.replace()"></script>
  <script>
    const sidebar  = document.querySelector('.sidebar');
    const openBtn  = document.querySelector('.menu-toggle');
    const closeBtn = document.querySelector('.sidebar-close');
    const backdrop = document.querySelector('.backdrop');

    function openSidebar(){ sidebar.classList.add('open'); backdrop.classList.add('show'); backdrop.hidden = false; }
    function closeSidebar(){ sidebar.classList.remove('open'); backdrop.classList.remove('show'); backdrop.hidden = true; }

    openBtn?.addEventListener('click', openSidebar);
    closeBtn?.addEventListener('click', closeSidebar);
    backdrop?.addEventListener('click', closeSidebar);
  </script>
</body>
</html>
