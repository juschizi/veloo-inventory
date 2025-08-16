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
$stores = $pdo->query("SELECT id, name FROM stores WHERE is_deleted=0 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$params = [];
$sql = "SELECT i.id, i.store_id, i.name, i.brand, i.image_url, i.price, i.markup_price, i.quantity, i.low_stock_threshold,
               i.last_updated, s.name AS store_name
        FROM items i
        JOIN stores s ON s.id = i.store_id
        WHERE i.is_deleted = 1";

if ($store_id) { $sql .= " AND i.store_id = ?"; $params[] = $store_id; }
if ($q !== '') {
  $sql .= " AND (i.name LIKE ? OR i.brand LIKE ?)";
  $like = '%' . $q . '%';   // <-- fixed
  $params[] = $like;
  $params[] = $like;
}
if ($cursor > 0) { $sql .= " AND i.id < ?"; $params[] = $cursor; } // reverse pagination

$sql .= " ORDER BY i.id DESC LIMIT $limit";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$next_cursor = count($rows) ? (int)end($rows)['id'] : null;
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Deleted Items — Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <link rel="stylesheet" href="assets/styles.css">
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

  <style>
    :root{
      --bg:#f8fafc;
      --panel:#ffffff;
      --line:#e5e7eb;
      --muted:#6b7280;
      --ink:#0f172a;
      --primary:#1e40af;
      --primary-ink:#fff;
      --radius:12px;
      --shadow:0 1px 2px rgba(0,0,0,.04), 0 6px 16px rgba(0,0,0,.06);
    }
    body{ background:var(--bg); color:var(--ink); font-family:Poppins,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif; }

    .page{ max-width:1100px; margin:32px auto; padding:0 16px; }

    .top{
      display:flex; align-items:center; justify-content:space-between;
      margin-bottom:16px; padding-bottom:10px; border-bottom:1px solid var(--line);
    }
    .top h2{ margin:0; font-size:1.35rem; }

    .filters{
      display:grid; gap:10px; grid-template-columns: 1fr 260px 140px;
      background:var(--panel); border:1px solid var(--line); border-radius:var(--radius);
      padding:12px; box-shadow:var(--shadow); margin-bottom:16px;
    }
    @media (max-width: 820px){ .filters{ grid-template-columns:1fr; } }

    .filters input[type="text"],
    .filters select{
      width:100%; border:1px solid var(--line); border-radius:10px; padding:10px 12px; background:#fff;
      font-size:.95rem; outline:none;
    }
    .filters input[type="text"]::placeholder{ color:#9ca3af; }

    .btn{
      display:inline-flex; align-items:center; gap:8px;
      background:var(--primary); color:var(--primary-ink); border:none;
      border-radius:10px; padding:10px 14px; font-weight:600; text-decoration:none; cursor:pointer;
    }
    .btn:hover{ filter:brightness(0.98); }
    .btn-outline{
      display:inline-flex; align-items:center; gap:8px;
      background:#fff; color:var(--primary); border:1px solid var(--primary);
      border-radius:10px; padding:10px 14px; font-weight:600; text-decoration:none; cursor:pointer;
    }

    .grid{
      display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:14px;
    }
    .card{
      background:var(--panel); border:1px solid var(--line); border-radius:var(--radius);
      padding:12px; box-shadow:var(--shadow); display:flex; gap:12px; align-items:flex-start;
      transition:transform .06s ease, box-shadow .1s ease;
    }
    .card:hover{ transform:translateY(-1px); box-shadow:0 2px 3px rgba(0,0,0,.05), 0 10px 22px rgba(0,0,0,.08); }

    .thumb{
      width:80px; height:80px; object-fit:cover; border-radius:10px;
      background:#f3f4f6; border:1px solid var(--line);
      flex:0 0 80px;
    }

    .title-row{ display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .title-row strong{ font-size:1rem; }

    .badge{
      font-size:.75rem; padding:2px 8px; border-radius:999px; border:1px solid var(--line);
      background:#fff; color:#374151;
    }
    .muted{ color:var(--muted); font-size:.9rem; margin-top:2px; }

    .price{ font-weight:700; margin-top:4px; }
    .qty{ font-size:.85rem; color:var(--muted); }

    .actions{ margin-top:8px; }
  </style>
</head>
<body>
  <div class="page">
    <div class="top">
      <h2>Recently Deleted Items</h2>
      <a class="btn-outline" href="dashboard.php">← Dashboard</a>
    </div>

    <form method="GET" class="filters">
      <input type="text" name="q" placeholder="Search name or brand…" value="<?= htmlspecialchars($q) ?>">
      <select name="store_id">
        <option value="">All Stores</option>
        <?php foreach ($stores as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $store_id == $s['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($s['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div style="display:flex; gap:10px;">
        <button class="btn" type="submit">Filter</button>
        <?php if ($q !== '' || $store_id): ?>
          <a class="btn-outline" href="deleted-items.php">Clear</a>
        <?php endif; ?>
      </div>
    </form>

    <?php if (!$rows): ?>
      <div class="card" style="justify-content:center;"> <div class="muted">No deleted items found with these filters.</div> </div>
    <?php else: ?>
      <div class="grid">
        <?php foreach ($rows as $r): ?>
          <div class="card">
            <img class="thumb" src="<?= $r['image_url'] ? htmlspecialchars($r['image_url']) : 'assets/default-store.png' ?>" alt="">
            <div style="flex:1">
              <div class="title-row">
                <strong><?= htmlspecialchars($r['name']) ?></strong>
                <?php if ($r['brand']): ?><span class="badge"><?= htmlspecialchars($r['brand']) ?></span><?php endif; ?>
              </div>

              <div class="muted"><?= htmlspecialchars($r['store_name']) ?></div>

              <div class="price">
                ₦<?= number_format($r['markup_price'] ?? $r['price'], 2) ?>
                <span class="qty"> · Qty: <?= (int)$r['quantity'] ?> · Low≤<?= (int)$r['low_stock_threshold'] ?></span>
              </div>

              <div class="actions">
                <form method="POST" action="restore-item.php" onsubmit="return confirm('Restore this item?');">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn" type="submit">Restore</button>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if ($next_cursor): ?>
        <div style="margin-top:16px; display:flex; justify-content:flex-end;">
          <a class="btn-outline" href="deleted-items.php?<?= http_build_query(['q'=>$q,'store_id'=>$store_id,'limit'=>$limit,'cursor'=>$next_cursor]) ?>">
            Older →
          </a>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</body>
</html>
