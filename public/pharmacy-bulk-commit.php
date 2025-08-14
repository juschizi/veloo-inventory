<?php
// public/pharmacy-bulk-commit.php
session_start();
if (!isset($_SESSION['admin_id']) && isset($_SESSION['user_id'])) $_SESSION['admin_id'] = (int)$_SESSION['user_id'];
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

require_once '../src/db.php';
require_once '../src/auth.php';

$store_id = (int)($_POST['store_id'] ?? 0);
$update_existing = (int)($_POST['update_existing'] ?? 0);
if ($store_id <= 0) { header('Location: pharmacy-dashboard.php'); exit; }

assertStoreAccess($pdo, $store_id);

// Ensure store is pharmacy
$st = $pdo->prepare("SELECT type, name FROM stores WHERE id=? AND is_active=1 AND is_deleted=0");
$st->execute([$store_id]);
$store = $st->fetch(PDO::FETCH_ASSOC);
if (!$store || $store['type'] !== 'pharmacy') {
  header("Location: pharmacy-dashboard.php?msg=Not%20a%20pharmacy"); exit;
}

// Validate CSV upload
if (empty($_FILES['csv']['name']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
  header("Location: inventory.php?store_id={$store_id}&msg=CSV%20missing"); exit;
}

// Optional ZIP handling
$zipMap = [];       // 'filename.ext' => absoluteExtractedPath
$extractedDir = null;
if (!empty($_FILES['images_zip']['name']) && $_FILES['images_zip']['error'] === UPLOAD_ERR_OK) {
  if (class_exists('ZipArchive')) {
    $tmpZip = $_FILES['images_zip']['tmp_name'];
    $extractedDir = sys_get_temp_dir() . '/pharm_bulk_' . uniqid();
    @mkdir($extractedDir, 0755, true);
    $zip = new ZipArchive();
    if ($zip->open($tmpZip) === TRUE) {
      $zip->extractTo($extractedDir);
      $zip->close();
      // index files
      $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractedDir, FilesystemIterator::SKIP_DOTS));
      foreach ($rii as $file) {
        if (!$file->isFile()) continue;
        $base = strtolower($file->getBasename());
        $zipMap[$base] = $file->getPathname();
      }
    }
  } // else: silently ignore if ZipArchive not available
}

// CSV parse
$csvPath = $_FILES['csv']['tmp_name'];
$fh = fopen($csvPath, 'r');
if (!$fh) { header("Location: inventory.php?store_id={$store_id}&msg=Unable%20to%20read%20CSV"); exit; }

$headers = fgetcsv($fh);
if (!$headers) { header("Location: inventory.php?store_id={$store_id}&msg=Empty%20CSV"); exit; }
$norm = [];
foreach ($headers as $i => $h) { $norm[$i] = strtolower(trim($h)); }

$requiredAnyName = ['name','brand','generic_name'];
if (!in_array('category', $norm) || !in_array('price', $norm)) {
  header("Location: inventory.php?store_id={$store_id}&msg=Missing%20category/price%20headers"); exit;
}
if (count(array_intersect($requiredAnyName, $norm)) === 0) {
  header("Location: inventory.php?store_id={$store_id}&msg=Need%20name/brand/generic_name"); exit;
}

// helpers
$getCatId = $pdo->prepare("SELECT id FROM categories WHERE name=? LIMIT 1");
$insCat   = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");

$getBySku = $pdo->prepare("SELECT id FROM items WHERE store_id=? AND sku=? LIMIT 1");
$getByNB  = $pdo->prepare("SELECT id FROM items WHERE store_id=? AND name=? AND IFNULL(brand,'')=IFNULL(?, '') LIMIT 1");

$insItem = $pdo->prepare("INSERT INTO items
 (store_id, category_id, sku, name, brand, generic_name, dosage_form, strength, requires_prescription,
  expiry_date, batch_number, price, markup_price, in_stock, quantity, low_stock_threshold, description, image_url)
 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

$updItem = $pdo->prepare("UPDATE items SET
  category_id=?, name=?, brand=?, generic_name=?, dosage_form=?, strength=?, requires_prescription=?,
  expiry_date=?, batch_number=?, price=?, markup_price=?, in_stock=?, quantity=?, low_stock_threshold=?,
  description=?, image_url=?, last_updated=NOW()
 WHERE id=?");

// destination for images
$itemsDir = realpath(__DIR__ . '/..') . '/uploads/items';
@mkdir($itemsDir, 0755, true);

$inserted = 0; $updated = 0; $failed = 0;
$pdo->beginTransaction();

try {
  while (($row = fgetcsv($fh)) !== false) {
    if (count($row) === 1 && trim($row[0]) === '') continue; // skip blanks
    $R = [];
    foreach ($norm as $i => $key) { $R[$key] = $row[$i] ?? null; }

    // minimal validation
    $catName = trim((string)($R['category'] ?? ''));
    $price   = $R['price'] ?? null;
    $nameIn  = trim((string)($R['name'] ?? ''));
    $brand   = trim((string)($R['brand'] ?? ''));
    $generic = trim((string)($R['generic_name'] ?? ''));
    if ($catName === '' || $price === null || $price === '' || !is_numeric($price)) { $failed++; continue; }
    $price = (float)$price;

    // preferred display name: provided name, else brand, else generic
    $name = $nameIn !== '' ? $nameIn : ($brand !== '' ? $brand : ($generic !== '' ? $generic : null));
    if ($name === null) { $failed++; continue; }

    // category id (create if missing)
    $getCatId->execute([$catName]);
    $catRow = $getCatId->fetch(PDO::FETCH_ASSOC);
    if (!$catRow) { $insCat->execute([$catName]); $category_id = (int)$pdo->lastInsertId(); }
    else { $category_id = (int)$catRow['id']; }

    // pharmacy fields
    $dosage  = trim((string)($R['dosage_form'] ?? '')) ?: null;
    $strength= trim((string)($R['strength'] ?? '')) ?: null;
    $rx      = isset($R['requires_prescription']) ? (int)$R['requires_prescription'] : 0;
    $batch   = trim((string)($R['batch_number'] ?? '')) ?: null;

    // expiry (YYYY-MM-DD expected)
    $expiry = null;
    if (!empty($R['expiry_date'])) {
      $ts = strtotime($R['expiry_date']);
      if ($ts !== false) { $expiry = date('Y-m-d', $ts); }
    }

    // other fields
    $sku    = isset($R['sku']) ? trim((string)$R['sku']) : null;
    $markup = isset($R['markup_price']) && $R['markup_price'] !== '' ? (float)$R['markup_price'] : null;
    $qty    = isset($R['quantity']) ? max(0, (int)$R['quantity']) : 0;
    $lowthr = isset($R['low_stock_threshold']) ? max(0, (int)$R['low_stock_threshold']) : 5;
    $desc   = isset($R['description']) ? trim((string)$R['description']) : null;

    // in_stock rule: if expired, force out-of-stock; else qty>0
    $expired = ($expiry && strtotime($expiry) < strtotime('today'));
    $in_stock = $expired ? 0 : ($qty > 0 ? 1 : 0);

    // image resolution: image_filename from ZIP (priority), else image_url
    $image_url = null;
    if (!empty($R['image_filename']) && $extractedDir && !empty($zipMap)) {
      $base = strtolower(trim((string)$R['image_filename']));
      if (isset($zipMap[$base])) {
        $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp'])) {
          $newRel = 'uploads/items/' . uniqid('drug_', true) . '.' . $ext;
          $dest = realpath(__DIR__ . '/..') . '/' . $newRel;
          if (@copy($zipMap[$base], $dest)) { $image_url = $newRel; }
        }
      }
    }
    if (!$image_url && !empty($R['image_url'])) {
      // trust-as-is (later swap to CDN)
      $image_url = trim((string)$R['image_url']);
    }

    // find existing
    $existingId = null;
    if ($sku) {
      $getBySku->execute([$store_id, $sku]);
      $exist = $getBySku->fetch(PDO::FETCH_ASSOC);
      if ($exist) $existingId = (int)$exist['id'];
    }
    if (!$existingId && $update_existing) {
      $getByNB->execute([$store_id, $name, $brand ?: null]);
      $exist = $getByNB->fetch(PDO::FETCH_ASSOC);
      if ($exist) $existingId = (int)$exist['id'];
    }

    if ($existingId) {
      $updItem->execute([
        $category_id, $name, ($brand ?: null), ($generic ?: null), $dosage, $strength, $rx,
        $expiry, $batch, $price, $markup, $in_stock, $qty, $lowthr, $desc, $image_url, $existingId
      ]);
      $updated++;
    } else {
      $insItem->execute([
        $store_id, $category_id, ($sku ?: null), $name, ($brand ?: null), ($generic ?: null), $dosage, $strength, $rx,
        $expiry, $batch, $price, $markup, $in_stock, $qty, $lowthr, $desc, $image_url
      ]);
      $inserted++;
    }
  }

  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  fclose($fh);
  if ($extractedDir) { exec('rm -rf ' . escapeshellarg($extractedDir)); }
  header("Location: inventory.php?store_id={$store_id}&msg=Import%20failed"); exit;
}

fclose($fh);
if ($extractedDir) { exec('rm -rf ' . escapeshellarg($extractedDir)); }

header("Location: inventory.php?store_id={$store_id}&msg=Imported:%20{$inserted}%20added,%20{$updated}%20updated,%20{$failed}%20skipped");
exit;
