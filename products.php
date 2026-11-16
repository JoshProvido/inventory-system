<?php
require 'config.php';
require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $sku = trim($_POST['sku'] ?? '');
            $cat = (int)($_POST['category_id'] ?? 0);
            $sup = ($_POST['supplier_id'] ?? '') !== '' ? (int)$_POST['supplier_id'] : null;
            $desc = trim($_POST['description'] ?? '');
            $pp = (float)($_POST['purchase_price'] ?? 0);
            $sp = (float)($_POST['selling_price'] ?? 0);
            $min = (int)($_POST['min_stock'] ?? 0);
            $unit = trim($_POST['unit'] ?? 'pcs') ?: 'pcs';
            $qty = max(0, (int)($_POST['quantity'] ?? 0));

            if ($name === '' || $sku === '' || $cat < 1) throw new RuntimeException('Name, SKU and category are required.');
            if ($pp < 0 || $sp < 0 || $min < 0) throw new RuntimeException('Prices and minimum stock cannot be negative.');

            if ($id) {
                $pdo->prepare('UPDATE products SET name=?, sku=?, category_id=?, supplier_id=?, description=?, purchase_price=?, selling_price=?, min_stock=?, unit=? WHERE id=?')
                    ->execute([$name, $sku, $cat, $sup, $desc, $pp, $sp, $min, $unit, $id]);
                flash('success', 'Product updated.');
            } else {
                $pdo->prepare('INSERT INTO products (name, sku, category_id, supplier_id, description, purchase_price, selling_price, quantity, min_stock, unit) VALUES (?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$name, $sku, $cat, $sup, $desc, $pp, $sp, $qty, $min, $unit]);
                $newId = (int)$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO stock_movements (product_id, user_id, type, quantity, qty_before, qty_after, remarks) VALUES (?,?, 'in', ?, 0, ?, 'Initial stock')")
                    ->execute([$newId, $_SESSION['user']['id'], $qty, $qty]);
                flash('success', 'Product added.');
            }
        } elseif ($action === 'delete') {
            $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
            flash('success', 'Product deleted (its stock history was removed too).');
        }
    } catch (PDOException $ex) {
        flash('danger', $ex->getCode() === '23000' ? 'That SKU already exists. SKUs must be unique.' : 'Database error. Please try again.');
    } catch (RuntimeException $ex) {
        flash('danger', $ex->getMessage());
    }
    redirect('products.php');
}

$q = trim($_GET['q'] ?? '');
$cat = (int)($_GET['category'] ?? 0);
$sortMap = ['name' => 'p.name', 'sku' => 'p.sku', 'category' => 'c.name', 'quantity' => 'p.quantity', 'price' => 'p.selling_price', 'added' => 'p.created_at'];
$sort = $_GET['sort'] ?? 'name';
if (!isset($sortMap[$sort])) $sort = 'name';
$dir = (($_GET['dir'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 10;

$where = []; $params = [];
if ($q !== '') { $where[] = '(p.name LIKE :q1 OR p.sku LIKE :q2)'; $params[':q1'] = "%$q%"; $params[':q2'] = "%$q%"; }
if ($cat > 0) { $where[] = 'p.category_id = :cat'; $params[':cat'] = $cat; }
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$cnt = $pdo->prepare("SELECT COUNT(*) FROM products p $w");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();
$offset = ($page - 1) * $per;

$st = $pdo->prepare("SELECT p.*, c.name AS category_name, s.name AS supplier_name FROM products p JOIN categories c ON c.id = p.category_id LEFT JOIN suppliers s ON s.id = p.supplier_id $w ORDER BY {$sortMap[$sort]} $dir LIMIT $per OFFSET $offset");
$st->execute($params);
$products = $st->fetchAll();

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$suppliers = $pdo->query('SELECT id, name FROM suppliers ORDER BY name')->fetchAll();

$pageTitle = 'Products'; $active = 'products';
include 'includes/header.php';
?>
<div class="card mb-3"><div class="card-body">
  <form class="row g-2 align-items-end" method="get">
    <div class="col-12 col-md-5"><label class="form-label small mb-1">Search</label>
      <input type="search" name="q" value="<?= e($q) ?>" class="form-control" placeholder="Product name or SKU"></div>
    <div class="col-6 col-md-3"><label class="form-label small mb-1">Category</label>
      <select name="category" class="form-select"><option value="">All categories</option>
        <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-6 col-md-2 d-flex gap-2"><button class="btn btn-primary flex-fill"><i class="bi bi-search"></i> Filter</button>
      <a href="products.php" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-x-lg"></i></a></div>
    <div class="col-12 col-md-2 text-md-end"><button type="button" class="btn btn-success w-100" onclick="openProduct()"><i class="bi bi-plus-lg"></i> Add Product</button></div>
  </form>
</div></div>

<div class="card"><div class="table-responsive">
  <table class="table table-hover align-middle mb-0">
    <thead><tr>
      <th><?= sort_link('sku', 'SKU', $sort, $dir) ?></th>
      <th><?= sort_link('name', 'Product', $sort, $dir) ?></th>
      <th><?= sort_link('category', 'Category', $sort, $dir) ?></th>
      <th class="text-end"><?= sort_link('price', 'Price', $sort, $dir) ?></th>
      <th class="text-end"><?= sort_link('quantity', 'Stock', $sort, $dir) ?></th>
      <th>Status</th><th class="text-end">Actions</th>
    </tr></thead>
    <tbody>
    <?php foreach ($products as $p): ?>
      <tr>
        <td><code><?= e($p['sku']) ?></code></td>
        <td class="fw-semibold"><?= e($p['name']) ?></td>
        <td><?= e($p['category_name']) ?></td>
        <td class="text-end"><?= money($p['selling_price']) ?></td>
        <td class="text-end"><?= (int)$p['quantity'] ?> <small class="text-muted"><?= e($p['unit']) ?></small></td>
        <td><?= stock_badge((int)$p['quantity'], (int)$p['min_stock']) ?></td>
        <td class="text-end text-nowrap">
          <button class="btn btn-sm btn-outline-info" title="View" onclick='viewProduct(<?= e(json_encode($p)) ?>)'><i class="bi bi-eye"></i></button>
          <button class="btn btn-sm btn-outline-primary" title="Edit" onclick='openProduct(<?= e(json_encode($p)) ?>)'><i class="bi bi-pencil"></i></button>
          <form method="post" class="d-inline js-confirm" data-message="Delete “<?= e($p['name']) ?>”? Its stock history will also be deleted.">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $p['id'] ?>">
            <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
    <?php endforeach; if (!$products): ?>
      <tr><td colspan="7" class="text-center text-muted py-5">No products found.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
  <small class="text-muted"><?= $total ?> product(s)</small><?= pager($total, $per, $page) ?>
</div></div>

<div class="modal fade" id="productModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
  <form method="post" class="needs-validation" novalidate>
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="f_id">
    <div class="modal-header"><h5 class="modal-title" id="productModalTitle">Add Product</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><div class="row g-3">
      <div class="col-md-8"><label class="form-label">Product name *</label><input name="name" id="f_name" class="form-control" required maxlength="150"></div>
      <div class="col-md-4"><label class="form-label">SKU / Barcode *</label><input name="sku" id="f_sku" class="form-control" required maxlength="60"></div>
      <div class="col-md-6"><label class="form-label">Category *</label>
        <select name="category_id" id="f_category" class="form-select" required><option value="">Select…</option>
          <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-6"><label class="form-label">Supplier</label>
        <select name="supplier_id" id="f_supplier" class="form-select"><option value="">— None —</option>
          <?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-12"><label class="form-label">Description</label><textarea name="description" id="f_description" class="form-control" rows="2"></textarea></div>
      <div class="col-md-3"><label class="form-label">Purchase price *</label><input type="number" step="0.01" min="0" name="purchase_price" id="f_pp" class="form-control" required></div>
      <div class="col-md-3"><label class="form-label">Selling price *</label><input type="number" step="0.01" min="0" name="selling_price" id="f_sp" class="form-control" required></div>
      <div class="col-md-2"><label class="form-label">Min. stock *</label><input type="number" min="0" name="min_stock" id="f_min" class="form-control" value="5" required></div>
      <div class="col-md-2"><label class="form-label">Unit *</label><input name="unit" id="f_unit" class="form-control" value="pcs" required maxlength="20"></div>
      <div class="col-md-2" id="qtyGroup"><label class="form-label">Initial qty</label><input type="number" min="0" name="quantity" id="f_qty" class="form-control" value="0"></div>
      <div class="col-12 d-none" id="qtyNote"><div class="form-text"><i class="bi bi-info-circle"></i> To change quantity on an existing product, use <a href="stock.php">Stock Management</a> so the movement is recorded.</div></div>
    </div></div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
  </form>
</div></div></div>

<div class="modal fade" id="viewModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="v_name"></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><table class="table table-sm mb-0"><tbody id="v_body"></tbody></table></div>
</div></div></div>

<?php
$cur = CURRENCY;
$extraScripts = <<<JS
<script>
const productModal = new bootstrap.Modal('#productModal');
const viewModal = new bootstrap.Modal('#viewModal');
const money = n => '{$cur}' + Number(n).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
function openProduct(p) {
  const f = document.querySelector('#productModal form');
  f.classList.remove('was-validated'); f.reset();
  const edit = !!p;
  document.getElementById('productModalTitle').textContent = edit ? 'Edit Product' : 'Add Product';
  document.getElementById('qtyGroup').classList.toggle('d-none', edit);
  document.getElementById('qtyNote').classList.toggle('d-none', !edit);
  document.getElementById('f_id').value = edit ? p.id : '';
  if (edit) {
    f_name.value = p.name; f_sku.value = p.sku; f_category.value = p.category_id; f_supplier.value = p.supplier_id || '';
    f_description.value = p.description || ''; f_pp.value = p.purchase_price; f_sp.value = p.selling_price;
    f_min.value = p.min_stock; f_unit.value = p.unit;
  }
  productModal.show();
}
function viewProduct(p) {
  v_name.textContent = p.name;
  const rows = [['SKU', p.sku], ['Category', p.category_name], ['Supplier', p.supplier_name || '—'], ['Description', p.description || '—'],
    ['Purchase price', money(p.purchase_price)], ['Selling price', money(p.selling_price)], ['In stock', p.quantity + ' ' + p.unit],
    ['Minimum stock', p.min_stock], ['Date added', p.created_at], ['Last updated', p.updated_at]];
  v_body.innerHTML = '';
  rows.forEach(([k, v]) => { const tr = document.createElement('tr'); const th = document.createElement('th'); th.textContent = k; const td = document.createElement('td'); td.textContent = v; tr.append(th, td); v_body.appendChild(tr); });
  viewModal.show();
}
</script>
JS;
include 'includes/footer.php';
