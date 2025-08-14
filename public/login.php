<?php
session_start();
require_once '../src/db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = strtolower(trim($_POST['email'] ?? ''));
  $password = $_POST['password'] ?? '';

  $stmt = $pdo->prepare("SELECT id, name, email, role, assigned_store_id, is_active, password_hash
                         FROM admins WHERE email = ? LIMIT 1");
  $stmt->execute([$email]);
  $admin = $stmt->fetch();

  // TEMP DEBUG — comment these out after testing
  // error_log('DB: ' . $pdo->query("SELECT DATABASE()")->fetchColumn());
  // error_log('Login email: ' . $email);
  // error_log('Row found: ' . json_encode(['exists'=>!!$admin, 'is_active'=>$admin['is_active'] ?? null]));
  // error_log('Verify: ' . (($admin && password_verify($password, $admin['password_hash'])) ? 'true' : 'false'));

  if ($admin && (int)$admin['is_active'] === 1 && password_verify($password, $admin['password_hash'])) {
    $_SESSION['admin_id'] = (int)$admin['id'];
    $_SESSION['role'] = $admin['role'];
    $_SESSION['name'] = $admin['name'];
    $_SESSION['assigned_store_id'] = (int)$admin['assigned_store_id'];
    header('Location: dashboard.php'); exit;
  } else {
    $error = 'Invalid login credentials.';
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login - Veloo Inventory</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
<script src="https://unpkg.com/feather-icons"></script>
<style>
    body {
        background: #f4f6f8;
        font-family: 'Inter', sans-serif;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        margin: 0;
    }
    .login-card {
        background: white;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.05);
        width: 100%;
        max-width: 350px;
        text-align: center;
    }
    h2 {
        margin-bottom: 20px;
        font-weight: 600;
    }
    input {
        width: 100%;
        padding: 10px;
        margin: 8px 0;
        border-radius: 6px;
        border: 1px solid #ddd;
        font-size: 0.9rem;
    }
    button {
        width: 100%;
        padding: 10px;
        background: #1976d2;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.9rem;
        margin-top: 10px;
    }
    button:hover { background: #125a9c; }
    .error {
        background: #ffebee;
        color: #c62828;
        padding: 8px;
        border-radius: 6px;
        margin-bottom: 10px;
        font-size: 0.85rem;
    }
</style>
</head>
<body>
<div class="login-card">
    <i data-feather="lock" style="width:40px;height:40px;color:#1976d2;"></i>
    <h2>Admin Login</h2>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form  method="post">
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Sign In</button>
    </form>
</div>
<script>feather.replace()</script>
</body>
</html>
