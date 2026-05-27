<?php
require_once BASE_PATH . '/admin/layout.php';
Auth::requireLogin();

$userId = Auth::id();
$isSuperAdmin = Auth::isSuperAdmin();

// Fetch blogs
$blogs = $isSuperAdmin
    ? Database::fetchAll("SELECT b.*, u.username FROM blogs b JOIN users u ON b.user_id = u.id ORDER BY b.created_at DESC LIMIT 10")
    : Database::fetchAll("SELECT * FROM blogs WHERE user_id = ? ORDER BY created_at DESC", [$userId]);

// Recent posts
$recentPosts = $isSuperAdmin
    ? Database::fetchAll("SELECT p.*, b.name as blog_name, b.slug as blog_slug FROM posts p JOIN blogs b ON p.blog_id = b.id ORDER BY p.created_at DESC LIMIT 8")
    : Database::fetchAll("SELECT p.*, b.name as blog_name, b.slug as blog_slug FROM posts p JOIN blogs b ON p.blog_id = b.id WHERE b.user_id = ? ORDER BY p.created_at DESC LIMIT 8", [$userId]);

$totalBlogs = $isSuperAdmin
    ? (int)Database::fetch("SELECT COUNT(*) as n FROM blogs")['n']
    : count($blogs);
$totalPosts = $isSuperAdmin
    ? (int)Database::fetch("SELECT COUNT(*) as n FROM posts")['n']
    : (int)Database::fetch("SELECT COUNT(*) as n FROM posts p JOIN blogs b ON p.blog_id = b.id WHERE b.user_id = ?", [$userId])['n'];

adminLayout('Dashboard', function() use ($blogs, $recentPosts, $totalBlogs, $totalPosts) { ?>
<div class="page-header">
  <h1>Dashboard</h1>
  <a href="/admin/blogs/new" class="btn btn-primary">+ New Blog</a>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-number"><?= $totalBlogs ?></div>
    <div class="stat-label">Blogs</div>
  </div>
  <div class="stat-card">
    <div class="stat-number"><?= $totalPosts ?></div>
    <div class="stat-label">Total Posts</div>
  </div>
</div>

<?php if ($blogs): ?>
<div class="card">
  <div class="card-header">
    <h2>Your Blogs</h2>
    <a href="/admin/blogs" class="btn btn-sm">View all</a>
  </div>
  <table class="data-table">
    <thead><tr><th>Blog</th><th>Theme</th><th>Posts</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($blogs as $b):
        $postCount = (int)Database::fetch("SELECT COUNT(*) as n FROM posts WHERE blog_id = ?", [$b['id']])['n'];
    ?>
    <tr>
      <td>
        <a href="<?= h(blogUrl($b)) ?>" target="_blank" class="blog-link"><?= h($b['name']) ?></a>
        <span class="blog-slug">/<?= h($b['slug']) ?></span>
      </td>
      <td><?= h(ucfirst($b['theme'])) ?></td>
      <td><?= $postCount ?></td>
      <td class="actions">
        <a href="/admin/blogs/<?= $b['id'] ?>/posts" class="btn btn-sm">Posts</a>
        <a href="/admin/blogs/<?= $b['id'] ?>/posts/new" class="btn btn-sm btn-primary">+ Post</a>
        <a href="/admin/blogs/<?= $b['id'] ?>/edit" class="btn btn-sm">Settings</a>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php if ($recentPosts): ?>
<div class="card" style="margin-top:1.5rem">
  <div class="card-header"><h2>Recent Posts</h2></div>
  <table class="data-table">
    <thead><tr><th>Title</th><th>Blog</th><th>Status</th><th>Date</th></tr></thead>
    <tbody>
    <?php foreach ($recentPosts as $p): ?>
    <tr>
      <td><a href="/admin/blogs/<?= Database::fetch("SELECT id FROM blogs WHERE slug = ?", [$p['blog_slug']])['id'] ?>/posts/<?= $p['id'] ?>/edit"><?= h($p['title']) ?></a></td>
      <td><?= h($p['blog_name']) ?></td>
      <td><span class="badge badge-<?= $p['status'] ?>"><?= h($p['status']) ?></span></td>
      <td><?= formatDate($p['created_at'], 'M j, Y') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<?php });
