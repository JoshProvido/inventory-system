<?php
require 'config.php';
require_login();
$pdo = db();

$types = [
    'inventory' => 'Complete Inventory Report',
    'low' => 'Low Stock Report',
    'out' => 'Out of Stock Report',
    'movement' => 'Stock Movement Report',
    'valuation' => 'Inventory Valuation Report',
];
$type = $_GET['type'] ?? 'inventory';
if (!isset($types[$type])) $type = 'inventory';
$from = $_GET['from'] ?? ''; $to = $_GET['to'] ?? '';
$q = trim($_GET['q'] ?? ''); $cat = (int)($_GET['category'] ?? 0);
if ($from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = '';
if ($to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = '';

$where = []; $params = [];
if ($q !== '') { $where[] = '(p.name LIKE :q1 OR p.sku LIKE :q2)'; $params[':q1'] = "%$q%"; $params[':q2'] = "%$q%"; }
if ($cat > 0) { $where[] = 'p.category_id = :cat'; $params[':cat'] = $cat; }

if ($type === 'movement') {
    $dateCol = 'm.created_at';
    if ($from !== '') { $where[] = "DATE($dateCol) >= :from"; $params[':from'] = $from; }
    if ($to !== '') { $where[] = "DATE($dateCol) <= :to"; $params[':to'] = $to; }
    $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT m.created_at AS `Date`, p.name AS `Product`, p.sku AS `SKU`, UPPER(m.type) AS `Type`, m.quantity AS `Quantity`,
            m.qty_before AS `Before`, m.qty_after AS `After`, COALESCE(u.full_name, '') AS `By`, COALESCE(m.remarks, '') AS `Remarks`
            FROM stock_movements m JOIN products p ON p.id = m.product_id LEFT JOIN users u ON u.id = m.user_id $w ORDER BY m.created_at DESC, m.id DESC";
} else {
    if ($type === 'low') $where[] = 'p.quantity > 0 AND p.quantity <= p.min_stock';
    if ($type === 'out') $where[] = 'p.quantity = 0';
    if ($from !== '') { $where[] = 'DATE(p.created_at) >= :from'; $params[':from'] = $from; }
    if ($to !== '') { $where[] = 'DATE(p.created_at) <= :to'; $params[':to'] = $to; }
    $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $from_ = "FROM products p JOIN categories c ON c.id = p.category_id LEFT JOIN suppliers s ON s.id = p.supplier_id";
    if ($type === 'valuation') {
        $sql = "SELECT p.sku AS `SKU`, p.name AS `Product`, c.name AS `Category`, p.quantity AS `Qty`, p.purchase_price AS `Unit Cost`, p.selling_price AS `Unit Price`,
                ROUND(p.quantity * p.purchase_price, 2) AS `Cost Value`, ROUND(p.quantity * p.selling_price, 2) AS `Retail Value` $from_ $w ORDER BY p.name";
    } else {
        $sql = "SELECT p.sku AS `SKU`, p.name AS `Product`, c.name AS `Category`, COALESCE(s.name, '') AS `Supplier`, p.quantity AS `Qty`, p.min_stock AS `Min Stock`, p.unit AS `Unit`,
                CASE WHEN p.quantity = 0 THEN 'Out of Stock' WHEN p.quantity <= p.min_stock THEN 'Low Stock' ELSE 'In Stock' END AS `Status` $from_ $w ORDER BY p.name";
    }
}
$st = $pdo->prepare($sql); $st->execute($params);
$rows = $st->fetchAll();
$headers = $rows ? array_keys($rows[0]) : [];

// CSV export (must run before any HTML output)
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $type . '_report_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens it correctly
    $safe = fn($v) => (is_string($v) && $v !== '' && strpbrk($v[0], '=+-@') !== false) ? "'" . $v : $v; // block CSV formula injection
    fputcsv($out, $headers ?: ['No data']);
    foreach ($rows as $r) fputcsv($out, array_map($safe, array_values($r)));
    fclose($out);
    exit;
}

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$pageTitle = 'Reports'; $active = 'reports';
include 'includes/header.php';
$moneyCols = ['Unit Cost', 'Unit Price', 'Cost Value', 'Retail Value'];
?>
<div class="card mb-3 no-print"><div class="card-body">
  <form class="row g-2 align-items-end" method="get">
    <div class="col-md-3"><label class="form-label small mb-1">Report</label>
      <select name="type" class="form-select"><?php foreach ($types as $k => $l): ?><option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1">From</label><input type="date" name="from" value="<?= e($from) ?>" class="form-control"></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1">To</label><input type="date" name="to" value="<?= e($to) ?>" class="form-control"></div>
    <div class="col-md-2"><label class="form-label small mb-1">Category</label>
      <select name="category" class="form-select"><option value="">All</option><?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label small mb-1">Search</label><input type="search" name="q" value="<?= e($q) ?>" class="form-control" placeholder="Name or SKU"></div>
    <div class="col-12 d-flex flex-wrap gap-2 mt-3">
      <button class="btn btn-primary"><i class="bi bi-funnel"></i> Generate</button>
      <a class="btn btn-outline-secondary" href="reports.php"><i class="bi bi-x-lg"></i> Reset</a>
      <button type="button" class="btn btn-outline-dark ms-md-auto" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
      <a class="btn btn-success" href="<?= e(qs(['export' => 'csv', 'type' => $type])) ?>"><i class="bi bi-filetype-csv"></i> Export CSV</a>
    </div>
    <div class="form-text">The date range filters <?= $type === 'movement' ? 'the transaction date' : 'the date each product was added' ?>.</div>
  </form>
</div></div>

<div class="card"><div class="card-header bg-white">
  <h2 class="h5 mb-0"><?= e($types[$type]) ?></h2>
  <small class="text-muted">Generated <?= e(date('M d, Y H:i')) ?><?= $from || $to ? ' · ' . e($from ?: '…') . ' to ' . e($to ?: '…') : '' ?></small>
</div>
<div class="table-responsive"><table class="table table-striped align-middle mb-0">
  <thead><tr><?php foreach ($headers as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead>
  <tbody>
  <?php $sum = ['Cost Value' => 0, 'Retail Value' => 0]; foreach ($rows as $r): ?>
    <tr><?php foreach ($r as $k => $v): ?>
      <td class="<?= in_array($k, $moneyCols, true) ? 'text-end' : '' ?>"><?= in_array($k, $moneyCols, true) ? money($v) : e($v) ?></td>
    <?php endforeach; ?></tr>
    <?php if ($type === 'valuation') { $sum['Cost Value'] += $r['Cost Value']; $sum['Retail Value'] += $r['Retail Value']; } ?>
  <?php endforeach; if (!$rows): ?><tr><td class="text-center text-muted py-5">No records match your filters.</td></tr><?php endif; ?>
  </tbody>
  <?php if ($type === 'valuation' && $rows): ?>
  <tfoot><tr class="fw-bold table-light"><td colspan="6" class="text-end">Totals</td><td class="text-end"><?= money($sum['Cost Value']) ?></td><td class="text-end"><?= money($sum['Retail Value']) ?></td></tr></tfoot>
  <?php endif; ?>
</table></div>
<div class="card-footer bg-white"><small class="text-muted"><?= count($rows) ?> row(s)</small></div></div>
<?php include 'includes/footer.php';
