<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($blog['name']) ?></title>
<link rel="stylesheet" href="<?= h(themeAsset($blog, 'css/style.css')) ?>">
<link rel="alternate" type="application/rss+xml" title="<?= h($blog['name']) ?>" href="<?= h($feedUrl) ?>">
</head>
<body>
<header class="site-header">
  <div class="header-inner">
    <div class="blog-title"><a href="<?= h($blogUrl) ?>"><?= h($blog['name']) ?></a></div>
    <?php if ($blog['tagline']): ?><div class="blog-tagline-text"><?= h($blog['tagline']) ?></div><?php endif; ?>
  </div>
</header>

<div class="layout">
  <main class="main">
    <?php if (!$posts): ?>
    <p style="color:#888;text-align:center;padding:2rem 0">No posts yet.</p>
    <?php else: ?>
    <?php foreach ($posts as $p): ?>
    <div class="post-list-item">
      <div class="post-meta"><?= formatDate($p['published_at'] ?? $p['created_at']) ?></div>
      <h2 class="post-list-title"><a href="<?= h(blogUrl($blog, $p['slug'])) ?>"><?= h($p['title']) ?></a></h2>
      <?php $ex = $p['excerpt'] ?: excerpt($p['content'] ?? '', 40); if ($ex): ?>
      <p class="post-excerpt"><?= h($ex) ?></p>
      <?php endif; ?>
      <a href="<?= h(blogUrl($blog, $p['slug'])) ?>" class="read-more">Read more &rarr;</a>
    </div>
    <?php endforeach; ?>
    <?= $pagination ?>
    <?php endif; ?>
  </main>

  <aside class="sidebar">
    <div class="sidebar-widget">
      <h3>About</h3>
      <p><?= h($blog['description'] ?: $blog['tagline'] ?: $blog['name']) ?></p>
    </div>
    <?php
    $blogTags = Database::fetchAll("SELECT t.*, COUNT(pt.post_id) as n FROM tags t LEFT JOIN post_tags pt ON t.id = pt.tag_id WHERE t.blog_id = ? GROUP BY t.id ORDER BY n DESC LIMIT 20", [$blog['id']]);
    if ($blogTags): ?>
    <div class="sidebar-widget">
      <h3>Tags</h3>
      <div class="tag-cloud">
        <?php foreach ($blogTags as $t): ?>
        <a href="<?= h(blogUrl($blog, 'tag/' . $t['slug'])) ?>"><?= h($t['name']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <div class="sidebar-widget">
      <h3>Subscribe</h3>
      <div class="sidebar-nav">
        <a href="<?= h($feedUrl) ?>">RSS Feed</a>
        <a href="<?= h(blogUrl($blog, 'feed/atom')) ?>">Atom Feed</a>
      </div>
    </div>
    <div class="sidebar-widget" style="text-align:center">
      <a href="/" style="font-size:.8rem;color:#aaa;text-decoration:none">OCCI Blogs</a>
    </div>
  </aside>
</div>

<footer class="site-footer">
  <a href="<?= h($blogUrl) ?>"><?= h($blog['name']) ?></a>
  &middot; <a href="<?= h($feedUrl) ?>">RSS</a>
  &middot; <a href="/">OCCI Blogs</a>
</footer>
</body>
</html>
