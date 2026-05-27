<?php
require_once BASE_PATH . '/admin/layout.php';
Auth::requireLogin();

$blogId = (int)($_GET['blog_id'] ?? 0);
if (!$blogId) redirect('/admin/blogs');
Auth::requireBlogAccess($blogId);

$blog = Database::fetch("SELECT * FROM blogs WHERE id = ?", [$blogId]);
if (!$blog) { http_response_code(404); die('Blog not found.'); }

$results = null;
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    $publishImported = isset($_POST['publish']) ? 'published' : 'draft';

    // ── Read uploaded file or pasted JSON ────────────────────
    $json = '';
    if (!empty($_FILES['import_file']['tmp_name'])) {
        $json = file_get_contents($_FILES['import_file']['tmp_name']);
    } elseif (!empty($_POST['import_json'])) {
        $json = trim($_POST['import_json']);
    }

    if (!$json) {
        $errors[] = 'Please upload a file or paste JSON.';
    } else {
        $data = json_decode($json, true);
        if (!$data) {
            $errors[] = 'Could not parse JSON. Make sure the file is a valid export.';
        } else {
            $posts = self_detectFormat($data);
            if ($posts === null) {
                $errors[] = 'Unrecognised export format. Supported: WriteFreely JSON, Ghost JSON.';
            } else {
                $imported = 0;
                $skipped  = 0;

                foreach ($posts as $raw) {
                    $title   = trim($raw['title'] ?? 'Untitled');
                    $content = $raw['html'] ?? $raw['body'] ?? $raw['content'] ?? '';
                    $excerpt = trim($raw['custom_excerpt'] ?? $raw['excerpt'] ?? '');
                    $slug    = slugify($raw['slug'] ?? $title);
                    $rawDate = $raw['published_at'] ?? $raw['created'] ?? $raw['created_at'] ?? null;
                    $date    = $rawDate ? date('Y-m-d H:i:s', strtotime($rawDate)) : date('Y-m-d H:i:s');

                    // Skip if blank content
                    if (!$title && !$content) { $skipped++; continue; }

                    // Make slug unique within this blog
                    $baseSlug = $slug ?: slugify($title);
                    $slug     = $baseSlug;
                    $i = 1;
                    while (Database::fetch("SELECT id FROM posts WHERE blog_id = ? AND slug = ?", [$blogId, $slug])) {
                        $slug = $baseSlug . '-' . $i++;
                    }

                    Database::insert('posts', [
                        'blog_id'      => $blogId,
                        'user_id'      => Auth::id(),
                        'title'        => $title,
                        'slug'         => $slug,
                        'content'      => $content,
                        'excerpt'      => $excerpt,
                        'status'       => $publishImported,
                        'published_at' => $publishImported === 'published' ? $date : null,
                    ]);
                    $imported++;
                }

                $results = ['imported' => $imported, 'skipped' => $skipped, 'status' => $publishImported];
            }
        }
    }
}

/** Detect export format and return normalised posts array, or null on failure */
function self_detectFormat(array $data): ?array
{
    // WriteFreely: {"posts": [...]}
    if (isset($data['posts']) && is_array($data['posts'])) {
        return $data['posts'];
    }
    // Ghost: {"db": [{"data": {"posts": [...]}}]}
    if (isset($data['db'][0]['data']['posts']) && is_array($data['db'][0]['data']['posts'])) {
        return $data['db'][0]['data']['posts'];
    }
    // Ghost (newer export): {"posts": {"meta": ..., "data": [...]}}
    if (isset($data['posts']['data']) && is_array($data['posts']['data'])) {
        return $data['posts']['data'];
    }
    return null;
}

adminLayout('Import: ' . $blog['name'], function() use ($blog, $blogId, $results, $errors) { ?>
<div class="page-header">
  <div>
    <h1>Import Posts</h1>
    <div class="breadcrumb">
      <a href="/admin/blogs">Blogs</a> /
      <a href="/admin/blogs/<?= $blogId ?>/posts"><?= h($blog['name']) ?></a> /
      Import
    </div>
  </div>
</div>

<?php if ($errors): ?>
<div class="alert alert-error">
  <?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($results): ?>
<div class="alert alert-success" style="margin-bottom:1.5rem">
  <strong>Import complete.</strong>
  <?= number_format($results['imported']) ?> post<?= $results['imported'] !== 1 ? 's' : '' ?> imported as
  <em><?= $results['status'] ?></em><?= $results['skipped'] ? ', ' . $results['skipped'] . ' skipped (empty)' : '' ?>.
  <a href="/admin/blogs/<?= $blogId ?>/posts" style="margin-left:.5rem">View posts &rarr;</a>
</div>
<?php endif; ?>

<div class="card" style="max-width:660px">
  <div class="card-header"><h2>Import from WriteFreely or Ghost</h2></div>

  <p style="color:#555;margin-bottom:1.25rem">
    Upload your export file or paste the JSON directly. Posts are imported into
    <strong><?= h($blog['name']) ?></strong>.
  </p>

  <div style="background:#f8f8f8;border-radius:6px;padding:1rem;margin-bottom:1.25rem;font-size:.9rem;color:#555">
    <strong>Supported formats:</strong><br>
    &bull; <strong>WriteFreely</strong> — export from <em>Settings &rarr; Export</em> (produces a <code>.json</code> file)<br>
    &bull; <strong>Ghost</strong> — export from <em>Settings &rarr; Labs &rarr; Export your content</em>
  </div>

  <form method="post" enctype="multipart/form-data" class="edit-form">
    <?= csrfField() ?>

    <div class="form-group">
      <label for="import_file">Upload JSON export file</label>
      <input type="file" id="import_file" name="import_file" accept=".json,application/json">
    </div>

    <div style="text-align:center;color:#aaa;margin:.5rem 0;font-size:.9rem">— or —</div>

    <div class="form-group">
      <label for="import_json">Paste JSON directly</label>
      <textarea id="import_json" name="import_json" rows="6"
                style="font-family:monospace;font-size:.8rem"
                placeholder='{"posts": [...]}'></textarea>
    </div>

    <div class="form-group form-check">
      <label>
        <input type="checkbox" name="publish" value="1">
        Publish imported posts immediately
        <span class="optional">(leave unchecked to import as drafts)</span>
      </label>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">&#x2B06; Import Posts</button>
      <a href="/admin/blogs/<?= $blogId ?>/posts" class="btn">Cancel</a>
    </div>
  </form>
</div>
<?php });
