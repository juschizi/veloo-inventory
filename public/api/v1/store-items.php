<?php
require_once '../../../src/db.php';
require_once '../../../src/api.php';

$key = require_api_key($pdo);
$store_id = (int)($_GET['store_id'] ?? 0);
if (!$store_id) json_out(['error'=>'bad_request','detail'=>'store_id required'], 400);

$q = trim($_GET['q'] ?? '');
$category_id = (int)($_GET['category_id'] ?? 0);
$stock = $_GET['stock'] ?? ''; // in|low|out
$limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
$cursor = (int)($_GET['cursor'] ?? 0); // last_id cursor

$params = [$store_id];
$sql = "SELECT id, category_id, sku, name, brand, description,
               price, markup_price, image_url, in_stock, quantity, low_stock_threshold, last_updated
        FROM items WHERE store_id=? AND is_deleted=0";

if ($cursor > 0) { $sql .= " AND id > ?"; $params[] = $cursor; }

if ($q !== '') {
  $sql .= " AND (name LIKE ? OR brand LIKE ? OR description LIKE ?)";
  $like = "%$q%"; $params[] = $like; $params[] = $like; $params[] = $like;
}

if ($category_id) { $sql .= " AND category_id=?"; $params[] = $category_id; }

if ($stock === 'low') {
  $sql .= " AND quantity > 0 AND quantity <= low_stock_threshold";
} elseif ($stock === 'in') {
  $sql .= " AND in_stock = 1";
} elseif ($stock === 'out') {
  $sql .= " AND in_stock = 0";
}

$sql .= " ORDER BY id ASC LIMIT $limit";
$st = $pdo->prepare($sql);
$st->execute($params);

$out = []; $next = null;
foreach ($st as $row) {
  $next = (int)$row['id'];
  $row['image_url'] = $row['image_url'] ? public_image_url($row['image_url']) : null;
  $row['final_price'] = $row['markup_price'] ?? $row['price'];
  $out[] = $row;
}

json_out(['data' => $out, 'next_cursor' => $next]);
