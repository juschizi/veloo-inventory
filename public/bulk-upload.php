<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../src/db.php';
require_once '../src/auth.php';

// First, get the store_id from the URL
$store_id = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 0;
if (!$store_id) { header('Location: dashboard.php'); exit; }

// THEN do the access check
assertStoreAccess($pdo, $store_id);


$st = $pdo->prepare("SELECT id, name FROM stores WHERE id=? AND is_active=1");
$st->execute([$store_id]);
$store = $st->fetch();
if (!$store) { header('Location: dashboard.php'); exit; }

$errors = [];
$rows = [];
$update_existing = isset($_POST['update_existing']) ? 1 : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
  if ($_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
    $errors[] = 'CSV upload failed.';
  } else {
    $tmp = $_FILES['csv']['tmp_name'];
    $fh = fopen($tmp, 'r');
    if (!$fh) { $errors[] = 'Unable to open CSV.'; }
    else {
      // read header
      $header = fgetcsv($fh);
      if (!$header) { $errors[] = 'CSV is empty.'; }
      else {
        // normalize header
        $header = array_map(fn($h) => strtolower(trim($h)), $header);
        $required = ['name','category','price'];
        foreach ($required as $req) {
          if (!in_array($req, $header, true)) {
            $errors[] = "Missing required column: $req";
          }
        }
        // optional columns
        // sku, brand, description, markup_price, quantity, low_stock_threshold, image_url, in_stock (ignored; we derive from qty)
        if (!$errors) {
          while (($line = fgetcsv($fh)) !== false) {
            if (count($line) === 1 && trim($line[0]) === '') continue;
            $row = array_combine($header, array_map('trim', $line));

            // minimal validation
            $row['name']   = $row['name']   ?? '';
            $row['price']  = $row['price']  ?? '';
            $row['category'] = $row['category'] ?? '';

            if ($row['name'] === '' || $row['price'] === '' || $row['category'] === '') {
              $row['_error'] = 'Missing required fields (name, category, price).';
            }

            // normalize numerics
            $row['price']              = is_numeric($row['price']) ? (float)$row['price'] : null;
            $row['markup_price']       = isset($row['markup_price']) && $row['markup_price'] !== '' ? (float)$row['markup_price'] : null;
            $row['quantity']           = isset($row['quantity']) && $row['quantity'] !== '' ? max(0, (int)$row['quantity']) : 0;
            $row['low_stock_threshold']= isset($row['low_stock_threshold']) && $row['low_stock_threshold'] !== '' ? max(0, (int)$row['low_stock_threshold']) : 5;

            // derive in_stock
            $row['in_stock']           = $row['quantity'] > 0 ? 1 : 0;

            // stash
            $rows[] = $row;
          }
          fclose($fh);
        }
      }
    }
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Bulk Upload — <?= htmlspecialchars($store['name']) ?></title>
  <link rel="stylesheet" href="assets/styles.css">
  <style>
    table { width:100%; border-collapse: collapse; margin-top:1rem; }
    th, td { border:1px solid #e5e7eb; padding:.5rem; font-size:.95rem; }
    th { background:#f9fafb; text-align:left; }
    .err { color:#b91c1c; font-size:.9rem; }
    .muted { color:#6b7280; }
    .flex { display:flex; gap:.5rem; align-items:center; }
    .pill { border:1px solid #d1d5db; padding:.2rem .5rem; border-radius:999px; font-size:.85rem; }
  </style>
</head>
<body>
  <h2>Bulk Upload — <?= htmlspecialchars($store['name']) ?></h2>

  <?php if (!$_POST): ?>
    <p class="muted">CSV headers required: <code>name, category, price</code>. Optional: <code>sku, brand, description, markup_price, quantity, low_stock_threshold, image_url</code>.</p>
    <form method="POST" enctype="multipart/form-data" class="flex">
        
 <style>
  .upload-btn {
    background: #1e40af;
    color: white;
    padding: 0.6rem 1.2rem;
    border-radius: 6px;
    cursor: pointer;
    display: inline-block;
    margin-bottom: 0.5rem;
  }
  .upload-btn:hover {
    background: #1e3a8a;
  }
  /* Hide default file inputs */
  input[type="file"] {
    display: none;
  }
</style>

<!-- Hidden store ID -->
<input type="hidden" name="store_id" value="<?= $store['id'] ?>">

<!-- CSV Upload -->
<label for="csv" class="upload-btn">📄 Choose CSV File</label>
<input type="file" id="csv" name="csv" accept=".csv" required>

<!-- ZIP Upload -->
<label for="images_zip" class="upload-btn">📁 Choose Image ZIP</label>
<input type="file" id="images_zip" name="images_zip" accept=".zip" aria-label="Choose image file">


      <label class="pill">
        <input type="checkbox" name="update_existing" value="1"> Update existing (by SKU; fallback name+brand)
      </label>
      <button class="btn" type="submit">Preview</button>
      <a class="btn-outline" href="template.csv">Download template</a>
    </form>
  <?php else: ?>

    <?php if ($errors): ?>
      <div class="err"><?= htmlspecialchars(implode(' ', $errors)) ?></div>
      <p><a href="bulk-upload.php?store_id=<?= $store['id'] ?>">Try again</a></p>
    <?php else: ?>
      <form method="POST" action="bulk-commit.php">
        <input type="hidden" name="store_id" value="<?= $store['id'] ?>">
        <input type="hidden" name="update_existing" value="<?= $update_existing ?>">
        <textarea name="payload" hidden><?= htmlspecialchars(json_encode($rows, JSON_UNESCAPED_UNICODE)) ?></textarea>

        <table>
          <thead>
            <tr>
              <th>#</th><th>SKU</th><th>Name</th><th>Brand</th><th>Category</th><th>Price</th><th>Markup</th><th>Qty</th><th>Low Thr.</th><th>Image</th><th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $i => $r): 
              $status = $r['_error'] ?? 'OK';
            ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td><?= htmlspecialchars($r['sku'] ?? '') ?></td>
              <td><?= htmlspecialchars($r['name'] ?? '') ?></td>
              <td><?= htmlspecialchars($r['brand'] ?? '') ?></td>
              <td><?= htmlspecialchars($r['category'] ?? '') ?></td>
              <td><?= htmlspecialchars((string)($r['price'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string)($r['markup_price'] ?? '')) ?></td>
              <td><?= (int)($r['quantity'] ?? 0) ?></td>
              <td><?= (int)($r['low_stock_threshold'] ?? 5) ?></td>
              <td><?= htmlspecialchars($r['image_url'] ?? '') ?></td>
              <td class="<?= $status==='OK' ? 'muted' : 'err' ?>"><?= htmlspecialchars($status) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <div style="margin-top:1rem;">
          <button class="btn" type="submit">Commit Import</button>
          <a class="btn-outline" href="bulk-upload.php?store_id=<?= $store['id'] ?>">Cancel</a>
        </div>
      </form>
    <?php endif; ?>

  <?php endif; ?>
</body>
</html>
