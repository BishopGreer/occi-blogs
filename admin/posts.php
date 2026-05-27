<?php
require_once BASE_PATH . '/admin/layout.php';
Auth::requireLogin();

$blogId = (int)($_GET['blog_id'] ?? 0);
if (!$blogId) redirect('/admin/blogs');
Auth::requireBlogAccess($blogId);

$blog = Database::fetch("SELECT * FROM blogs WHERE id = ?", [$blogId]);
if (!$blog) { http_response_code(404); die('Blog not found.'); }

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'delete') {
    Auth::verifyCsrf();
    $postId = (int)($_POST['id'] ?? 0);
    Database::delete('posts', 'id = ? AND blog_id = ?', [$postId, $blogId]);
    flash('success', 'Post deleted.');
    redirect("/admin/blogs/{$blogId}/posts");
}

$filter  = $_GET['status'] ?? 'all';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where  = 'blog_id = ?';
$params = [$blogId];
if (in_array($filter, ['draft', 'published', 'scheduled'])) {
    $where   .= ' AND status = ?';
    $params[] = $filter;
}

$total = (int)Database::fetch("SELECT COUNT(*) as n FROM posts WHERE $where", $params)['n'];
$posts = Database::fetchAll("SELECT * FROM posts WHERE $where ORDER BY created_at DESC LIMIT $perPage OFFSET " . (($page-1)*$perPage), $params);
$pager = pagination($total, $page, $perPage, "/admin/blogs/{$blogId}/posts?" . ($filter !== 'all' ? "status={$filter}&" : ''));

adminLayout('Posts: ' . $blog['name'], function() use ($blog, $blogId, $posts, $pager, $filter, $total) { ?>
<div class="page-header">
  <div>
    <h1><?= h($blog['name']) ?></h1>
    <div class="breadcrumb"><a href="/admin/blogs">Blogs</a> / Posts</div>
  </div>
  <a href="/admin/blogs/<?= $blogId ?>/posts/new" class="btn btn-primary">+ New Post</a>
</div>

<div class="filter-tabs">
  <a href="/admin/blogs/<?= $blogId ?>/posts" class="tab <?= $filter === 'all' ? 'active' : '' ?>">All</a>
  <a href="/admin/blogs/<?= $blogId ?>/posts?status=published" class="tab <?= $filter === 'published' ? 'active' : '' ?>">Published</a>
  <a href="/admin/blogs/<?= $blogId ?>/posts?status=draft" class="tab <?= $filter === 'draft' ? 'active' : '' ?>">Drafts</a>
</div>

<?php if (!$posts): ?>
<div class="empty-state">
  <p>No posts yet. Write your first one!</p>
  <a href="/admin/blogs/<?= $blogId ?>/posts/new" class="btn btn-primary">+ New Post</a>
</div>
<?php else: ?>
<div class="card">
  <table class="data-table">
    <thead><tr><th>Title</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($posts as $p): ?>
    <tr>
      <td>
        <a href="/admin/blogs/<?= $blogId ?>/posts/<?= $p['id'] ?>/edit"><?= h($p['title']) ?></a>
        <?php if ($p['status'] === 'published'): ?>
        <a href="<?= h(blogUrl($blog, $p['slug'])) ?>" target="_blank" class="view-link">&#x2197;</a>
        <?php endif; ?>
      </td>
      <td><span class="badge badge-<?= $p['status'] ?>"><?= h($p['status']) ?></span></td>
      <td><?= formatDate($p['status'] === 'published' ? ($p['published_at'] ?? $p['created_at']) : $p['created_at'], 'M j, Y') ?></td>
      <td class="actions">
        <a href="/admin/blogs/<?= $blogId ?>/posts/<?= $p['id'] ?>/edit" class="btn btn-sm">Edit</a>
        <form method="post" style="display:inline" onsubmit="return confirm('Delete this post?')">
          <?= csrfField() ?>
          <input type="hidden" name="_action" value="delete">
          <input type="hidden" name="id" value="<?= $p['id'] ?>">
          <button type="submit" class="btn btn-sm btn-danger">Delete</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?= $pager ?>
<?php endif; ?>
<?php });
