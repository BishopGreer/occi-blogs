<?php
require_once BASE_PATH . '/admin/layout.php';
Auth::requireLogin();

$blogId = (int)($_GET['blog_id'] ?? 0);
if (!$blogId) redirect('/admin/blogs');
Auth::requireBlogAccess($blogId);

$blog = Database::fetch("SELECT * FROM blogs WHERE id = ?", [$blogId]);
if (!$blog) { http_response_code(404); die('Blog not found.'); }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action = $_POST['action'] ?? '';

    // Enable federation
    if ($action === 'enable') {
        if (!$blog['ap_public_key']) {
            // Generate keypair on first enable
            $keypair = HttpSignature::generateKeypair();
            Database::update('blogs', [
                'ap_enabled'     => 1,
                'ap_public_key'  => $keypair['public'],
                'ap_private_key' => $keypair['private'],
            ], 'id = ?', [$blogId]);
            flash('success', 'Fediverse federation enabled and keypair generated.');
        } else {
            Database::update('blogs', ['ap_enabled' => 1], 'id = ?', [$blogId]);
            flash('success', 'Fediverse federation enabled.');
        }
        redirect("/admin/blogs/{$blogId}/federation");
    }

    // Disable federation
    if ($action === 'disable') {
        Database::update('blogs', ['ap_enabled' => 0], 'id = ?', [$blogId]);
        flash('success', 'Fediverse federation disabled.');
        redirect("/admin/blogs/{$blogId}/federation");
    }

    // Regenerate keypair (only when disabled for safety)
    if ($action === 'regen_keys' && !$blog['ap_enabled']) {
        $keypair = HttpSignature::generateKeypair();
        Database::update('blogs', [
            'ap_public_key'  => $keypair['public'],
            'ap_private_key' => $keypair['private'],
        ], 'id = ?', [$blogId]);
        flash('success', 'New keypair generated. Re-enable federation when ready.');
        redirect("/admin/blogs/{$blogId}/federation");
    }
}

// Reload blog after possible update
$blog = Database::fetch("SELECT * FROM blogs WHERE id = ?", [$blogId]);

$followerCount = (int)Database::fetch(
    "SELECT COUNT(*) as n FROM blog_followers WHERE blog_id = ?",
    [$blogId]
)['n'];

$queueCount = (int)Database::fetch(
    "SELECT COUNT(*) as n FROM federation_queue WHERE blog_id = ? AND attempts < 5",
    [$blogId]
)['n'];

$recentFollowers = Database::fetchAll(
    "SELECT ra.username, ra.domain, bf.created_at
     FROM blog_followers bf JOIN remote_actors ra ON bf.remote_actor_id = ra.id
     WHERE bf.blog_id = ?
     ORDER BY bf.created_at DESC LIMIT 10",
    [$blogId]
);

$host     = parse_url(siteUrl(), PHP_URL_HOST);
$actorUrl = Federator::actorUrl($blog);
$handle   = '@' . $blog['slug'] . '@' . $host;

adminLayout('Fediverse: ' . $blog['name'], function() use ($blog, $blogId, $followerCount, $queueCount, $recentFollowers, $actorUrl, $handle, $errors) { ?>
<div class="page-header">
  <div>
    <h1>Fediverse Federation</h1>
    <div class="breadcrumb">
      <a href="/admin/blogs">Blogs</a> /
      <a href="/admin/blogs/<?= $blogId ?>/posts"><?= h($blog['name']) ?></a> /
      Fediverse
    </div>
  </div>
  <a href="/admin/blogs/<?= $blogId ?>/posts" class="btn">View Posts</a>
</div>

<!-- Status banner -->
<div class="card" style="margin-bottom:1.5rem">
  <div style="display:flex;align-items:center;gap:1.5rem;padding:.25rem 0">
    <div>
      <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:<?= $blog['ap_enabled'] ? '#22c55e' : '#d1d5db' ?>;margin-right:.5rem"></span>
      <strong><?= $blog['ap_enabled'] ? 'Federation active' : 'Federation disabled' ?></strong>
    </div>
    <?php if ($blog['ap_enabled']): ?>
    <div style="color:#666">
      Fediverse handle: <code><?= h($handle) ?></code>
    </div>
    <div style="color:#666">
      <strong><?= number_format($followerCount) ?></strong> follower<?= $followerCount !== 1 ? 's' : '' ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($blog['ap_enabled']): ?>
<!-- Actor info -->
<div class="card" style="margin-bottom:1.5rem">
  <div class="card-header"><h2>Actor Information</h2></div>
  <table class="data-table">
    <tr><td><strong>Handle</strong></td><td><code><?= h($handle) ?></code> — people on Mastodon/Misskey/etc. can follow this</td></tr>
    <tr><td><strong>Actor URL</strong></td><td><a href="<?= h($actorUrl) ?>" target="_blank"><?= h($actorUrl) ?></a></td></tr>
    <tr><td><strong>Inbox</strong></td><td><code><?= h($actorUrl) ?>/inbox</code></td></tr>
    <tr><td><strong>Outbox</strong></td><td><a href="<?= h($actorUrl) ?>/outbox" target="_blank"><?= h($actorUrl) ?>/outbox</a></td></tr>
  </table>
</div>

<!-- Queue status -->
<?php if ($queueCount > 0): ?>
<div class="alert alert-info" style="margin-bottom:1.5rem">
  <?= number_format($queueCount) ?> activit<?= $queueCount === 1 ? 'y' : 'ies' ?> pending delivery in queue. The cron job will process these shortly.
</div>
<?php endif; ?>

<!-- Recent followers -->
<?php if ($recentFollowers): ?>
<div class="card" style="margin-bottom:1.5rem">
  <div class="card-header"><h2>Recent Followers (<?= number_format($followerCount) ?> total)</h2></div>
  <table class="data-table">
    <thead><tr><th>Account</th><th>Instance</th><th>Followed</th></tr></thead>
    <tbody>
    <?php foreach ($recentFollowers as $f): ?>
    <tr>
      <td><?= $f['username'] ? '@' . h($f['username']) : '<em>unknown</em>' ?></td>
      <td><?= h($f['domain'] ?? '') ?></td>
      <td><?= formatDate($f['created_at']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php else: ?>
<div class="card" style="margin-bottom:1.5rem;text-align:center;padding:2rem;color:#888">
  No followers yet. Share your handle (<strong><?= h($handle) ?></strong>) so people can follow from Mastodon, Misskey, and other fediverse platforms.
</div>
<?php endif; ?>

<!-- Disable -->
<div class="card">
  <div class="card-header"><h2>Disable Federation</h2></div>
  <p style="color:#666;margin-bottom:1rem">Disabling stops new activities from being delivered to followers. Existing followers remain stored.</p>
  <form method="post">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="disable">
    <button type="submit" class="btn btn-danger" onclick="return confirm('Disable fediverse federation for this blog?')">Disable Federation</button>
  </form>
</div>

<?php else: ?>
<!-- Enable -->
<div class="card" style="margin-bottom:1.5rem">
  <div class="card-header"><h2>Enable Fediverse Federation</h2></div>
  <p style="color:#666;margin-bottom:1rem">
    When enabled, your blog becomes a followable actor on the fediverse. Anyone on Mastodon, Misskey, Pleroma, or any other ActivityPub platform can follow <strong><?= h($handle) ?></strong> and receive your posts in their feed.
  </p>
  <p style="color:#666;margin-bottom:1.5rem">
    An RSA-2048 keypair will be generated automatically on first enable.
  </p>
  <form method="post">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="enable">
    <button type="submit" class="btn btn-primary">Enable Federation</button>
  </form>
</div>

<?php if ($blog['ap_public_key']): ?>
<div class="card">
  <div class="card-header"><h2>Keypair</h2></div>
  <p style="color:#666;margin-bottom:1rem">A keypair exists for this blog. You can regenerate it while federation is disabled (any existing followers will need to re-follow).</p>
  <form method="post">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="regen_keys">
    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Regenerate keypair? Existing followers will need to re-follow.')">Regenerate Keypair</button>
  </form>
</div>
<?php endif; ?>

<?php endif; ?>
<?php });
