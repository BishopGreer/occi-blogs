<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($blog['name']) ?></title>
<meta name="description" content="<?= h($blog['description'] ?? $blog['tagline'] ?? '') ?>">
<link rel="stylesheet" href="<?= h(themeAsset($blog, 'css/style.css')) ?>">
<link rel="alternate" type="application/rss+xml" title="<?= h($blog['name']) ?>" href="<?= h($feedUrl) ?>">
<?php if ($blog['custom_css']): ?><style><?= $blog['custom_css'] ?></style><?php endif; ?>
</head>
<body>
<header class="site-header">
  <div class="header-inner">
    <div class="blog-title"><a href="<?= h($blogUrl) ?>"><?= h($blog['name']) ?></a></div>
    <nav class="header-links">
      <a href="<?= h($feedUrl) ?>">RSS</a>
    </nav>
  </div>
  <?php if ($blog['tagline']): ?>
  <div style="max-width:680px;margin:.5rem auto 0;padding:0 1.5rem;font-size:.9rem;color:#888;font-family:-apple-system,sans-serif"><?= h($blog['tagline']) ?></div>
  <?php endif; ?>
</header>

<div class="container">
  <?php if (!$posts): ?>
  <p style="color:#888;text-align:center;padding:3rem 0">No posts yet.</p>
  <?php else: ?>
  <ul class="post-list">
    <?php foreach ($posts as $p): ?>
    <li class="post-list-item">
      <div class="post-meta">
        <span><?= formatDate($p['published_at'] ?? $p['created_at']) ?></span>
      </div>
      <h2 class="post-list-title">
        <a href="<?= h(blogUrl($blog, $p['slug'])) ?>"><?= h($p['title']) ?></a>
      </h2>
      <?php $ex = $p['excerpt'] ?: excerpt($p['content'] ?? '', 40); if ($ex): ?>
      <p class="post-excerpt"><?= h($ex) ?></p>
      <?php endif; ?>
      <a href="<?= h(blogUrl($blog, $p['slug'])) ?>" class="read-more">Read more &rarr;</a>
    </li>
    <?php endforeach; ?>
  </ul>
  <?= $pagination ?>
  <?php endif; ?>
</div>

<footer class="site-footer">
  <a href="<?= h($blogUrl) ?>"><?= h($blog['name']) ?></a>
  &middot; <a href="<?= h($feedUrl) ?>">RSS Feed</a>
  &middot; <a href="/">OCCI Blogs</a>
</footer>
</body>
</html>
