<?php
function assertStoreAccess(PDO $pdo, int $storeId): void {
  if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php'); exit;
  }
  $role = $_SESSION['role'] ?? 'staff';
  if ($role === 'admin') return;

  $assigned = (int)($_SESSION['assigned_store_id'] ?? 0);
  if ($assigned !== $storeId) {
    http_response_code(403);
    exit('Forbidden: you are not assigned to this store.');
  }
}
