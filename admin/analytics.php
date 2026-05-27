<?php
require_once BASE_PATH . '/admin/layout.php';
Auth::requireLogin();

$blogId = (int)($_GET['blog_id'] ?? 0);
if (!$blogId) redirect('/admin/blogs');
Auth::requireBlogAccess($blogId);

$blog = Database::fetch("SELECT * FROM blogs WHERE id = ?", [$blogId]);
if (!$blog) { http_response_code(404); die('Blog not found.'); }

$days = (int)($_GET['days'] ?? 30);
if (!in_array($days, [7, 30, 90])) $days = 30;
$since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

$totalViews   = (int)Database::fetch("SELECT COUNT(*) as n FROM post_views WHERE blog_id = ? AND viewed_at >= ?", [$blogId, $since])['n'];
$uniqueVisitors = (int)Database::fetch("SELECT COUNT(DISTINCT ip_hash) as n FROM post_views WHERE blog_id = ? AND viewed_at >= ?", [$blogId, $since])['n'];

$topPosts = Database::fetchAll(
    "SELECT p.title, p.slug, COUNT(pv.id) as views FROM post_views pv JOIN posts p ON pv.post_id = p.id WHERE pv.blog_id = ? AND pv.viewed_at >= ? GROUP BY p.id ORDER BY views DESC LIMIT 10",
    [$blogId, $since]
);

$dailyViews = Database::fetchAll(
    "SELECT DATE(viewed_at) as day, COUNT(*) as views FROM post_views WHERE blog_id = ? AND viewed_at >= ? GROUP BY DATE(viewed_at) ORDER BY day ASC",
    [$blogId, $since]
);

$deviceBreakdown = Database::fetchAll(
    "SELECT device, COUNT(*) as count FROM post_views WHERE blog_id = ? AND viewed_at >= ? GROUP BY device ORDER BY count DESC",
    [$blogId, $since]
);

$topReferrers = Database::fetchAll(
    "SELECT referer, COUNT(*) as count FROM post_views WHERE blog_id = ? AND viewed_at >= ? AND referer != '' AND device != 'bot' GROUP BY referer ORDER BY count DESC LIMIT 10",
    [$blogId, $since]
);

adminLayout('Analytics: ' . $blog['name'], function() use ($blog, $blogId, $days, $totalViews, $uniqueVisitors, $topPosts, $dailyViews, $deviceBreakdown, $topReferrers) { ?>
<div class="page-header">
  <div>
    <h1>Analytics</h1>
    <div class="breadcrumb"><a href="/admin/blogs">Blogs</a> / <?= h($blog['name']) ?></div>
  </div>
  <div style="display:flex;gap:.5rem">
    <a href="?blog_id=<?= $blogId ?>&days=7" class="btn btn-sm <?= $days===7?'btn-primary':'' ?>">7 days</a>
    <a href="?blog_id=<?= $blogId ?>&days=30" class="btn btn-sm <?= $days===30?'btn-primary':'' ?>">30 days</a>
    <a href="?blog_id=<?= $blogId ?>&days=90" class="btn btn-sm <?= $days===90?'btn-primary':'' ?>">90 days</a>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>

<div class="stats-grid">
  <div class="stat-card"><div class="stat-number"><?= number_format($totalViews) ?></div><div class="stat-label">Page Views</div></div>
  <div class="stat-card"><div class="stat-number"><?= number_format($uniqueVisitors) ?></div><div class="stat-label">Unique Visitors</div></div>
</div>

<?php if ($dailyViews): ?>
<div class="card" style="margin-top:1.5rem">
  <div class="card-header"><h2>Views Over Time</h2></div>
  <canvas id="views-chart" height="100"></canvas>
</div>
<script>
const labels = <?= json_encode(array_column($dailyViews, 'day')) ?>;
const data   = <?= json_encode(array_map('intval', array_column($dailyViews, 'views'))) ?>;
new Chart(document.getElementById('views-chart'), {
  type: 'line',
  data: { labels, datasets: [{ label: 'Views', data, borderColor: '#7c4dbd', backgroundColor: 'rgba(124,77,189,.1)', tension: .3, fill: true }] },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});
</script>
<?php endif; ?>

<?php if ($topPosts): ?>
<div class="card" style="margin-top:1.5rem">
  <div class="card-header"><h2>Top Posts</h2></div>
  <table class="data-table">
    <thead><tr><th>Post</th><th>Views</th></tr></thead>
    <tbody>
    <?php foreach ($topPosts as $p): ?>
    <tr>
      <td><a href="<?= h(blogUrl($blog, $p['slug'])) ?>" target="_blank"><?= h($p['title']) ?></a></td>
      <td><?= number_format($p['views']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-top:1.5rem">

<?php if ($deviceBreakdown): ?>
<div class="card">
  <div class="card-header"><h2>Devices</h2></div>
  <canvas id="device-chart" height="220"></canvas>
  <script>
  (function(){
    var labels = <?= json_encode(array_column($deviceBreakdown, 'device')) ?>;
    var data   = <?= json_encode(array_map('intval', array_column($deviceBreakdown, 'count'))) ?>;
    var colors = { desktop:'#7c4dbd', mobile:'#e91e8c', tablet:'#00bcd4', bot:'#ccc' };
    new Chart(document.getElementById('device-chart'), {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [{ data: data, backgroundColor: labels.map(function(l){ return colors[l]||'#999'; }) }]
      },
      options: { plugins: { legend: { position: 'bottom' } } }
    });
  })();
  </script>
</div>
<?php endif; ?>

<?php if ($topReferrers): ?>
<div class="card">
  <div class="card-header"><h2>Top Referrers</h2></div>
  <table class="data-table">
    <thead><tr><th>Source</th><th>Visits</th></tr></thead>
    <tbody>
    <?php foreach ($topReferrers as $r): ?>
    <tr>
      <td style="word-break:break-all;font-size:.85rem"><?= h($r['referer']) ?></td>
      <td><?= number_format($r['count']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

</div>
<?php });
