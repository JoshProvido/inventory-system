<?php
require 'config.php';
require_login();
$pdo = db();

$stats = $pdo->query("SELECT
  (SELECT COUNT(*) FROM products) AS products,
  (SELECT COUNT(*) FROM categories) AS categories,
  (SELECT COUNT(*) FROM suppliers) AS suppliers,
  (SELECT COUNT(*) FROM products WHERE quantity > 0 AND quantity <= min_stock) AS low,
  (SELECT COUNT(*) FROM products WHERE quantity = 0) AS out_stock,
  (SELECT COALESCE(SUM(quantity * purchase_price), 0) FROM products) AS value")->fetch();

$lowItems = $pdo->query('SELECT name, sku, quantity, min_stock, unit FROM products WHERE quantity <= min_stock ORDER BY quantity ASC LIMIT 8')->fetchAll();
$recentProducts = $pdo->query('SELECT p.name, p.sku, p.created_at, c.name AS category FROM products p JOIN categories c ON c.id = p.category_id ORDER BY p.created_at DESC, p.id DESC LIMIT 5')->fetchAll();
$recentMoves = $pdo->query('SELECT m.*, p.name AS product_name FROM stock_movements m JOIN products p ON p.id = m.product_id ORDER BY m.created_at DESC, m.id DESC LIMIT 6')->fetchAll();

$catChart = $pdo->query('SELECT c.name, COALESCE(SUM(p.quantity),0) AS qty FROM categories c LEFT JOIN products p ON p.category_id = c.id GROUP BY c.id ORDER BY c.name')->fetchAll();

// Last 7 days stock in vs out
$days = []; for ($i = 6; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-$i day"))] = ['in' => 0, 'out' => 0];
$mv = $pdo->query("SELECT DATE(created_at) d, type, SUM(ABS(quantity)) q FROM stock_movements WHERE created_at >= CURDATE() - INTERVAL 6 DAY AND type IN ('in','out') GROUP BY d, type")->fetchAll();
foreach ($mv as $r) if (isset($days[$r['d']])) $days[$r['d']][$r['type']] = (int)$r['q'];

$pageTitle = 'Dashboard'; $active = 'dashboard';
include 'includes/header.php';

$cards = [
  ['Total Products', $stats['products'], 'box-seam', 'primary'],
  ['Categories', $stats['categories'], 'tags', 'info'],
  ['Suppliers', $stats['suppliers'], 'truck', 'secondary'],
  ['Low Stock', $stats['low'], 'exclamation-triangle', 'warning'],
  ['Out of Stock', $stats['out_stock'], 'x-octagon', 'danger'],
  ['Inventory Value', money($stats['value']), 'cash-stack', 'success'],
];
?>
<?php if ($stats['out_stock'] > 0): ?>
  <div class="alert alert-danger"><i class="bi bi-x-octagon me-2"></i><strong><?= (int)$stats['out_stock'] ?></strong> product(s) are out of stock. <a href="reports.php?type=out" class="alert-link">View report</a></div>
<?php endif; ?>
<?php if ($stats['low'] > 0): ?>
  <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i><strong><?= (int)$stats['low'] ?></strong> product(s) are at or below their minimum stock level. <a href="reports.php?type=low" class="alert-link">View report</a></div>
<?php endif; ?>

<div class="row g-3 mb-4">
<?php foreach ($cards as [$label, $val, $icon, $color]): ?>
  <div class="col-6 col-md-4 col-xl-2"><div class="card stat-card h-100"><div class="card-body d-flex align-items-center gap-3">
    <div class="icon bg-<?= $color ?>-subtle text-<?= $color ?>"><i class="bi bi-<?= $icon ?>"></i></div>
    <div class="min-w-0"><div class="text-muted small"><?= e($label) ?></div><div class="fw-bold fs-5 text-truncate"><?= is_string($val) ? e($val) : (int)$val ?></div></div>
  </div></div></div>
<?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-6"><div class="card h-100"><div class="card-header bg-white fw-semibold">Units in stock by category</div><div class="card-body"><canvas id="catChart" height="200"></canvas></div></div></div>
  <div class="col-lg-6"><div class="card h-100"><div class="card-header bg-white fw-semibold">Stock in vs. out (last 7 days)</div><div class="card-body"><canvas id="moveChart" height="200"></canvas></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-4"><div class="card h-100"><div class="card-header bg-white fw-semibold">Low-stock alerts</div>
    <ul class="list-group list-group-flush">
    <?php foreach ($lowItems as $l): ?>
      <li class="list-group-item d-flex justify-content-between align-items-center">
        <div><div class="fw-semibold"><?= e($l['name']) ?></div><small class="text-muted">Min <?= (int)$l['min_stock'] ?> <?= e($l['unit']) ?></small></div>
        <span class="badge text-bg-<?= $l['quantity'] <= 0 ? 'danger' : 'warning' ?>"><?= (int)$l['quantity'] ?> left</span>
      </li>
    <?php endforeach; if (!$lowItems): ?><li class="list-group-item text-muted">All products are sufficiently stocked.</li><?php endif; ?>
    </ul></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-header bg-white fw-semibold">Recently added products</div>
    <ul class="list-group list-group-flush">
    <?php foreach ($recentProducts as $r): ?>
      <li class="list-group-item"><div class="fw-semibold"><?= e($r['name']) ?></div><small class="text-muted"><?= e($r['category']) ?> · <?= e($r['sku']) ?> · <?= e(date('M d, Y', strtotime($r['created_at']))) ?></small></li>
    <?php endforeach; if (!$recentProducts): ?><li class="list-group-item text-muted">No products yet.</li><?php endif; ?>
    </ul></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-header bg-white fw-semibold">Recent inventory activity</div>
    <ul class="list-group list-group-flush">
    <?php foreach ($recentMoves as $m): $c = ['in' => 'success', 'out' => 'danger', 'adjustment' => 'secondary'][$m['type']]; ?>
      <li class="list-group-item d-flex justify-content-between align-items-start">
        <div><div class="fw-semibold"><?= e($m['product_name']) ?></div><small class="text-muted"><?= e(date('M d, H:i', strtotime($m['created_at']))) ?> · <?= e($m['remarks']) ?></small></div>
        <span class="badge text-bg-<?= $c ?>"><?= e(ucfirst($m['type'])) ?> <?= (int)$m['quantity'] ?></span>
      </li>
    <?php endforeach; if (!$recentMoves): ?><li class="list-group-item text-muted">No activity yet.</li><?php endif; ?>
    </ul></div></div>
</div>
<?php
$cl = json_encode(array_column($catChart, 'name')); $cd = json_encode(array_map('intval', array_column($catChart, 'qty')));
$dl = json_encode(array_map(fn($d) => date('M d', strtotime($d)), array_keys($days)));
$di = json_encode(array_column($days, 'in')); $do = json_encode(array_column($days, 'out'));
$extraScripts = <<<JS
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('catChart'), {type: 'doughnut', data: {labels: $cl, datasets: [{data: $cd, backgroundColor: ['#2563eb','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#84cc16','#f97316']}]}, options: {plugins: {legend: {position: 'bottom'}}}});
new Chart(document.getElementById('moveChart'), {type: 'bar', data: {labels: $dl, datasets: [{label: 'Stock in', data: $di, backgroundColor: '#10b981'}, {label: 'Stock out', data: $do, backgroundColor: '#ef4444'}]}, options: {scales: {y: {beginAtZero: true, ticks: {precision: 0}}}}});
</script>
JS;
include 'includes/footer.php';
