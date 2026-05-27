<?php
require_once BASE_PATH . '/admin/layout.php';
Auth::requireLogin();
Auth::requireSuperAdmin();

// ── POST: reassign blog owner ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action = $_POST['_action'] ?? '';

    if ($action === 'reassign_blog') {
        $blogId    = (int)($_POST['blog_id'] ?? 0);
        $newUserId = (int)($_POST['new_user_id'] ?? 0);
        $blog      = $blogId    ? Database::fetch("SELECT name FROM blogs WHERE id = ?", [$blogId]) : null;
        $newUser   = $newUserId ? Database::fetch("SELECT display_name, username FROM users WHERE id = ?", [$newUserId]) : null;

        if ($blog && $newUser) {
            Database::update('blogs', ['user_id' => $newUserId], 'id = ?', [$blogId]);
            $name = $newUser['display_name'] ?: $newUser['username'];
            flash('success', '"' . $blog['name'] . '" reassigned to ' . $name . '.');
        } else {
            flash('error', 'Invalid blog or user.');
        }
        redirect('/admin/superadmin');
    }
}

// ── Stats ────────────────────────────────────────────────────────────────────
$totalUsers    = (int)Database::fetch("SELECT COUNT(*) as n FROM users")['n'];
$totalBlogs    = (int)Database::fetch("SELECT COUNT(*) as n FROM blogs")['n'];
$totalPosts    = (int)Database::fetch("SELECT COUNT(*) as n FROM posts WHERE status = 'published'")['n'];
$totalDrafts   = (int)Database::fetch("SELECT COUNT(*) as n FROM posts WHERE status = 'draft'")['n'];
$totalViews    = (int)(Database::fetch("SELECT COUNT(*) as n FROM post_views")['n'] ?? 0);
$apBlogs       = (int)Database::fetch("SELECT COUNT(*) as n FROM blogs WHERE ap_enabled = 1")['n'];
$totalFollowers= (int)(Database::fetch("SELECT COUNT(*) as n FROM blog_followers")['n'] ?? 0);

$queuePending  = (int)(Database::fetch("SELECT COUNT(*) as n FROM federation_queue WHERE attempts < 5")['n'] ?? 0);
$queueFailed   = (int)(Database::fetch("SELECT COUNT(*) as n FROM federation_queue WHERE attempts >= 5")['n'] ?? 0);

// ── Data ─────────────────────────────────────────────────────────────────────
$allUsers  = Database::fetchAll("SELECT id, username, display_name FROM users ORDER BY display_name, username");
$allBlogs  = Database::fetchAll(
    "SELECT b.*, u.username, u.display_name as owner_name
     FROM blogs b JOIN users u ON b.user_id = u.id
     ORDER BY b.created_at DESC"
);
$recentPosts = Database::fetchAll(
    "SELECT p.title, p.status, p.created_at, b.name as blog_name
     FROM posts p JOIN blogs b ON p.blog_id = b.id
     ORDER BY p.created_at DESC LIMIT 10"
);

// ── Migrations ───────────────────────────────────────────────────────────────
$allMigrations     = Updater::allMigrations();
$appliedMigrations = Updater::appliedMigrations();

adminLayout('Platform Overview', function() use (
    $totalUsers, $totalBlogs, $totalPosts, $totalDrafts, $totalViews,
    $apBlogs, $totalFollowers, $queuePending, $queueFailed,
    $allUsers, $allBlogs, $recentPosts,
    $allMigrations, $appliedMigrations
) { ?>
<div class="page-header">
  <h1>Platform Overview</h1>
  <a href="/admin/users" class="btn">Manage Users</a>
</div>

<!-- ── Platform stats ── -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr)">
  <div class="stat-card"><div class="stat-number"><?= $totalUsers ?></div><div class="stat-label">Users</div></div>
  <div class="stat-card"><div class="stat-number"><?= $totalBlogs ?></div><div class="stat-label">Blogs</div></div>
  <div class="stat-card"><div class="stat-number"><?= $totalPosts ?></div><div class="stat-label">Published Posts</div></div>
  <div class="stat-card"><div class="stat-number"><?= $totalDrafts ?></div><div class="stat-label">Drafts</div></div>
  <div class="stat-card"><div class="stat-number"><?= number_format($totalViews) ?></div><div class="stat-label">Total Views</div></div>
</div>

<!-- ── All blogs with reassign ── -->
<div class="card" style="margin-top:1.5rem">
  <div class="card-header"><h2>All Blogs</h2></div>
  <table class="data-table">
    <thead>
      <tr><th>Blog</th><th>Owner</th><th>Theme</th><th>AP</th><th>Created</th><th>Reassign Owner</th></tr>
    </thead>
    <tbody>
    <?php foreach ($allBlogs as $b): ?>
    <tr>
      <td>
        <a href="<?= h(blogUrl($b)) ?>" target="_blank"><?= h($b['name']) ?></a>
        <div class="text-muted"><code><?= h($b['slug']) ?></code></div>
      </td>
      <td><?= h($b['owner_name'] ?: $b['username']) ?></td>
      <td><?= h($b['theme']) ?></td>
      <td><?= $b['ap_enabled'] ? '<span class="badge badge-published">On</span>' : '<span class="badge badge-draft">Off</span>' ?></td>
      <td><?= formatDate($b['created_at'], 'M j, Y') ?></td>
      <td>
        <form method="post" style="display:flex;gap:.4rem;align-items:center">
          <?= csrfField() ?>
          <input type="hidden" name="_action" value="reassign_blog">
          <input type="hidden" name="blog_id" value="<?= $b['id'] ?>">
          <select name="new_user_id" style="font-size:.8rem;padding:.25rem .4rem">
            <?php foreach ($allUsers as $u): ?>
            <option value="<?= $u['id'] ?>" <?= $u['id'] == $b['user_id'] ? 'selected' : '' ?>>
              <?= h($u['display_name'] ?: $u['username']) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-sm"
                  onclick="return confirm('Reassign this blog?')">Move</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- ── Recent posts + Federation queue ── -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;margin-top:1.5rem">

<div class="card">
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

<div>
  <!-- Federation queue -->
  <div class="card" style="margin-bottom:1.25rem">
    <div class="card-header"><h2>Federation</h2></div>
    <table class="data-table">
      <tr><td>AP-enabled blogs</td><td><strong><?= $apBlogs ?></strong></td></tr>
      <tr><td>Total followers</td><td><strong><?= number_format($totalFollowers) ?></strong></td></tr>
      <tr><td>Queue pending</td><td>
        <?php if ($queuePending > 0): ?>
          <span class="badge badge-draft"><?= $queuePending ?></span>
        <?php else: ?>
          <span class="badge badge-published">0</span>
        <?php endif; ?>
      </td></tr>
      <tr><td>Queue failed</td><td>
        <?php if ($queueFailed > 0): ?>
          <span class="badge badge-danger"><?= $queueFailed ?></span>
        <?php else: ?>
          <span class="badge badge-published">0</span>
        <?php endif; ?>
      </td></tr>
    </table>
  </div>

  <!-- Migration status -->
  <div class="card">
    <div class="card-header"><h2>Migrations</h2></div>
    <table class="data-table">
      <?php foreach ($allMigrations as $m): ?>
      <?php $applied = in_array($m['version'], $appliedMigrations); ?>
      <tr>
        <td style="font-size:.8rem;font-family:monospace"><?= h($m['version']) ?></td>
        <td>
          <?php if ($applied): ?>
            <span class="badge badge-published">Applied</span>
          <?php else: ?>
            <span class="badge badge-danger">Pending</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

</div>
<?php });
