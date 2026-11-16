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
            $contact = trim($_POST['contact_person'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $addr = trim($_POST['address'] ?? '');
            if ($name === '') throw new RuntimeException('Supplier name is required.');
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Please enter a valid email address.');
            if ($id) {
                $pdo->prepare('UPDATE suppliers SET name=?, contact_person=?, phone=?, email=?, address=? WHERE id=?')->execute([$name, $contact, $phone, $email, $addr, $id]);
                flash('success', 'Supplier updated.');
            } else {
                $pdo->prepare('INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES (?,?,?,?,?)')->execute([$name, $contact, $phone, $email, $addr]);
                flash('success', 'Supplier added.');
            }
        } elseif ($action === 'delete') {
            $pdo->prepare('DELETE FROM suppliers WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
            flash('success', 'Supplier deleted.');
        }
    } catch (PDOException $ex) {
        flash('danger', $ex->getCode() === '23000' ? 'This supplier is still linked to products. Reassign those products first.' : 'Database error. Please try again.');
    } catch (RuntimeException $ex) {
        flash('danger', $ex->getMessage());
    }
    redirect('suppliers.php');
}

$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1)); $per = 10;
$where = ''; $params = [];
if ($q !== '') {
    $where = 'WHERE name LIKE :a OR contact_person LIKE :b OR phone LIKE :c OR email LIKE :d';
    foreach (['a', 'b', 'c', 'd'] as $k) $params[":$k"] = "%$q%";
}
$cnt = $pdo->prepare("SELECT COUNT(*) FROM suppliers $where"); $cnt->execute($params); $total = (int)$cnt->fetchColumn();
$offset = ($page - 1) * $per;
$st = $pdo->prepare("SELECT * FROM suppliers $where ORDER BY name LIMIT $per OFFSET $offset"); $st->execute($params);
$rows = $st->fetchAll();

$pageTitle = 'Suppliers'; $active = 'suppliers';
include 'includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
  <form class="d-flex gap-2" method="get">
    <input type="search" name="q" value="<?= e($q) ?>" class="form-control" placeholder="Search suppliers">
    <button class="btn btn-primary"><i class="bi bi-search"></i></button>
    <?php if ($q !== ''): ?><a href="suppliers.php" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a><?php endif; ?>
  </form>
  <?php if (is_admin()): ?><button class="btn btn-success" onclick="openSup()"><i class="bi bi-plus-lg"></i> Add Supplier</button><?php endif; ?>
</div>
<div class="card"><div class="table-responsive">
  <table class="table table-hover align-middle mb-0">
    <thead><tr><th>ID</th><th>Supplier</th><th>Contact person</th><th>Phone</th><th>Email</th><th>Address</th><th>Added</th><?php if (is_admin()): ?><th class="text-end">Actions</th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= $r['id'] ?></td><td class="fw-semibold"><?= e($r['name']) ?></td><td><?= e($r['contact_person']) ?></td><td><?= e($r['phone']) ?></td>
        <td><?= e($r['email']) ?></td><td><?= e($r['address']) ?></td><td><?= e(date('M d, Y', strtotime($r['created_at']))) ?></td>
        <?php if (is_admin()): ?>
        <td class="text-end text-nowrap">
          <button class="btn btn-sm btn-outline-primary" onclick='openSup(<?= e(json_encode($r)) ?>)'><i class="bi bi-pencil"></i></button>
          <form method="post" class="d-inline js-confirm" data-message="Delete supplier “<?= e($r['name']) ?>”?">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
          </form>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-5">No suppliers found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<div class="card-footer bg-white d-flex justify-content-between align-items-center"><small class="text-muted"><?= $total ?> supplier(s)</small><?= pager($total, $per, $page) ?></div></div>

<div class="modal fade" id="supModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form method="post" class="needs-validation" novalidate>
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="s_id">
    <div class="modal-header"><h5 class="modal-title" id="supTitle">Add Supplier</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><div class="row g-3">
      <div class="col-12"><label class="form-label">Supplier name *</label><input name="name" id="s_name" class="form-control" required maxlength="150"></div>
      <div class="col-md-6"><label class="form-label">Contact person</label><input name="contact_person" id="s_contact" class="form-control" maxlength="100"></div>
      <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" id="s_phone" class="form-control" maxlength="30"></div>
      <div class="col-12"><label class="form-label">Email</label><input type="email" name="email" id="s_email" class="form-control" maxlength="120"></div>
      <div class="col-12"><label class="form-label">Business address</label><input name="address" id="s_addr" class="form-control" maxlength="255"></div>
    </div></div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
  </form>
</div></div></div>
<?php
$extraScripts = <<<'JS'
<script>
const supModal = new bootstrap.Modal('#supModal');
function openSup(s) {
  const f = document.querySelector('#supModal form'); f.reset(); f.classList.remove('was-validated');
  supTitle.textContent = s ? 'Edit Supplier' : 'Add Supplier';
  s_id.value = s ? s.id : ''; s_name.value = s ? s.name : ''; s_contact.value = s ? (s.contact_person || '') : '';
  s_phone.value = s ? (s.phone || '') : ''; s_email.value = s ? (s.email || '') : ''; s_addr.value = s ? (s.address || '') : '';
  supModal.show();
}
</script>
JS;
include 'includes/footer.php';
