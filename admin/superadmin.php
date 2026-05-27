<?php
require_once BASE_PATH . '/admin/layout.php';
Auth::requireLogin();
Auth::requireSuperAdmin();

$totalUsers  = (int)Database::fetch("SELECT COUNT(*) as n FROM users")['n'];
$totalBlogs  = (int)Database::fetch("SELECT COUNT(*) as n FROM blogs")['n'];
$totalPosts  = (int)Database::fetch("SELECT COUNT(*) as n FROM posts WHERE status = 'published'")['n'];
$totalDrafts = (int)Database::fetch("SELECT COUNT(*) as n FROM posts WHERE status = 'draft'")['n'];
$totalViews  = (int)(Database::fetch("SELECT COUNT(*) as n FROM post_views")['n'] ?? 0);

$recentBlogs = Database::fetchAll("SELECT b.*, u.username FROM blogs b JOIN users u ON b.user_id = u.id ORDER BY b.created_at DESC LIMIT 10");
$recentPosts = Database::fetchAll("SELECT p.title, p.status, p.created_at, b.name as blog_name FROM posts p JOIN blogs b ON p.blog_id = b.id ORDER BY p.created_at DESC LIMIT 10");

adminLayout('Platform Overview', function() use ($totalUsers, $totalBlogs, $totalPosts, $totalDrafts, $totalViews, $recentBlogs, $recentPosts) { ?>
<div class="page-header"><h1>Platform Overview</h1></div>

<div class="stats-grid" style="grid-template-columns: repeat(5, 1fr)">
  <div class="stat-card"><div class="stat-number"><?= $totalUsers ?></div><div class="stat-label">Users</div></div>
  <div class="stat-card"><div class="stat-number"><?= $totalBlogs ?></div><div class="stat-label">Blogs</div></div>
  <div class="stat-card"><div class="stat-number"><?= $totalPosts ?></div><div class="stat-label">Published Posts</div></div>
  <div class="stat-card"><div class="stat-number"><?= $totalDrafts ?></div><div class="stat-label">Drafts</div></div>
  <div class="stat-card"><div class="stat-number"><?= number_format($totalViews) ?></div><div class="stat-label">Total Views</div></div>
</div>

<div class="card" style="margin-top:1.5rem">
  <div class="card-header"><h2>Recent Blogs</h2><a href="/admin/users" class="btn btn-sm">Manage Users</a></div>
  <table class="data-table">
    <thead><tr><th>Blog</th><th>Owner</th><th>Theme</th><th>Created</th></tr></thead>
    <tbody>
    <?php foreach ($recentBlogs as $b): ?>
    <tr>
      <td><a href="<?= h(blogUrl($b)) ?>" target="_blank"><?= h($b['name']) ?></a></td>
      <td><?= h($b['username']) ?></td>
      <td><?= h($b['theme']) ?></td>
      <td><?= formatDate($b['created_at'], 'M j, Y') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="card" style="margin-top:1.5rem">
  <div class="card-header"><h2>Recent Posts</h2></div>
  <table class="data-table">
    <thead><tr><th>Title</th><th>Blog</th><th>Status</th><th>Date</th></tr></thead>
    <tbody>
    <?php foreach ($recentPosts as $p): ?>
    <tr>
      <td><?= h($p['title']) ?></td>
      <td><?= h($p['blog_name']) ?></td>
      <td><span class="badge badge-<?= $p['status'] ?>"><?= h($p['status']) ?></span></td>
      <td><?= formatDate($p['created_at'], 'M j, Y') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php });
