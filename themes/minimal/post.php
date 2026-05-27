<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($post['title']) ?> &mdash; <?= h($blog['name']) ?></title>
<meta name="description" content="<?= h($post['excerpt'] ?: excerpt($post['content'] ?? '', 30)) ?>">
<link rel="canonical" href="<?= h(blogUrl($blog, $post['slug'])) ?>">
<link rel="stylesheet" href="<?= h(themeAsset($blog, 'css/style.css')) ?>">
<link rel="alternate" type="application/rss+xml" title="<?= h($blog['name']) ?>" href="<?= h($feedUrl) ?>">
<!-- Open Graph -->
<meta property="og:title" content="<?= h($post['title']) ?>">
<meta property="og:description" content="<?= h($post['excerpt'] ?: excerpt($post['content'] ?? '', 30)) ?>">
<meta property="og:url" content="<?= h(blogUrl($blog, $post['slug'])) ?>">
<meta property="og:type" content="article">
<?php if ($post['cover_image']): ?><meta property="og:image" content="<?= h($post['cover_image']) ?>"><?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<?php if ($blog['custom_css']): ?><style><?= $blog['custom_css'] ?></style><?php endif; ?>
</head>
<body>
<header class="site-header">
  <div class="header-inner">
    <div class="blog-title"><a href="<?= h($blogUrl) ?>"><?= h($blog['name']) ?></a></div>
    <nav class="header-links">
      <a href="<?= h($blogUrl) ?>">All Posts</a>
      <a href="<?= h($feedUrl) ?>">RSS</a>
    </nav>
  </div>
</header>

<div class="container">
  <article>
    <header class="post-header">
      <div class="post-meta">
        <span><?= formatDate($post['published_at'] ?? $post['created_at']) ?></span>
      </div>
      <h1 class="post-title"><?= h($post['title']) ?></h1>
    </header>

    <?php if ($post['cover_image']): ?>
    <img src="<?= h($post['cover_image']) ?>" alt="<?= h($post['title']) ?>" class="cover-image">
    <?php endif; ?>

    <div class="post-content">
      <?= $post['content'] ?>
    </div>

    <?php if ($tags): ?>
    <div class="post-tags">
      <?php foreach ($tags as $t): ?>
      <a href="<?= h(blogUrl($blog, 'tag/' . $t['slug'])) ?>" class="tag-link"><?= h($t['name']) ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </article>

  <!-- Social share -->
  <div style="margin-top:2.5rem;padding-top:1.5rem;border-top:1px solid #eee;font-family:-apple-system,sans-serif;font-size:.85rem;color:#888">
    Share:
    <a href="https://twitter.com/intent/tweet?url=<?= urlencode(blogUrl($blog, $post['slug'])) ?>&text=<?= urlencode($post['title']) ?>" target="_blank" rel="noopener" style="color:#7c4dbd;margin-left:.75rem">Twitter / X</a>
    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode(blogUrl($blog, $post['slug'])) ?>" target="_blank" rel="noopener" style="color:#7c4dbd;margin-left:.75rem">Facebook</a>
    <a href="#" onclick="shareMastodon('<?= addslashes(blogUrl($blog, $post['slug'])) ?>','<?= addslashes(h($post['title'])) ?>');return false" style="color:#7c4dbd;margin-left:.75rem">Mastodon</a>
    <a href="#" onclick="navigator.clipboard.writeText(window.location.href);this.textContent='Copied!';return false" style="color:#7c4dbd;margin-left:.75rem">Copy link</a>
  </div>
<script>
function shareMastodon(url, title) {
  var instance = prompt('Your Mastodon instance (e.g. mastodon.social):');
  if (instance) {
    instance = instance.replace(/^https?:\/\//i, '').replace(/\/+$/, '');
    window.open('https://' + instance + '/share?text=' + encodeURIComponent(title + ' ' + url), '_blank');
  }
}
</script>

  <p style="margin-top:2rem;font-family:-apple-system,sans-serif;font-size:.9rem">
    <a href="<?= h($blogUrl) ?>" style="color:#7c4dbd">&larr; Back to <?= h($blog['name']) ?></a>
  </p>
</div>

<footer class="site-footer">
  <a href="<?= h($blogUrl) ?>"><?= h($blog['name']) ?></a>
  &middot; <a href="<?= h($feedUrl) ?>">RSS Feed</a>
  &middot; <a href="/">OCCI Blogs</a>
</footer>
</body>
</html>
