<?php
require_once BASE_PATH . '/admin/layout.php';
Auth::requireLogin();
Auth::requireSuperAdmin();

$errors = [];

// Handle create/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action = $_POST['_action'] ?? '';

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === Auth::id()) {
            flash('error', 'You cannot delete your own account.');
        } else {
            Database::delete('users', 'id = ?', [$id]);
            flash('success', 'User deleted.');
        }
        redirect('/admin/users');
    }

    if (in_array($action, ['create', 'update'])) {
        $id          = (int)($_POST['id'] ?? 0);
        $username    = trim($_POST['username'] ?? '');
        $email       = strtolower(trim($_POST['email'] ?? ''));
        $role        = in_array($_POST['role'] ?? '', ['superadmin', 'blogger']) ? $_POST['role'] : 'blogger';
        $displayName = trim($_POST['display_name'] ?? '');
        $password    = $_POST['password'] ?? '';

        if (!$username)  $errors[] = 'Username is required.';
        if (!$email)     $errors[] = 'Email is required.';
        if ($action === 'create' && !$password) $errors[] = 'Password is required for new users.';
        if ($password && strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';

        // Uniqueness checks
        if ($username && Database::fetch("SELECT id FROM users WHERE username = ? AND id != ?", [$username, $id])) $errors[] = 'Username already taken.';
        if ($email && Database::fetch("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $id])) $errors[] = 'Email already in use.';

        if (!$errors) {
            if ($action === 'create') {
                Database::insert('users', [
                    'username'     => $username,
                    'email'        => $email,
                    'password'     => Auth::hashPassword($password),
                    'role'         => $role,
                    'display_name' => $displayName ?: $username,
                ]);
                flash('success', 'User created.');
            } else {
                $data = ['username' => $username, 'email' => $email, 'role' => $role, 'display_name' => $displayName];
                if ($password) $data['password'] = Auth::hashPassword($password);
                Database::update('users', $data, 'id = ?', [$id]);
                flash('success', 'User updated.');
            }
            redirect('/admin/users');
        }
    }
}

$editId   = (int)($_GET['edit'] ?? 0);
$editUser = $editId ? Database::fetch("SELECT * FROM users WHERE id = ?", [$editId]) : null;
$users    = Database::fetchAll("SELECT * FROM users ORDER BY created_at DESC");

adminLayout('Users', function() use ($users, $errors, $editId, $editUser) { ?>
<div class="page-header">
  <h1>Users</h1>
</div>

<?php if ($errors): ?>
<div class="alert alert-error">
  <?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<!-- User form -->
<div class="card" style="max-width:560px;margin-bottom:2rem">
  <h2 style="margin-bottom:1rem"><?= $editUser ? 'Edit User' : 'Create New User' ?></h2>
  <form method="post" class="edit-form">
    <?= csrfField() ?>
    <input type="hidden" name="_action" value="<?= $editUser ? 'update' : 'create' ?>">
    <?php if ($editUser): ?><input type="hidden" name="id" value="<?= $editUser['id'] ?>"><?php endif; ?>
    <div class="form-group">
      <label>Username</label>
      <input type="text" name="username" value="<?= h($editUser['username'] ?? '') ?>" required>
    </div>
    <div class="form-group">
      <label>Email</label>
      <input type="email" name="email" value="<?= h($editUser['email'] ?? '') ?>" required>
    </div>
    <div class="form-group">
      <label>Display Name</label>
      <input type="text" name="display_name" value="<?= h($editUser['display_name'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Role</label>
      <select name="role">
        <option value="blogger" <?= ($editUser['role'] ?? 'blogger') === 'blogger' ? 'selected' : '' ?>>Blogger</option>
        <option value="superadmin" <?= ($editUser['role'] ?? '') === 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
      </select>
    </div>
    <div class="form-group">
      <label>Password <?= $editUser ? '<span class="optional">(leave blank to keep current)</span>' : '' ?></label>
      <input type="password" name="password" <?= !$editUser ? 'required' : '' ?> minlength="8" autocomplete="new-password">
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary"><?= $editUser ? 'Update User' : 'Create User' ?></button>
      <?php if ($editUser): ?><a href="/admin/users" class="btn">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<!-- User list -->
<div class="card">
  <table class="data-table">
    <thead><tr><th>User</th><th>Role</th><th>Blogs</th><th>Joined</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u):
        $blogCount = (int)Database::fetch("SELECT COUNT(*) as n FROM blogs WHERE user_id = ?", [$u['id']])['n'];
    ?>
    <tr>
      <td>
        <strong><?= h($u['display_name'] ?: $u['username']) ?></strong>
        <div class="text-muted"><?= h($u['email']) ?></div>
      </td>
      <td><span class="badge badge-<?= $u['role'] === 'superadmin' ? 'published' : 'draft' ?>"><?= h($u['role']) ?></span></td>
      <td><?= $blogCount ?></td>
      <td><?= formatDate($u['created_at'], 'M j, Y') ?></td>
      <td class="actions">
        <a href="/admin/users?edit=<?= $u['id'] ?>" class="btn btn-sm">Edit</a>
        <?php if ($u['id'] !== Auth::id()): ?>
        <form method="post" style="display:inline" onsubmit="return confirm('Delete this user and all their blogs?')">
          <?= csrfField() ?>
          <input type="hidden" name="_action" value="delete">
          <input type="hidden" name="id" value="<?= $u['id'] ?>">
          <button type="submit" class="btn btn-sm btn-danger">Delete</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php });
