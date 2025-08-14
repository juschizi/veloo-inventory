<?php
function json_out($data, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

function get_api_key_row(PDO $pdo): ?array {
  $header = $_SERVER['HTTP_X_API_KEY'] ?? '';
  if ($header === '') return null;
  $stmt = $pdo->query("SELECT id, name, token_hash, status, qpm_limit FROM api_keys WHERE status='active'");
  foreach ($stmt as $row) {
    if (password_verify($header, $row['token_hash'])) return $row;
  }
  return null;
}

function rate_limit(PDO $pdo, array $keyRow): void {
  $minute = gmdate('Y-m-d H:i:00'); // UTC minute bucket
  $sel = $pdo->prepare("SELECT id, count FROM api_usage WHERE api_key_id=? AND minute_ts=?");
  $sel->execute([$keyRow['id'], $minute]);
  $row = $sel->fetch();

  if (!$row) {
    $pdo->prepare("INSERT INTO api_usage (api_key_id, minute_ts, count) VALUES (?,?,1)")
        ->execute([$keyRow['id'], $minute]);
    return;
  }

  if ((int)$row['count'] >= (int)$keyRow['qpm_limit']) {
    json_out(['error' => 'rate_limited', 'detail' => 'Too many requests per minute'], 429);
  }
  $pdo->prepare("UPDATE api_usage SET count = count + 1 WHERE id=?")->execute([$row['id']]);
}

function require_api_key(PDO $pdo): array {
  $keyRow = get_api_key_row($pdo);
  if (!$keyRow) json_out(['error'=>'unauthorized','detail'=>'Provide X-API-Key header'], 401);
  if ($keyRow['status'] !== 'active') json_out(['error'=>'forbidden','detail'=>'Key disabled'], 403);
  rate_limit($pdo, $keyRow);
  $pdo->prepare("UPDATE api_keys SET last_used_at = NOW() WHERE id=?")->execute([$keyRow['id']]);
  return $keyRow;
}

/** Build absolute URL for images later swappable to CDN */
function public_image_url(string $relPath): string {
  // Swap base easily later, e.g., https://cdn.veloo.com/
  $base = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/';
  return $base . ltrim($relPath, '/');
}
