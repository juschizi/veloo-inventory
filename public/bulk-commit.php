<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

require_once '../src/db.php';
require_once '../src/auth.php'; // <-- role-based scoping helper

$store_id = (int)($_POST['store_id'] ?? 0);
$update_existing = (int)($_POST['update_existing'] ?? 0);
$payload = $_POST['payload'] ?? '';

if (!$store_id || !$payload) { header('Location: dashboard.php'); exit; }

// Enforce: staff can only touch their assigned store
assertStoreAccess($pdo, $store_id);

$rows = json_decode($payload, true);
if (!is_array($rows)) {
  header("Location: inventory.php?store_id=$store_id&msg=Invalid%20payload");
  exit;
}

/**
 * Download remote image and save locally under public/uploads/items/
 * Returns relative path (e.g., 'uploads/items/img_xxx.jpg') or null on failure.
 */
function saveImageFromUrl(string $url): ?string {
  if (!preg_match('~^https?://~i', $url)) return null;

  // Fetch
  $context = stream_context_create([
    'http' => ['timeout' => 10],
    'https' => ['timeout' => 10]
  ]);
  $data = @file_get_contents($url, false, $context);
  if ($data === false) return null;

  // Ensure dir
  $dir = 'uploads/items';
  if (!is_dir($dir)) { @mkdir($dir, 0755, true); }

  // Determine extension (fallback jpg)
  $pathPart = parse_url($url, PHP_URL_PATH) ?? '';
  $ext = pathinfo($pathPart, PATHINFO_EXTENSION) ?: 'jpg';
  $ext = preg_replace('/[^a-zA-Z0-9]/','', $ext);
  if ($ext === '') $ext = 'jpg';

  // Write file
  $name = uniqid('img_', true) . '.' . $ext;
  $relPath = $dir . '/' . $name;
  $ok = @file_put_contents($relPath, $data);
  return $ok ? $relPath : null;
}

// helpers
$getCatId = $pdo->prepare("SELECT id FROM categories WHERE name = ? LIMIT 1");
$insCat   = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");

$getBySku = $pdo->prepare("SELECT id FROM items WHERE store_id=? AND sku=? LIMIT 1");
$getByNB  = $pdo->prepare("SELECT id FROM items WHERE store_id=? AND name=? AND IFNULL(brand,'')=IFNULL(?, '') LIMIT 1");

$insItem = $pdo->prepare("INSERT INTO items
  (store_id, category_id, sku, name, brand, description, price, markup_price, image_url, in_stock, quantity, low_stock_threshold)
  VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");

$updItem = $pdo->prepare("UPDATE items SET
  category_id=?, name=?, brand=?, description=?, price=?, markup_price=?, image_url=?, in_stock=?, quantity=?, low_stock_threshold=?, last_updated=NOW()
  WHERE id=?");

$inserted = 0; $updated = 0; $failed = 0;

$pdo->beginTransaction();
try {
  foreach ($rows as $r) {
    if (!empty($r['_error'])) { $failed++; continue; }

    $name  = trim($r['name'] ?? '');
    $brand = trim($r['brand'] ?? '');
    $cat   = trim($r['category'] ?? '');
    $price = $r['price'] ?? null;

    if ($name === '' || $cat === '' || $price === null || !is_numeric($price)) {
      $failed++; continue;
    }

    // category id (create if missing)
    $getCatId->execute([$cat]);
    $catRow = $getCatId->fetch();
    if (!$catRow) {
      $insCat->execute([$cat]);
      $category_id = (int)$pdo->lastInsertId();
    } else {
      $category_id = (int)$catRow['id'];
    }

    $sku    = isset($r['sku']) ? trim($r['sku']) : null;
    $desc   = $r['description'] ?? null;
    $markup = isset($r['markup_price']) && $r['markup_price'] !== '' ? (float)$r['markup_price'] : null;
    $qty    = isset($r['quantity']) ? max(0, (int)$r['quantity']) : 0;
    $lowthr = isset($r['low_stock_threshold']) ? max(0, (int)$r['low_stock_threshold']) : 5;

    // Download external image URL to local file (if provided)
    $img = isset($r['image_url']) && $r['image_url'] !== '' ? $r['image_url'] : null;
    if ($img) {
      $local = saveImageFromUrl($img);
      $img = $local ?: null;
    }

    // derive stock
    $in_stock = $qty > 0 ? 1 : 0;

    // find existing
    $existingId = null;
    if ($sku) {
      $getBySku->execute([$store_id, $sku]);
      $exist = $getBySku->fetch();
      if ($exist) $existingId = (int)$exist['id'];
    }
    if (!$existingId && $update_existing) {
      $getByNB->execute([$store_id, $name, $brand]);
      $exist = $getByNB->fetch();
      if ($exist) $existingId = (int)$exist['id'];
    }

    if ($existingId) {
      $updItem->execute([
        $category_id,
        $name,
        $brand !== '' ? $brand : null,
        $desc,
        (float)$price,
        $markup,
        $img,
        $in_stock,
        $qty,
        $lowthr,
        $existingId
      ]);
      $updated++;
    } else {
      try {
        $insItem->execute([
          $store_id,
          $category_id,
          $sku ?: null,
          $name,
          $brand !== '' ? $brand : null,
          $desc,
          (float)$price,
          $markup,
          $img,
          $in_stock,
          $qty,
          $lowthr
        ]);
        $inserted++;
      } catch (PDOException $e) {
        // Handle unique SKU clash gracefully by updating instead
        if ((int)$e->getCode() === 23000) { // integrity constraint violation
          if ($sku) {
            $getBySku->execute([$store_id, $sku]);
            $exist = $getBySku->fetch();
            if ($exist) {
              $updItem->execute([
                $category_id, $name, $brand !== '' ? $brand : null, $desc,
                (float)$price, $markup, $img, $in_stock, $qty, $lowthr,
                (int)$exist['id']
              ]);
              $updated++;
              continue;
            }
          }
        }
        $failed++;
      }
    }
  }

  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  header("Location: inventory.php?store_id=$store_id&msg=Import%20failed");
  exit;
}
function extractZipTo(string $zipTmp, string $dest = 'uploads/items/bulk'): ?string {
  if (!is_dir($dest)) mkdir($dest, 0755, true);
  $zip = new ZipArchive();
  if ($zip->open($zipTmp) !== true) return null;
  $folder = $dest . '/' . uniqid('batch_', true);
  mkdir($folder, 0755, true);
  $zip->extractTo($folder);
  $zip->close();
  return $folder;
}

// After decoding CSV rows:
$zipFolder = null;
if (!empty($_FILES['images_zip']) && $_FILES['images_zip']['error'] === UPLOAD_ERR_OK) {
  $zipFolder = extractZipTo($_FILES['images_zip']['tmp_name']);
}

// When processing each row:
if ($zipFolder && !empty($r['sku'])) {
  // try to find an image named <sku>.(jpg|jpeg|png|webp)
  foreach (['jpg','jpeg','png','webp'] as $ext) {
    $candidate = $zipFolder . '/' . $r['sku'] . '.' . $ext;
    if (file_exists($candidate)) {
      // compress & normalize to uploads/items
      $fakeFile = ['tmp_name' => $candidate, 'error' => UPLOAD_ERR_OK];
      $imgLocal = saveCompressedImage($fakeFile); // reuse helper above
      if ($imgLocal) $img = $imgLocal;
      break;
    }
  }
}


header("Location: inventory.php?store_id=$store_id&msg=Imported:%20$inserted%20added,%20$updated%20updated,%20$failed%20skipped");
exit;
