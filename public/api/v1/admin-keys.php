<?php
// public/api/admin-keys.php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: ../login.php'); exit; }

require_once '../../src/db.php';

// ADMIN-ONLY
$roleStmt = $pdo->prepare("SELECT role FROM admins WHERE id=?");
$roleStmt->execute([$_SESSION['admin_id']]);
$role = $roleStmt->fetchColumn();
if ($role !== 'admin') { http_response_code(403); exit('Forbidden'); }

$message = '';
$new_token_plain = null;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'create') {
    $name = trim($_POST['name'] ?? '');
    $qpm  = (int)($_POST['qpm_limit'] ?? 60);
    if ($name === '' || $qpm <= 0) {
      $message = 'Provide a name and a positive QPM limit.';
    } else {
      $token_plain = bin2hex(random_bytes(16)); // 32 hex chars
      $hash = password_hash($token_plain, PASSWORD_BCRYPT);
      $st = $pdo->prepare("INSERT INTO api_keys (name, token_hash, qpm_limit) VALUES (?,?,?)");
      $st->execute([$name, $hash, $qpm]);
      $new_token_plain = $token_plain; // show ONCE
      $message = 'API key created. Copy the token now; it will not be shown again.';
    }
  } elseif ($action === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] === 'active' ? 'active' : 'disabled';
    $pdo->prepare("UPDATE api_keys SET status=? WHERE id=?")->execute([$status, $id]);
    $message = "Key #$id set to $status.";
  } elseif ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare("DELETE FROM api_keys WHERE id=?")->execute([$id]); // cascades to usage
    $message = "Key #$id deleted.";
  }
}

// List keys
$keys = $pdo->query("SELECT id, name, status, qpm_limit, created_at, last_used_at FROM api_keys ORDER BY id DESC")->fetchAll();

// Simple usage in last 60 minutes
$usageStmt = $pdo->query("
  SELECT ak.id, ak.name, SUM(au.count) as hits_last_hour
  FROM api_keys ak
  LEFT JOIN api_usage au ON au.api_key_id = ak.id AND au.minute_ts >= (NOW() - INTERVAL 60 MINUTE)
  GROUP BY ak.id, ak.name
  ORDER BY ak.id DESC
");
$usage = [];
foreach ($usageStmt as $u) { $usage[(int)$u['id']] = (int)$u['hits_last_hour']; }
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>API Keys — Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: Poppins, sans-serif; max-width: 1000px; margin: 24px auto; padding: 0 16px; }
    h2 { margin: 0 0 12px; }
    .top { display:flex; justify-content:space-between; align-items:center; margin-bottom: 16px; }
    .btn { background:#1e40af; color:#fff; border:none; border-radius:6px; padding:.45rem .8rem; text-decoration:none; cursor:pointer; }
    .btn-outline { background:#fff; border:1px solid #1e40af; color:#1e40af; border-radius:6px; padding:.45rem .8rem; text-decoration:none; cursor:pointer; }
    .muted { color:#6b7280; }
    table { width:100%; border-collapse: collapse; margin-top: 12px; }
    th, td { border:1px solid #e5e7eb; padding:.55rem; font-size:.95rem; text-align:left; }
    th { background:#f9fafb; }
    .row-actions { display:flex; gap:.4rem; }
    .banner { background:#eef2ff; border:1px solid #c7d2fe; padding:.6rem .8rem; border-radius:8px; margin: 10px 0; }
    .danger { background:#b91c1c; color:#fff; }
    form.inline { display:inline; }
    .grid { display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 800px){ .grid { grid-template-columns: 1fr; } }
    input[type="text"], input[type="number"] { width:100%; padding:.5rem .6rem; border:1px solid #d1d5db; border-radius:6px; }
  </style>
</head>
<body>
  <div class="top">
    <h2>API Keys</h2>
    <div style="display:flex; gap:.5rem;">
      <a class="btn-outline" href="../dashboard.php">← Dashboard</a>
      <a class="btn-outline" href="v1/docs.html">API Docs</a>
      <a class="btn-outline" href="explorer.php">API Explorer</a>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="banner"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <?php if ($new_token_plain): ?>
    <div class="banner"><strong>Copy your API token now:</strong>
      <div style="font-family:monospace; font-size:0.95rem; user-select: all;"><?= htmlspecialchars($new_token_plain) ?></div>
      <div class="muted">It will not be shown again.</div>
    </div>
  <?php endif; ?>

  <h3>Create a new key</h3>
  <form method="POST" class="grid">
    <input type="hidden" name="action" value="create">
    <div>
      <label>Name</label>
      <input type="text" name="name" placeholder="e.g., Veloo App (Prod)" required>
    </div>
    <div>
      <label>QPM Limit</label>
      <input type="number" name="qpm_limit" min="1" value="120" required>
    </div>
    <div><button class="btn" type="submit">Generate Key</button></div>
  </form>

  <h3 style="margin-top:16px;">Existing keys</h3>
  <table>
    <thead>
      <tr>
        <th>ID</th><th>Name</th><th>Status</th><th>QPM</th><th>Last Used</th><th>Hits (1h)</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($keys as $k): $kid=(int)$k['id']; ?>
        <tr>
          <td><?= $kid ?></td>
          <td><?= htmlspecialchars($k['name']) ?></td>
          <td><?= htmlspecialchars($k['status']) ?></td>
          <td><?= (int)$k['qpm_limit'] ?></td>
          <td><?= htmlspecialchars($k['last_used_at'] ?: '—') ?></td>
          <td><?= $usage[$kid] ?? 0 ?></td>
          <td class="row-actions">
            <form method="POST" class="inline">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= $kid ?>">
              <input type="hidden" name="status" value="<?= $k['status'] === 'active' ? 'disabled' : 'active' ?>">
              <button class="btn-outline" type="submit"><?= $k['status'] === 'active' ? 'Disable' : 'Enable' ?></button>
            </form>
            <form method="POST" class="inline" onsubmit="return confirm('Delete this key? This cannot be undone.');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $kid ?>">
              <button class="btn danger" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>
