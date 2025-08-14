<?php
require_once '../../../src/db.php';
require_once '../../../src/api.php';

$key = require_api_key($pdo);

$q = trim($_GET['q'] ?? '');
$limit = max(1, min(100, (int)($_GET['limit'] ?? 25)));
$cursor = (int)($_GET['cursor'] ?? 0); // simple numeric cursor=last_id

$params = [];
$sql = "SELECT id, name, address, contact_phone, contact_email, lat, lng, logo_url
        FROM stores WHERE is_active=1 AND is_deleted=0";

if ($q !== '') {
  $sql .= " AND (name LIKE ? OR address LIKE ?)";
  $like = "%$q%";
  $params = [$like, $like];
}

if ($cursor > 0) {
  $sql .= " AND id > ?";
  $params[] = $cursor;
}

$sql .= " ORDER BY id ASC LIMIT $limit";
$st = $pdo->prepare($sql);
$st->execute($params);

$out = [];
$next = null;
foreach ($st as $row) {
  $next = (int)$row['id'];
  $row['logo_url'] = $row['logo_url'] ? public_image_url($row['logo_url']) : null;
  $out[] = $row;
}

json_out(['data' => $out, 'next_cursor' => $next]);
