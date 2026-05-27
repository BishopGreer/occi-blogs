<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tag: <?= h($tag['name']) ?> &mdash; <?= h($blog['name']) ?></title>
<link rel="stylesheet" href="<?= h(themeAsset($blog, 'css/style.css')) ?>">
<?php if ($blog['custom_css']): ?><style><?= $blog['custom_css'] ?></style><?php endif; ?>
</head>
<body>
<header class="site-header">
  <div class="header-inner">
    <div class="blog-title"><a href="<?= h($blogUrl) ?>"><?= h($blog['name']) ?></a></div>
  </div>
</header>
<div class="layout">
  <main class="main">
    <h2 style="font-family:-apple-system,sans-serif;margin-bottom:1.5rem;color:#2c4a3e">Tag: <?= h($tag['name']) ?></h2>
    <?php if (!$posts): ?>
    <p style="color:#888">No posts with this tag.</p>
    <?php else: ?>
    <?php foreach ($posts as $p): ?>
    <div class="post-list-item">
      <div class="post-meta"><?= formatDate($p['published_at'] ?? $p['created_at']) ?></div>
      <h2 class="post-list-title"><a href="<?= h(blogUrl($blog, $p['slug'])) ?>"><?= h($p['title']) ?></a></h2>
      <?php $ex = $p['excerpt'] ?: excerpt($p['content'] ?? '', 40); if ($ex): ?><p class="post-excerpt"><?= h($ex) ?></p><?php endif; ?>
      <a href="<?= h(blogUrl($blog, $p['slug'])) ?>" class="read-more">Read more &rarr;</a>
    </div>
    <?php endforeach; ?>
    <?= $pagination ?>
    <?php endif; ?>
  </main>
  <aside class="sidebar">
    <div class="sidebar-widget">
      <h3>Subscribe</h3>
      <div class="sidebar-nav"><a href="<?= h($feedUrl) ?>">RSS Feed</a></div>
    </div>
  </aside>
</div>
<footer class="site-footer"><a href="<?= h($blogUrl) ?>"><?= h($blog['name']) ?></a></footer>
</body>
</html>
