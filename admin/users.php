<?php
require_once BASE_PATH . '/admin/layout.php';
Auth::requireLogin();
Auth::requireSuperAdmin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action = $_POST['_action'] ?? '';

    // ── Delete user ──────────────────────────────────────────
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

    // ── Create / update user ─────────────────────────────────
    if (in_array($action, ['create', 'update'])) {
        $id          = (int)($_POST['id'] ?? 0);
        $username    = trim($_POST['username'] ?? '');
        $email       = strtolower(trim($_POST['email'] ?? ''));
        $role        = in_array($_POST['role'] ?? '', ['superadmin', 'blogger']) ? $_POST['role'] : 'blogger';
        $displayName = trim($_POST['display_name'] ?? '');
        $password    = $_POST['password'] ?? '';

        if (!$username) $errors[] = 'Username is required.';
        if (!$email)    $errors[] = 'Email is required.';
        if ($action === 'create' && !$password) $errors[] = 'Password is required for new users.';
        if ($password && strlen($password) < 8)  $errors[] = 'Password must be at least 8 characters.';

        if ($username && Database::fetch("SELECT id FROM users WHERE username = ? AND id != ?", [$username, $id]))
            $errors[] = 'Username already taken.';
        if ($email && Database::fetch("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $id]))
            $errors[] = 'Email already in use.';

        if (!$errors) {
            if ($action === 'create') {
                $newId = Database::insert('users', [
                    'username'     => $username,
                    'email'        => $email,
                    'password'     => Auth::hashPassword($password),
                    'role'         => $role,
                    'display_name' => $displayName ?: $username,
                ]);
                flash('success', 'User created.');
                redirect('/admin/users?edit=' . $newId);
            } else {
                $data = ['username' => $username, 'email' => $email, 'role' => $role, 'display_name' => $displayName];
                if ($password) $data['password'] = Auth::hashPassword($password);
                Database::update('users', $data, 'id = ?', [$id]);
                flash('success', 'User updated.');
                redirect('/admin/users?edit=' . $id);
            }
        }
    }

    // ── Assign / transfer a blog to a user ───────────────────
    if ($action === 'assign_blog') {
        $blogId  = (int)($_POST['blog_id'] ?? 0);
        $userId  = (int)($_POST['user_id'] ?? 0);
        $blog    = $blogId ? Database::fetch("SELECT * FROM blogs WHERE id = ?", [$blogId]) : null;
        $toUser  = $userId ? Database::fetch("SELECT * FROM users WHERE id = ?", [$userId]) : null;

        if ($blog && $toUser) {
            Database::update('blogs', ['user_id' => $userId], 'id = ?', [$blogId]);
            flash('success', '"' . $blog['name'] . '" transferred to ' . ($toUser['display_name'] ?: $toUser['username']) . '.');
        } else {
            flash('error', 'Invalid blog or user.');
        }
        redirect('/admin/users?edit=' . $userId);
    }

    // ── Create a new blog directly for a user ────────────────
    if ($action === 'create_blog') {
        $userId      = (int)($_POST['user_id'] ?? 0);
        $blogName    = trim($_POST['blog_name'] ?? '');
        $blogSlug    = slugify(trim($_POST['blog_slug'] ?? $blogName));
        $toUser      = $userId ? Database::fetch("SELECT id FROM users WHERE id = ?", [$userId]) : null;

        if (!$toUser)    $errors[] = 'User not found.';
        if (!$blogName)  $errors[] = 'Blog name is required.';
        if (!$blogSlug)  $errors[] = 'Slug is required.';
        if ($blogSlug && Database::fetch("SELECT id FROM blogs WHERE slug = ?", [$blogSlug]))
            $errors[] = 'That slug is already taken.';

        if (!$errors) {
            Database::insert('blogs', [
                'user_id'   => $userId,
                'slug'      => $blogSlug,
                'name'      => $blogName,
                'theme'     => 'minimal',
                'is_public' => 1,
            ]);
            flash('success', 'Blog "' . $blogName . '" created and assigned.');
            redirect('/admin/users?edit=' . $userId);
        }
    }
}

$editId   = (int)($_GET['edit'] ?? 0);
$editUser = $editId ? Database::fetch("SELECT * FROM users WHERE id = ?", [$editId]) : null;
$users    = Database::fetchAll("SELECT * FROM users ORDER BY created_at DESC");

adminLayout('Users', function() use ($users, $errors, $editId, $editUser) {
    // Per-user blog data for the table
    $blogCounts = [];
    foreach (Database::fetchAll("SELECT user_id, COUNT(*) as n FROM blogs GROUP BY user_id") as $r) {
        $blogCounts[$r['user_id']] = (int)$r['n'];
    }
?>
<div class="page-header">
  <h1>Users</h1>
</div>

<?php if ($errors): ?>
<div class="alert alert-error">
  <?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start">

  <!-- ── User form ── -->
  <div class="card">
    <h2 style="margin-bottom:1.25rem"><?= $editUser ? 'Edit User' : 'Create New User' ?></h2>
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
          <option value="blogger"    <?= ($editUser['role'] ?? 'blogger') === 'blogger'    ? 'selected' : '' ?>>Blogger</option>
          <option value="superadmin" <?= ($editUser['role'] ?? '') === 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
        </select>
      </div>
      <div class="form-group">
        <label>Password <?= $editUser ? '<span class="optional">(leave blank to keep current)</span>' : '' ?></label>
        <input type="password" name="password" <?= !$editUser ? 'required' : '' ?> minlength="8" autocomplete="new-password">
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $editUser ? 'Update User' : 'Create User' ?></button>
        <?php if ($editUser): ?><a href="/admin/users" class="btn">New User</a><?php endif; ?>
      </div>
    </form>
  </div>

  <!-- ── Blog assignment (edit mode only) ── -->
  <?php if ($editUser): ?>
  <div>
    <?php
    $userBlogs  = Database::fetchAll("SELECT * FROM blogs WHERE user_id = ? ORDER BY name", [$editUser['id']]);
    $otherBlogs = Database::fetchAll(
        "SELECT b.*, u.display_name as owner_name, u.username as owner_username
         FROM blogs b JOIN users u ON b.user_id = u.id
         WHERE b.user_id != ?
         ORDER BY b.name",
        [$editUser['id']]
    );
    $userName = h($editUser['display_name'] ?: $editUser['username']);
    ?>

    <!-- Current blogs -->
    <div class="card" style="margin-bottom:1.25rem">
      <div class="card-header">
        <h2>Blogs owned by <?= $userName ?></h2>
      </div>
      <?php if ($userBlogs): ?>
      <table class="data-table">
        <thead><tr><th>Blog</th><th>Slug</th><th>Visibility</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($userBlogs as $b): ?>
        <tr>
          <td><strong><?= h($b['name']) ?></strong></td>
          <td><code><?= h($b['slug']) ?></code></td>
          <td><span class="badge badge-<?= $b['is_public'] ? 'published' : 'draft' ?>"><?= $b['is_public'] ? 'Public' : 'Private' ?></span></td>
          <td><a href="<?= h(blogUrl($b)) ?>" target="_blank" class="btn btn-sm">View &#x2197;</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <p style="color:#888;padding:.25rem 0">No blogs yet.</p>
      <?php endif; ?>
    </div>

    <!-- Transfer existing blog -->
    <?php if ($otherBlogs): ?>
    <div class="card" style="margin-bottom:1.25rem">
      <div class="card-header"><h2>Transfer a blog to <?= $userName ?></h2></div>
      <form method="post" class="edit-form">
        <?= csrfField() ?>
        <input type="hidden" name="_action" value="assign_blog">
        <input type="hidden" name="user_id" value="<?= $editUser['id'] ?>">
        <div class="form-group">
          <label>Blog (current owner)</label>
          <select name="blog_id">
            <?php foreach ($otherBlogs as $b): ?>
            <option value="<?= $b['id'] ?>">
              <?= h($b['name']) ?> &mdash; <?= h($b['owner_name'] ?: $b['owner_username']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary"
                  onclick="return confirm('Transfer this blog to <?= $userName ?>?')">
            Transfer Ownership
          </button>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <!-- Create new blog for this user -->
    <div class="card">
      <div class="card-header"><h2>Create a new blog for <?= $userName ?></h2></div>
      <form method="post" class="edit-form">
        <?= csrfField() ?>
        <input type="hidden" name="_action" value="create_blog">
        <input type="hidden" name="user_id" value="<?= $editUser['id'] ?>">
        <div class="form-group">
          <label>Blog Name</label>
          <input type="text" name="blog_name" placeholder="My Blog"
                 oninput="if(document.getElementById('nb-slug').dataset.auto!='0') document.getElementById('nb-slug').value=slugify(this.value)">
        </div>
        <div class="form-group">
          <label>URL Slug</label>
          <input type="text" id="nb-slug" name="blog_slug" placeholder="my-blog"
                 data-auto="1" oninput="this.dataset.auto='0'">
          <small>blogs.myocci.net/<span id="nb-slug-preview"></span></small>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Create &amp; Assign</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- ── User table ── -->
<div class="card" style="margin-top:1.5rem">
  <div class="card-header"><h2>All Users (<?= count($users) ?>)</h2></div>
  <table class="data-table">
    <thead><tr><th>User</th><th>Role</th><th>Blogs</th><th>Joined</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
    <tr <?= $u['id'] === ($editUser['id'] ?? 0) ? 'style="background:#f8f5ff"' : '' ?>>
      <td>
        <strong><?= h($u['display_name'] ?: $u['username']) ?></strong>
        <div class="text-muted"><?= h($u['email']) ?></div>
      </td>
      <td><span class="badge badge-<?= $u['role'] === 'superadmin' ? 'published' : 'draft' ?>"><?= h($u['role']) ?></span></td>
      <td><?= $blogCounts[$u['id']] ?? 0 ?></td>
      <td><?= formatDate($u['created_at'], 'M j, Y') ?></td>
      <td class="actions">
        <a href="/admin/users?edit=<?= $u['id'] ?>" class="btn btn-sm">Edit</a>
        <?php if ($u['id'] !== Auth::id()): ?>
        <form method="post" style="display:inline"
              onsubmit="return confirm('Delete this user and all their blogs and posts?')">
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

<script>
function slugify(str) {
  return str.toLowerCase().trim().replace(/[^\w\s-]/g,'').replace(/[\s_-]+/g,'-').replace(/^-+|-+$/g,'');
}
document.getElementById('nb-slug')?.addEventListener('input', function() {
  document.getElementById('nb-slug-preview').textContent = this.value;
});
</script>
<?php });
