<?php
require_login();
$lowCount = (int)db()->query('SELECT COUNT(*) FROM products WHERE quantity <= min_stock')->fetchColumn();
$nav = [
    ['index.php', 'Dashboard', 'speedometer2', 'dashboard'],
    ['products.php', 'Products', 'box-seam', 'products'],
    ['categories.php', 'Categories', 'tags', 'categories'],
    ['suppliers.php', 'Suppliers', 'truck', 'suppliers'],
    ['stock.php', 'Stock Management', 'arrow-left-right', 'stock'],
    ['reports.php', 'Reports', 'file-earmark-bar-graph', 'reports'],
];
if (is_admin()) $nav[] = ['users.php', 'Users', 'people', 'users'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Inventory') ?> | <?= e(BUSINESS_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="assets/style.css" rel="stylesheet">
</head>
<body>
<div class="d-flex">
  <aside class="sidebar offcanvas-lg offcanvas-start flex-shrink-0" tabindex="-1" id="sidebar">
    <div class="d-flex justify-content-between align-items-center brand">
      <span><i class="bi bi-box-seam-fill me-2"></i>Inventory</span>
      <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebar"></button>
    </div>
    <nav class="nav flex-column mt-2">
      <?php foreach ($nav as [$href, $label, $icon, $key]): ?>
        <a class="nav-link <?= ($active ?? '') === $key ? 'active' : '' ?>" href="<?= $href ?>"><i class="bi bi-<?= $icon ?>"></i><?= e($label) ?></a>
      <?php endforeach; ?>
      <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i>Logout</a>
    </nav>
  </aside>

  <div class="flex-grow-1 min-w-0">
    <header class="topbar d-flex align-items-center justify-content-between px-3 py-2">
      <div class="d-flex align-items-center gap-2">
        <button class="btn btn-outline-secondary d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebar"><i class="bi bi-list"></i></button>
        <h1 class="h5 mb-0"><?= e($pageTitle ?? '') ?></h1>
      </div>
      <div class="d-flex align-items-center gap-3">
        <a href="reports.php?type=low" class="position-relative text-secondary fs-5" title="Low stock alerts">
          <i class="bi bi-bell"></i>
          <?php if ($lowCount > 0): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $lowCount ?></span><?php endif; ?>
        </a>
        <div class="dropdown">
          <a class="text-decoration-none text-dark dropdown-toggle" href="#" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle me-1"></i><?= e($_SESSION['user']['full_name']) ?>
            <span class="badge text-bg-secondary"><?= e(ucfirst($_SESSION['user']['role'])) ?></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li></ul>
        </div>
      </div>
    </header>
    <main class="p-3 p-md-4">
      <?php foreach ($_SESSION['flash'] ?? [] as [$t, $m]): ?>
        <div class="alert alert-<?= e($t) ?> alert-dismissible fade show no-print" role="alert"><?= e($m) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
      <?php endforeach; unset($_SESSION['flash']); ?>
