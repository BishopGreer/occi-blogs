<?php
require_once BASE_PATH . '/admin/layout.php';
Auth::requireLogin();

$isSuperAdmin = Auth::isSuperAdmin();
$blogs = $isSuperAdmin
    ? Database::fetchAll("SELECT b.*, u.username FROM blogs b JOIN users u ON b.user_id = u.id ORDER BY b.created_at DESC")
    : Database::fetchAll("SELECT * FROM blogs WHERE user_id = ? ORDER BY created_at DESC", [Auth::id()]);

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'delete') {
    Auth::verifyCsrf();
    $id = (int)($_POST['id'] ?? 0);
    if (Auth::ownsBlog($id)) {
        Database::delete('blogs', 'id = ?', [$id]);
        flash('success', 'Blog deleted.');
    }
    redirect('/admin/blogs');
}

adminLayout('My Blogs', function() use ($blogs) { ?>
<div class="page-header">
  <h1>My Blogs</h1>
  <a href="/admin/blogs/new" class="btn btn-primary">+ New Blog</a>
</div>

<?php if (!$blogs): ?>
<div class="empty-state">
  <p>You have not created any blogs yet.</p>
  <a href="/admin/blogs/new" class="btn btn-primary">Create your first blog</a>
</div>
<?php else: ?>
<div class="card">
  <table class="data-table">
    <thead>
      <tr><th>Blog</th><th>Theme</th><th>Posts</th><th>Visibility</th><th>Created</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($blogs as $b):
        $postCount = (int)Database::fetch("SELECT COUNT(*) as n FROM posts WHERE blog_id = ?", [$b['id']])['n'];
    ?>
    <tr>
      <td>
        <strong><a href="<?= h(blogUrl($b)) ?>" target="_blank"><?= h($b['name']) ?></a></strong>
        <div class="text-muted">/<?= h($b['slug']) ?></div>
      </td>
      <td><?= h(ucfirst($b['theme'])) ?></td>
      <td><a href="/admin/blogs/<?= $b['id'] ?>/posts"><?= $postCount ?></a></td>
      <td><?= $b['is_public'] ? '<span class="badge badge-published">Public</span>' : '<span class="badge badge-draft">Private</span>' ?></td>
      <td><?= formatDate($b['created_at'], 'M j, Y') ?></td>
      <td class="actions">
        <a href="/admin/blogs/<?= $b['id'] ?>/posts" class="btn btn-sm">Posts</a>
        <a href="/admin/blogs/<?= $b['id'] ?>/posts/new" class="btn btn-sm btn-primary">+ Post</a>
        <a href="/admin/blogs/<?= $b['id'] ?>/edit" class="btn btn-sm">Settings</a>
        <form method="post" style="display:inline" onsubmit="return confirm('Delete this blog and all its posts?')">
          <?= csrfField() ?>
          <input type="hidden" name="_action" value="delete">
          <input type="hidden" name="id" value="<?= $b['id'] ?>">
          <button type="submit" class="btn btn-sm btn-danger">Delete</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<?php });
