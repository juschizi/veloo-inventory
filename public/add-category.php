<?php
require_once __DIR__ . '/../src/db.php';

$store_id = $_GET['store_id'] ?? null;
if (!$store_id) die("Store ID required.");

// Get store type
$st = $pdo->prepare("SELECT type FROM stores WHERE id = ?");
$st->execute([$store_id]);
$store = $st->fetch();
if (!$store) die("Invalid store.");

$type = $store['type'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    if ($name) {
        $st = $pdo->prepare("INSERT INTO categories (name, type) VALUES (?, ?)");
        $st->execute([$name, $type]);
        header("Location: categories.php?store_id=$store_id");
        exit;
    }
}
?>
<h1>Add <?= ucfirst($type) ?> Category</h1>
<form method="POST">
    <label>Name</label>
    <input type="text" name="name" required>
    <button type="submit">Save</button>
</form>
