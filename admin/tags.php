<?php
require_once BASE_PATH . '/admin/layout.php';
Auth::requireLogin();

// Require a blog context
$blogId = (int)($_GET['blog_id'] ?? 0);
if (!$blogId) {
    // Show all blogs to pick from
    $blogs = Auth::isSuperAdmin()
        ? Database::fetchAll("SELECT * FROM blogs ORDER BY name")
        : Database::fetchAll("SELECT * FROM blogs WHERE user_id = ? ORDER BY name", [Auth::id()]);
    adminLayout('Tags', function() use ($blogs) { ?>
    <div class="page-header"><h1>Tags</h1></div>
    <div class="card"><p style="padding:1rem">Select a blog to manage its tags:</p>
    <ul style="padding:0 1rem 1rem">
    <?php foreach ($blogs as $b): ?>
      <li style="margin:.5rem 0"><a href="/admin/tags?blog_id=<?= $b['id'] ?>" class="btn btn-sm"><?= h($b['name']) ?></a></li>
    <?php endforeach; ?>
    </ul></div>
    <?php });
    return;
}
Auth::requireBlogAccess($blogId);
$blog = Database::fetch("SELECT * FROM blogs WHERE id = ?", [$blogId]);

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'delete') {
    Auth::verifyCsrf();
    $tid = (int)($_POST['id'] ?? 0);
    Database::delete('tags', 'id = ? AND blog_id = ?', [$tid, $blogId]);
    flash('success', 'Tag deleted.');
    redirect("/admin/tags?blog_id={$blogId}");
}

$tags = Database::fetchAll(
    "SELECT t.*, COUNT(pt.post_id) as post_count FROM tags t LEFT JOIN post_tags pt ON t.id = pt.tag_id WHERE t.blog_id = ? GROUP BY t.id ORDER BY t.name",
    [$blogId]
);

adminLayout('Tags: ' . ($blog['name'] ?? ''), function() use ($tags, $blogId, $blog) { ?>
<div class="page-header">
  <div>
    <h1>Tags</h1>
    <div class="breadcrumb"><a href="/admin/blogs">Blogs</a> / <?= h($blog['name'] ?? '') ?></div>
  </div>
</div>

<?php if (!$tags): ?>
<div class="empty-state"><p>No tags yet. Add tags when editing posts.</p></div>
<?php else: ?>
<div class="card">
  <table class="data-table">
    <thead><tr><th>Tag</th><th>Slug</th><th>Posts</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($tags as $t): ?>
    <tr>
      <td><?= h($t['name']) ?></td>
      <td><?= h($t['slug']) ?></td>
      <td><?= $t['post_count'] ?></td>
      <td>
        <form method="post" style="display:inline" onsubmit="return confirm('Delete this tag?')">
          <?= csrfField() ?>
          <input type="hidden" name="_action" value="delete">
          <input type="hidden" name="id" value="<?= $t['id'] ?>">
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
