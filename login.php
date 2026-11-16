<?php
require 'config.php';
if (is_logged_in()) redirect('index.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    $st = db()->prepare('SELECT * FROM users WHERE username = ?');
    $st->execute([$u]);
    $user = $st->fetch();
    if ($user && password_verify($p, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => (int)$user['id'], 'full_name' => $user['full_name'], 'username' => $user['username'], 'role' => $user['role']];
        redirect('index.php');
    }
    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login | <?= e(BUSINESS_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>body{background:linear-gradient(135deg,#1e293b,#2563eb);min-height:100vh}</style>
</head>
<body class="d-flex align-items-center justify-content-center p-3">
<div class="card shadow border-0" style="width:100%;max-width:400px">
  <div class="card-body p-4">
    <div class="text-center mb-4"><i class="bi bi-box-seam-fill text-primary" style="font-size:2.5rem"></i><h1 class="h4 mt-2"><?= e(BUSINESS_NAME) ?></h1><p class="text-muted mb-0">Sign in to continue</p></div>
    <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="needs-validation" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label">Username</label><input name="username" class="form-control" required autofocus></div>
      <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
      <button class="btn btn-primary w-100">Login</button>
    </form>
  </div>
</div>
<script>document.querySelectorAll('.needs-validation').forEach(f=>f.addEventListener('submit',e=>{if(!f.checkValidity()){e.preventDefault();}f.classList.add('was-validated')}));</script>
</body></html>
