<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($post['title']) ?> &mdash; <?= h($blog['name']) ?></title>
<meta name="description" content="<?= h($post['excerpt'] ?: excerpt($post['content'] ?? '', 30)) ?>">
<link rel="canonical" href="<?= h(blogUrl($blog, $post['slug'])) ?>">
<link rel="stylesheet" href="<?= h(themeAsset($blog, 'css/style.css')) ?>">
<meta property="og:title" content="<?= h($post['title']) ?>">
<meta property="og:description" content="<?= h($post['excerpt'] ?: excerpt($post['content'] ?? '', 30)) ?>">
<meta property="og:url" content="<?= h(blogUrl($blog, $post['slug'])) ?>">
<meta property="og:type" content="article">
<?php if ($post['cover_image']): ?><meta property="og:image" content="<?= h($post['cover_image']) ?>"><?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
</head>
<body>
<header class="site-header">
  <div class="header-inner">
    <div class="blog-title"><a href="<?= h($blogUrl) ?>"><?= h($blog['name']) ?></a></div>
  </div>
</header>

<div class="layout">
  <main class="main">
    <article>
      <div class="post-meta"><?= formatDate($post['published_at'] ?? $post['created_at']) ?></div>
      <h1 class="post-title"><?= h($post['title']) ?></h1>

      <?php if ($post['cover_image']): ?>
      <img src="<?= h($post['cover_image']) ?>" alt="<?= h($post['title']) ?>" class="cover-image">
      <?php endif; ?>

      <div class="post-content"><?= $post['content'] ?></div>

      <?php if ($tags): ?>
      <div class="post-tags">
        <?php foreach ($tags as $t): ?>
        <a href="<?= h(blogUrl($blog, 'tag/' . $t['slug'])) ?>" class="tag-link"><?= h($t['name']) ?></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </article>

    <div class="share-links">
      Share:
      <a href="https://twitter.com/intent/tweet?url=<?= urlencode(blogUrl($blog, $post['slug'])) ?>&text=<?= urlencode($post['title']) ?>" target="_blank" rel="noopener">Twitter / X</a>
      <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode(blogUrl($blog, $post['slug'])) ?>" target="_blank" rel="noopener">Facebook</a>
      <a href="#" onclick="navigator.clipboard.writeText(window.location.href);this.textContent='Copied!';return false">Copy link</a>
    </div>

    <p style="margin-top:1.5rem;font-family:-apple-system,sans-serif;font-size:.9rem">
      <a href="<?= h($blogUrl) ?>" style="color:#4a7c6f">&larr; Back to <?= h($blog['name']) ?></a>
    </p>
  </main>

  <aside class="sidebar">
    <div class="sidebar-widget">
      <h3>About</h3>
      <p><?= h($blog['description'] ?: $blog['tagline'] ?: '') ?></p>
    </div>
    <?php
    $blogTags = Database::fetchAll("SELECT * FROM tags WHERE blog_id = ? ORDER BY name LIMIT 20", [$blog['id']]);
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
      </div>
    </div>
  </aside>
</div>

<footer class="site-footer">
  <a href="<?= h($blogUrl) ?>"><?= h($blog['name']) ?></a> &middot; <a href="/">OCCI Blogs</a>
</footer>
</body>
</html>
