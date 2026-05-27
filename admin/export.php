<?php
require_once BASE_PATH . '/admin/layout.php';
Auth::requireLogin();

$blogId = (int)($_GET['blog_id'] ?? 0);
if (!$blogId) redirect('/admin/blogs');
Auth::requireBlogAccess($blogId);

$blog = Database::fetch("SELECT * FROM blogs WHERE id = ?", [$blogId]);
if (!$blog) { http_response_code(404); die('Blog not found.'); }

// ── Trigger download ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    if (!class_exists('ZipArchive')) {
        flash('error', 'ZIP export requires the PHP zip extension, which is not enabled on this server.');
        redirect("/admin/blogs/{$blogId}/export");
    }

    $posts = Database::fetchAll(
        "SELECT p.*, GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ') as tag_list
         FROM posts p
         LEFT JOIN post_tags pt ON pt.post_id = p.id
         LEFT JOIN tags t ON t.id = pt.tag_id
         WHERE p.blog_id = ?
         GROUP BY p.id
         ORDER BY p.published_at DESC, p.created_at DESC",
        [$blogId]
    );

    if (!$posts) {
        flash('error', 'This blog has no posts to export.');
        redirect("/admin/blogs/{$blogId}/export");
    }

    $zip      = new ZipArchive();
    $tmpFile  = tempnam(sys_get_temp_dir(), 'occi_export_');
    $zipName  = $blog['slug'] . '-export-' . date('Y-m-d') . '.zip';

    if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        flash('error', 'Could not create ZIP file.');
        redirect("/admin/blogs/{$blogId}/export");
    }

    foreach ($posts as $p) {
        $date     = date('Y-m-d', strtotime($p['published_at'] ?? $p['created_at']));
        $filename = $date . '-' . $p['slug'] . '.md';

        // YAML frontmatter
        $tags = $p['tag_list'] ?? '';
        $fm   = "---\n";
        $fm  .= 'title: "' . str_replace('"', '\\"', $p['title']) . "\"\n";
        $fm  .= 'slug: ' . $p['slug'] . "\n";
        $fm  .= 'date: ' . $date . "\n";
        $fm  .= 'status: ' . $p['status'] . "\n";
        if ($tags) $fm .= 'tags: [' . $tags . "]\n";
        if ($p['excerpt']) $fm .= 'excerpt: "' . str_replace('"', '\\"', $p['excerpt']) . "\"\n";
        if ($p['cover_image']) $fm .= 'cover_image: ' . $p['cover_image'] . "\n";
        $fm  .= "---\n\n";

        $zip->addFromString($filename, $fm . ($p['content'] ?? ''));
    }

    // Include a README
    $readme  = "# {$blog['name']} — Blog Export\n\n";
    $readme .= 'Exported: ' . date('Y-m-d H:i:s') . "\n";
    $readme .= 'Posts: ' . count($posts) . "\n\n";
    $readme .= "Each `.md` file contains YAML frontmatter followed by the post content (HTML).\n";
    $zip->addFromString('README.md', $readme);

    $zip->close();

    // Stream to browser
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipName . '"');
    header('Content-Length: ' . filesize($tmpFile));
    header('Pragma: no-cache');
    readfile($tmpFile);
    unlink($tmpFile);
    exit;
}

// ── Page ─────────────────────────────────────────────────────────────────────
$postCount = (int)Database::fetch("SELECT COUNT(*) as n FROM posts WHERE blog_id = ?", [$blogId])['n'];

adminLayout('Export: ' . $blog['name'], function() use ($blog, $blogId, $postCount) { ?>
<div class="page-header">
  <div>
    <h1>Export Posts</h1>
    <div class="breadcrumb">
      <a href="/admin/blogs">Blogs</a> /
      <a href="/admin/blogs/<?= $blogId ?>/posts"><?= h($blog['name']) ?></a> /
      Export
    </div>
  </div>
</div>

<div class="card" style="max-width:560px">
  <div class="card-header"><h2>Markdown ZIP Export</h2></div>
  <p style="color:#555;margin-bottom:1.25rem">
    Downloads all <strong><?= number_format($postCount) ?></strong> post<?= $postCount !== 1 ? 's' : '' ?>
    as a <code>.zip</code> archive. Each post is a <code>.md</code> file with
    YAML frontmatter (title, date, tags, status) followed by the post content.
    Compatible with Hugo, Jekyll, and most static site generators.
  </p>

  <?php if (!class_exists('ZipArchive')): ?>
  <div class="alert alert-error">
    The PHP <code>zip</code> extension is not installed on this server. Ask your host to enable <code>php-zip</code>.
  </div>
  <?php elseif ($postCount === 0): ?>
  <p style="color:#888">No posts to export yet.</p>
  <?php else: ?>
  <form method="post">
    <?= csrfField() ?>
    <button type="submit" class="btn btn-primary">&#x2B07; Download ZIP (<?= $postCount ?> post<?= $postCount !== 1 ? 's' : '' ?>)</button>
  </form>
  <?php endif; ?>
</div>
<?php });
