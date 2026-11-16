<?php
require 'config.php';
require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $type = $_POST['type'] ?? '';
    if (!in_array($type, ['in', 'out', 'adjustment'], true)) {
        flash('danger', 'Invalid transaction type.');
    } else {
        [$ok, $msg] = apply_stock((int)($_POST['product_id'] ?? 0), $type, (int)($_POST['quantity'] ?? 0), trim($_POST['remarks'] ?? ''), $_SESSION['user']['id']);
        flash($ok ? 'success' : 'danger', $msg);
    }
    redirect('stock.php');
}

$fp = (int)($_GET['product'] ?? 0);
$ft = $_GET['type'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1)); $per = 15;
$where = []; $params = [];
if ($fp > 0) { $where[] = 'm.product_id = :p'; $params[':p'] = $fp; }
if (in_array($ft, ['in', 'out', 'adjustment'], true)) { $where[] = 'm.type = :t'; $params[':t'] = $ft; }
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$cnt = $pdo->prepare("SELECT COUNT(*) FROM stock_movements m $w"); $cnt->execute($params); $total = (int)$cnt->fetchColumn();
$offset = ($page - 1) * $per;
$st = $pdo->prepare("SELECT m.*, p.name AS product_name, p.sku, p.unit, u.full_name FROM stock_movements m JOIN products p ON p.id = m.product_id LEFT JOIN users u ON u.id = m.user_id $w ORDER BY m.created_at DESC, m.id DESC LIMIT $per OFFSET $offset");
$st->execute($params);
$moves = $st->fetchAll();
$products = $pdo->query('SELECT id, name, sku, quantity, unit FROM products ORDER BY name')->fetchAll();

$pageTitle = 'Stock Management'; $active = 'stock';
include 'includes/header.php';
$typeBadge = ['in' => 'success', 'out' => 'danger', 'adjustment' => 'secondary'];
?>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card"><div class="card-header bg-white fw-semibold">New transaction</div><div class="card-body">
      <form method="post" class="needs-validation" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label">Product *</label>
          <select name="product_id" id="t_product" class="form-select" required><option value="">Select product…</option>
            <?php foreach ($products as $p): ?><option value="<?= $p['id'] ?>" data-qty="<?= (int)$p['quantity'] ?>" data-unit="<?= e($p['unit']) ?>"><?= e($p['name']) ?> (<?= e($p['sku']) ?>)</option><?php endforeach; ?>
          </select><div class="form-text" id="t_current"></div></div>
        <div class="mb-3"><label class="form-label">Type *</label>
          <select name="type" id="t_type" class="form-select" required>
            <option value="in">Stock In (add)</option><option value="out">Stock Out (remove)</option><option value="adjustment">Adjustment (set counted qty)</option>
          </select></div>
        <div class="mb-3"><label class="form-label" id="t_qtyLabel">Quantity *</label><input type="number" min="0" name="quantity" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Remarks</label><input name="remarks" class="form-control" maxlength="255" placeholder="e.g. Delivery from supplier, damaged, recount"></div>
        <button class="btn btn-primary w-100"><i class="bi bi-check2-circle"></i> Record</button>
      </form>
    </div></div>
  </div>
  <div class="col-lg-8">
    <div class="card mb-3"><div class="card-body">
      <form class="row g-2" method="get">
        <div class="col-md-6"><select name="product" class="form-select"><option value="">All products</option>
          <?php foreach ($products as $p): ?><option value="<?= $p['id'] ?>" <?= $fp === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><select name="type" class="form-select"><option value="">All types</option>
          <?php foreach (['in' => 'Stock In', 'out' => 'Stock Out', 'adjustment' => 'Adjustment'] as $k => $l): ?><option value="<?= $k ?>" <?= $ft === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary flex-fill">Filter</button><a href="stock.php" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a></div>
      </form>
    </div></div>
    <div class="card"><div class="card-header bg-white fw-semibold">Stock movement history</div><div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Date</th><th>Product</th><th>Type</th><th class="text-end">Qty</th><th class="text-end">Before → After</th><th>Remarks</th><th>By</th></tr></thead>
        <tbody>
        <?php foreach ($moves as $m): ?>
          <tr>
            <td class="text-nowrap"><?= e(date('M d, Y H:i', strtotime($m['created_at']))) ?></td>
            <td><?= e($m['product_name']) ?><br><small class="text-muted"><?= e($m['sku']) ?></small></td>
            <td><span class="badge text-bg-<?= $typeBadge[$m['type']] ?>"><?= e(ucfirst($m['type'])) ?></span></td>
            <td class="text-end"><?= ($m['type'] === 'adjustment' && $m['quantity'] > 0 ? '+' : '') . (int)$m['quantity'] ?></td>
            <td class="text-end text-nowrap"><?= (int)$m['qty_before'] ?> → <?= (int)$m['qty_after'] ?></td>
            <td><?= e($m['remarks']) ?></td><td><?= e($m['full_name'] ?? '—') ?></td>
          </tr>
        <?php endforeach; if (!$moves): ?><tr><td colspan="7" class="text-center text-muted py-5">No movements recorded.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center"><small class="text-muted"><?= $total ?> record(s)</small><?= pager($total, $per, $page) ?></div></div>
  </div>
</div>
<?php
$extraScripts = <<<'JS'
<script>
const tp = document.getElementById('t_product'), tt = document.getElementById('t_type'), tc = document.getElementById('t_current'), tl = document.getElementById('t_qtyLabel');
function refresh() {
  const o = tp.selectedOptions[0];
  tc.textContent = o && o.dataset.qty !== undefined ? 'Current stock: ' + o.dataset.qty + ' ' + o.dataset.unit : '';
  tl.textContent = tt.value === 'adjustment' ? 'New counted quantity *' : 'Quantity *';
  if (o && o.dataset.qty !== undefined) { const q = document.querySelector('[name=quantity]'); q.min = tt.value === 'adjustment' ? 0 : 1; if (tt.value === 'out') q.max = o.dataset.qty; else q.removeAttribute('max'); }
}
tp.addEventListener('change', refresh); tt.addEventListener('change', refresh); refresh();
</script>
JS;
include 'includes/footer.php';
