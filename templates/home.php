<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(setting('platform_name', 'OCCI Blogs')) ?></title>
<meta name="description" content="<?= h(setting('platform_tagline', '')) ?>">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #fafafa; color: #222; line-height: 1.6; }
  .site-header { background: #4a2c6e; color: #fff; padding: 3rem 1.5rem; text-align: center; }
  .site-header h1 { font-size: 2.5rem; font-weight: 700; }
  .site-header p { font-size: 1.1rem; opacity: .8; margin-top: .5rem; }
  .container { max-width: 900px; margin: 0 auto; padding: 2.5rem 1.5rem; }
  .section-title { font-size: 1rem; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; color: #888; margin-bottom: 1.5rem; }
  .blog-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; }
  .blog-card { background: #fff; border-radius: 12px; padding: 1.75rem; text-decoration: none; color: inherit; box-shadow: 0 2px 8px rgba(0,0,0,.06); transition: transform .2s, box-shadow .2s; border: 1px solid #eee; display: block; }
  .blog-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,.1); }
  .blog-card h2 { font-size: 1.2rem; font-weight: 700; color: #1a1a2e; margin-bottom: .4rem; }
  .blog-card .blog-tagline { color: #555; font-size: .9rem; margin-bottom: .75rem; }
  .blog-card .blog-meta { font-size: .8rem; color: #999; }
  .empty { text-align: center; padding: 4rem 1rem; color: #999; }
  .site-footer { text-align: center; padding: 2rem; color: #aaa; font-size: .85rem; border-top: 1px solid #eee; }
  .header-nav { margin-top: 1.5rem; }
  .header-nav a { color: rgba(255,255,255,.85); text-decoration: none; margin: 0 .75rem; font-size: .9rem; }
  .header-nav a:hover { color: #fff; }
</style>
</head>
<body>
<header class="site-header">
  <h1><?= h(setting('platform_name', 'OCCI Blogs')) ?></h1>
  <?php $tagline = setting('platform_tagline', ''); if ($tagline): ?>
  <p><?= h($tagline) ?></p>
  <?php endif; ?>
  <nav class="header-nav">
    <a href="/admin/login">Sign In</a>
  </nav>
</header>

<div class="container">
  <?php
  $blogs = Database::fetchAll(
      "SELECT b.*, u.display_name, u.username,
              (SELECT COUNT(*) FROM posts WHERE blog_id = b.id AND status = 'published') as post_count
       FROM blogs b JOIN users u ON b.user_id = u.id
       WHERE b.is_public = 1
       ORDER BY b.created_at DESC"
  );
  ?>
  <?php if ($blogs): ?>
  <div class="section-title">All Blogs</div>
  <div class="blog-grid">
    <?php foreach ($blogs as $b): ?>
    <a href="<?= h(blogUrl($b)) ?>" class="blog-card">
      <h2><?= h($b['name']) ?></h2>
      <?php if ($b['tagline']): ?>
      <div class="blog-tagline"><?= h($b['tagline']) ?></div>
      <?php elseif ($b['description']): ?>
      <div class="blog-tagline"><?= h(truncate($b['description'], 80)) ?></div>
      <?php endif; ?>
      <div class="blog-meta">
        by <?= h($b['display_name'] ?: $b['username']) ?>
        &middot; <?= $b['post_count'] ?> post<?= $b['post_count'] !== 1 ? 's' : '' ?>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="empty">
    <p>No blogs yet. <a href="/admin/login">Sign in</a> to create one.</p>
  </div>
  <?php endif; ?>
</div>

<footer class="site-footer">
  &copy; <?= date('Y') ?> <?= h(setting('platform_name', 'OCCI Blogs')) ?> &mdash; Powered by OCCI Blogs
</footer>
</body>
</html>
