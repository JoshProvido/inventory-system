<?php
require 'config.php';
require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    csrf_check();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            if ($name === '') throw new RuntimeException('Category name is required.');
            if ($id) {
                $pdo->prepare('UPDATE categories SET name=?, description=? WHERE id=?')->execute([$name, $desc, $id]);
                flash('success', 'Category updated.');
            } else {
                $pdo->prepare('INSERT INTO categories (name, description) VALUES (?,?)')->execute([$name, $desc]);
                flash('success', 'Category added.');
            }
        } elseif ($action === 'delete') {
            $pdo->prepare('DELETE FROM categories WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
            flash('success', 'Category deleted.');
        }
    } catch (PDOException $ex) {
        $msg = $ex->getCode() === '23000'
            ? ($action === 'delete' ? 'This category still has products. Move or delete them first.' : 'A category with that name already exists.')
            : 'Database error. Please try again.';
        flash('danger', $msg);
    } catch (RuntimeException $ex) {
        flash('danger', $ex->getMessage());
    }
    redirect('categories.php');
}

$rows = $pdo->query('SELECT c.*, COUNT(p.id) AS product_count FROM categories c LEFT JOIN products p ON p.category_id = c.id GROUP BY c.id ORDER BY c.name')->fetchAll();
$pageTitle = 'Categories'; $active = 'categories';
include 'includes/header.php';
?>
<?php if (is_admin()): ?>
<div class="d-flex justify-content-end mb-3"><button class="btn btn-success" onclick="openCat()"><i class="bi bi-plus-lg"></i> Add Category</button></div>
<?php endif; ?>
<div class="card"><div class="table-responsive">
  <table class="table table-hover align-middle mb-0">
    <thead><tr><th>ID</th><th>Name</th><th>Description</th><th class="text-center">Products</th><th>Date created</th><?php if (is_admin()): ?><th class="text-end">Actions</th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= $r['id'] ?></td><td class="fw-semibold"><?= e($r['name']) ?></td><td><?= e($r['description']) ?></td>
        <td class="text-center"><span class="badge text-bg-primary"><?= (int)$r['product_count'] ?></span></td>
        <td><?= e(date('M d, Y', strtotime($r['created_at']))) ?></td>
        <?php if (is_admin()): ?>
        <td class="text-end text-nowrap">
          <button class="btn btn-sm btn-outline-primary" onclick='openCat(<?= e(json_encode($r)) ?>)'><i class="bi bi-pencil"></i></button>
          <form method="post" class="d-inline js-confirm" data-message="Delete category “<?= e($r['name']) ?>”?">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
          </form>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-5">No categories yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div></div>

<div class="modal fade" id="catModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form method="post" class="needs-validation" novalidate>
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="c_id">
    <div class="modal-header"><h5 class="modal-title" id="catTitle">Add Category</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="mb-3"><label class="form-label">Name *</label><input name="name" id="c_name" class="form-control" required maxlength="100"></div>
      <div><label class="form-label">Description</label><textarea name="description" id="c_desc" class="form-control" rows="3"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
  </form>
</div></div></div>
<?php
$extraScripts = <<<'JS'
<script>
const catModal = new bootstrap.Modal('#catModal');
function openCat(c) {
  const f = document.querySelector('#catModal form'); f.reset(); f.classList.remove('was-validated');
  catTitle.textContent = c ? 'Edit Category' : 'Add Category';
  c_id.value = c ? c.id : ''; c_name.value = c ? c.name : ''; c_desc.value = c ? (c.description || '') : '';
  catModal.show();
}
</script>
JS;
include 'includes/footer.php';
