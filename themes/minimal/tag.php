<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Posts tagged "<?= h($tag['name']) ?>" &mdash; <?= h($blog['name']) ?></title>
<link rel="stylesheet" href="<?= h(themeAsset($blog, 'css/style.css')) ?>">
<?php if ($blog['custom_css']): ?><style><?= $blog['custom_css'] ?></style><?php endif; ?>
</head>
<body>
<header class="site-header">
  <div class="header-inner">
    <div class="blog-title"><a href="<?= h($blogUrl) ?>"><?= h($blog['name']) ?></a></div>
    <nav class="header-links"><a href="<?= h($blogUrl) ?>">All Posts</a></nav>
  </div>
</header>

<div class="container">
  <h2 style="font-family:-apple-system,sans-serif;margin-bottom:1.5rem;color:#4a2c6e">Tag: <?= h($tag['name']) ?></h2>
  <?php if (!$posts): ?>
  <p style="color:#888">No posts with this tag.</p>
  <?php else: ?>
  <ul class="post-list">
    <?php foreach ($posts as $p): ?>
    <li class="post-list-item">
      <div class="post-meta"><?= formatDate($p['published_at'] ?? $p['created_at']) ?></div>
      <h2 class="post-list-title"><a href="<?= h(blogUrl($blog, $p['slug'])) ?>"><?= h($p['title']) ?></a></h2>
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
<footer class="site-footer"><a href="<?= h($blogUrl) ?>"><?= h($blog['name']) ?></a></footer>
</body>
</html>
