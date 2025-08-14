<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

require_once '../src/db.php';

// ADMIN-ONLY
$roleStmt = $pdo->prepare("SELECT role FROM admins WHERE id=?");
$roleStmt->execute([$_SESSION['admin_id']]);
$role = $roleStmt->fetchColumn();
if ($role !== 'admin') { http_response_code(403); exit('Forbidden'); }

$q        = trim($_GET['q'] ?? '');
$store_id = (int)($_GET['store_id'] ?? 0);
$limit    = max(1, min(100, (int)($_GET['limit'] ?? 30)));
$cursor   = (int)($_GET['cursor'] ?? 0); // simple pagination: last seen id

// preload store list for filter
$stores = $pdo->query("SELECT id, name FROM stores WHERE is_deleted=0 ORDER BY name")->fetchAll();

$params = [];
$sql = "SELECT i.id, i.store_id, i.name, i.brand, i.image_url, i.price, i.markup_price, i.quantity, i.low_stock_threshold,
               i.last_updated, s.name AS store_name
        FROM items i
        JOIN stores s ON s.id = i.store_id
        WHERE i.is_deleted = 1";

if ($store_id) { $sql .= " AND i.store_id = ?"; $params[] = $store_id; }
if ($q !== '') {
  $sql .= " AND (i.name LIKE ? OR i.brand LIKE ?)";
  $like = "%$q%"; $params[] = $like; $params[] = $like;
}
if ($cursor > 0) { $sql .= " AND i.id < ?"; $params[] = $cursor; } // reverse pagination

$sql .= " ORDER BY i.id DESC LIMIT $limit";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
$next_cursor = count($rows) ? (int)end($rows)['id'] : null;
?>
<!DOCTYPE html>
<html>
<head>
  <title>Deleted Items — Admin</title>
  <link rel="stylesheet" href="assets/styles.css">
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: Poppins, sans-serif; }
    .top { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }
    .filters { display:grid; grid-template-columns: 1fr 240px 140px; gap:.5rem; margin: .5rem 0 1rem; }
    @media (max-width: 768px){ .filters{ grid-template-columns:1fr; } }
    .grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:1rem; }
    .card { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:0.9rem; display:flex; gap:.75rem; }
    .thumb { width:72px; height:72px; object-fit:cover; border-radius:8px; background:#f3f4f6; }
    .muted { color:#6b7280; font-size:.9rem; }
    .btn { background:#1e40af; color:#fff; border:none; border-radius:6px; padding:.45rem .8rem; text-decoration:none; display:inline-block; }
    .btn-outline { background:#fff; border:1px solid #1e40af; color:#1e40af; border-radius:6px; padding:.45rem .8rem; text-decoration:none; }
    .badge { font-size:12px; padding:2px 6px; border-radius:999px; border:1px solid #d1d5db; }
    .qty { font-size:12px; color:#6b7280; }
  </style>
</head>
<body>
  <div class="top">
    <h2>Recently Deleted Items</h2>
    <div>
      <a class="btn-outline" href="dashboard.php">← Dashboard</a>
    </div>
  </div>

  <form method="GET" class="filters">
    <input type="text" name="q" placeholder="Search name or brand..." value="<?= htmlspecialchars($q) ?>">
    <select name="store_id">
      <option value="">All Stores</option>
      <?php foreach ($stores as $s): ?>
        <option value="<?= $s['id'] ?>" <?= $store_id == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <div>
      <button class="btn" type="submit">Filter</button>
    </div>
  </form>

  <?php if (!$rows): ?>
    <p class="muted">No deleted items found with these filters.</p>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($rows as $r): ?>
        <div class="card">
          <img class="thumb" src="<?= $r['image_url'] ? htmlspecialchars($r['image_url']) : 'assets/default-store.png' ?>" alt="">
          <div style="flex:1">
            <div style="display:flex; gap:.5rem; align-items:center; flex-wrap:wrap;">
              <strong><?= htmlspecialchars($r['name']) ?></strong>
              <?php if ($r['brand']): ?><span class="badge"><?= htmlspecialchars($r['brand']) ?></span><?php endif; ?>
            </div>
            <div class="muted"><?= htmlspecialchars($r['store_name']) ?></div>
            <div class="muted">₦<?= number_format($r['markup_price'] ?? $r['price'], 2) ?>
              <span class="qty"> · Qty: <?= (int)$r['quantity'] ?> · Low≤<?= (int)$r['low_stock_threshold'] ?></span>
            </div>
            <form method="POST" action="restore-item.php" style="margin-top:.5rem;">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn" type="submit">Restore</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($next_cursor): ?>
      <div style="margin-top:1rem;">
        <a class="btn-outline" href="deleted-items.php?<?= http_build_query(['q'=>$q,'store_id'=>$store_id,'limit'=>$limit,'cursor'=>$next_cursor]) ?>">Older →</a>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</body>
</html>
