<?php
require 'config.php';
require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['full_name'] ?? '');
            $user = trim($_POST['username'] ?? '');
            $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'staff';
            $pass = $_POST['password'] ?? '';
            if ($name === '' || $user === '') throw new RuntimeException('Full name and username are required.');
            if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $user)) throw new RuntimeException('Username must be 3–50 characters: letters, numbers, dot, dash or underscore.');
            if ($id) {
                if ($id === (int)$_SESSION['user']['id'] && $role !== 'admin') throw new RuntimeException('You cannot remove your own admin role.');
                if ($pass !== '') {
                    if (strlen($pass) < 6) throw new RuntimeException('Password must be at least 6 characters.');
                    $pdo->prepare('UPDATE users SET full_name=?, username=?, role=?, password_hash=? WHERE id=?')->execute([$name, $user, $role, password_hash($pass, PASSWORD_DEFAULT), $id]);
                } else {
                    $pdo->prepare('UPDATE users SET full_name=?, username=?, role=? WHERE id=?')->execute([$name, $user, $role, $id]);
                }
                if ($id === (int)$_SESSION['user']['id']) { $_SESSION['user']['full_name'] = $name; $_SESSION['user']['username'] = $user; }
                flash('success', 'User updated.');
            } else {
                if (strlen($pass) < 6) throw new RuntimeException('Password must be at least 6 characters.');
                $pdo->prepare('INSERT INTO users (full_name, username, password_hash, role) VALUES (?,?,?,?)')->execute([$name, $user, password_hash($pass, PASSWORD_DEFAULT), $role]);
                flash('success', 'User created.');
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id === (int)$_SESSION['user']['id']) throw new RuntimeException('You cannot delete your own account.');
            $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
            flash('success', 'User deleted.');
        }
    } catch (PDOException $ex) {
        flash('danger', $ex->getCode() === '23000' ? 'That username is already taken.' : 'Database error. Please try again.');
    } catch (RuntimeException $ex) {
        flash('danger', $ex->getMessage());
    }
    redirect('users.php');
}

$rows = $pdo->query('SELECT id, full_name, username, role, created_at FROM users ORDER BY full_name')->fetchAll();
$pageTitle = 'Users'; $active = 'users';
include 'includes/header.php';
?>
<div class="d-flex justify-content-end mb-3"><button class="btn btn-success" onclick="openUser()"><i class="bi bi-person-plus"></i> Add User</button></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Created</th><th class="text-end">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="fw-semibold"><?= e($r['full_name']) ?></td><td><?= e($r['username']) ?></td>
      <td><span class="badge text-bg-<?= $r['role'] === 'admin' ? 'primary' : 'secondary' ?>"><?= e(ucfirst($r['role'])) ?></span></td>
      <td><?= e(date('M d, Y', strtotime($r['created_at']))) ?></td>
      <td class="text-end text-nowrap">
        <button class="btn btn-sm btn-outline-primary" onclick='openUser(<?= e(json_encode($r)) ?>)'><i class="bi bi-pencil"></i></button>
        <?php if ((int)$r['id'] !== (int)$_SESSION['user']['id']): ?>
        <form method="post" class="d-inline js-confirm" data-message="Delete user “<?= e($r['username']) ?>”?">
          <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
          <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>

<div class="modal fade" id="userModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form method="post" class="needs-validation" novalidate autocomplete="off">
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="u_id">
    <div class="modal-header"><h5 class="modal-title" id="userTitle">Add User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><div class="row g-3">
      <div class="col-12"><label class="form-label">Full name *</label><input name="full_name" id="u_name" class="form-control" required maxlength="100"></div>
      <div class="col-md-6"><label class="form-label">Username *</label><input name="username" id="u_user" class="form-control" required maxlength="50" pattern="[A-Za-z0-9_.\-]{3,50}"></div>
      <div class="col-md-6"><label class="form-label">Role *</label><select name="role" id="u_role" class="form-select"><option value="staff">Staff</option><option value="admin">Admin</option></select></div>
      <div class="col-12"><label class="form-label" id="u_passLabel">Password *</label><input type="password" name="password" id="u_pass" class="form-control" minlength="6" autocomplete="new-password"></div>
    </div></div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
  </form>
</div></div></div>
<?php
$extraScripts = <<<'JS'
<script>
const userModal = new bootstrap.Modal('#userModal');
function openUser(u) {
  const f = document.querySelector('#userModal form'); f.reset(); f.classList.remove('was-validated');
  userTitle.textContent = u ? 'Edit User' : 'Add User';
  u_id.value = u ? u.id : ''; u_name.value = u ? u.full_name : ''; u_user.value = u ? u.username : ''; u_role.value = u ? u.role : 'staff';
  u_pass.required = !u; u_passLabel.textContent = u ? 'New password (leave blank to keep current)' : 'Password *';
  userModal.show();
}
</script>
JS;
include 'includes/footer.php';
