<?php
require_once __DIR__ . '/../src/db.php';

// Determine type from store record
$store_id = $_GET['store_id'] ?? null;
if (!$store_id) {
    die("Store ID required.");
}

$st = $pdo->prepare("SELECT type FROM stores WHERE id = ?");
$st->execute([$store_id]);
$store = $st->fetch();
if (!$store) die("Invalid store.");

$type = $store['type'];

// Fetch categories for this type
$categories = $pdo->prepare("SELECT id, name FROM categories WHERE type = ? ORDER BY name");
$categories->execute([$type]);
$categories = $categories->fetchAll();
?>
<h1><?= ucfirst($type) ?> Categories</h1>
<ul>
<?php foreach ($categories as $cat): ?>
    <li><?= htmlspecialchars($cat['name']) ?></li>
<?php endforeach; ?>
</ul>
<a href="add-category.php?store_id=<?= $store_id ?>">Add Category</a>
