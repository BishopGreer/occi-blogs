<?php
require_once BASE_PATH . '/admin/layout.php';
Auth::requireLogin();

$blogId = (int)($_GET['blog_id'] ?? 0);
$postId = (int)($_GET['id'] ?? 0);
$isNew  = $postId === 0;

if (!$blogId) redirect('/admin/blogs');
Auth::requireBlogAccess($blogId);

$blog = Database::fetch("SELECT * FROM blogs WHERE id = ?", [$blogId]);
if (!$blog) { http_response_code(404); die('Blog not found.'); }

$post   = $isNew ? [] : Database::fetch("SELECT * FROM posts WHERE id = ? AND blog_id = ?", [$postId, $blogId]);
$errors = [];

if (!$isNew && !$post) { http_response_code(404); die('Post not found.'); }

// Load existing tags for this post
$postTagNames = [];
if (!$isNew && $post) {
    $tagRows = Database::fetchAll(
        "SELECT t.name FROM tags t JOIN post_tags pt ON t.id = pt.tag_id WHERE pt.post_id = ?",
        [$postId]
    );
    $postTagNames = array_column($tagRows, 'name');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    $title      = trim($_POST['title'] ?? '');
    $slug       = slugify(trim($_POST['slug'] ?? $title));
    $content    = $_POST['content'] ?? '';
    $excerpt    = trim($_POST['excerpt'] ?? '');
    $cover      = trim($_POST['cover_image'] ?? '');
    $status     = in_array($_POST['status'] ?? '', ['draft','published','scheduled']) ? $_POST['status'] : 'draft';
    $tagsInput  = trim($_POST['tags'] ?? '');

    if (!$title)   $errors[] = 'Title is required.';
    if (!$slug)    $errors[] = 'Slug is required.';

    // Slug uniqueness within blog
    $existingSlug = Database::fetch("SELECT id FROM posts WHERE blog_id = ? AND slug = ? AND id != ?", [$blogId, $slug, $postId]);
    if ($existingSlug) $errors[] = 'A post with that slug already exists in this blog.';

    if (!$errors) {
        $publishedAt = null;
        if ($status === 'published') {
            $publishedAt = Database::fetch("SELECT published_at FROM posts WHERE id = ?", [$postId])['published_at'] ?? null;
            if (!$publishedAt) $publishedAt = date('Y-m-d H:i:s');
        }

        $data = compact('title', 'slug', 'content', 'excerpt', 'cover_image', 'status') + ['published_at' => $publishedAt, 'cover_image' => $cover];
        if ($isNew) {
            $data['blog_id'] = $blogId;
            $data['user_id'] = Auth::id();
            $postId = Database::insert('posts', $data);
        } else {
            Database::update('posts', $data, 'id = ? AND blog_id = ?', [$postId, $blogId]);
        }

        // Save tags
        Database::delete('post_tags', 'post_id = ?', [$postId]);
        if ($tagsInput) {
            $tagNames = array_unique(array_filter(array_map('trim', explode(',', $tagsInput))));
            foreach ($tagNames as $tagName) {
                if (!$tagName) continue;
                $tagSlug = slugify($tagName);
                $tag = Database::fetch("SELECT * FROM tags WHERE blog_id = ? AND slug = ?", [$blogId, $tagSlug]);
                if (!$tag) {
                    $tagId = Database::insert('tags', ['blog_id' => $blogId, 'name' => $tagName, 'slug' => $tagSlug]);
                } else {
                    $tagId = $tag['id'];
                }
                try { Database::insert('post_tags', ['post_id' => $postId, 'tag_id' => $tagId]); } catch (\Exception) {}
            }
        }

        flash('success', $isNew ? 'Post created!' : 'Post saved.');
        redirect("/admin/blogs/{$blogId}/posts/{$postId}/edit");
    }

    $post = compact('title', 'slug', 'content', 'excerpt', 'status') + ['cover_image' => $cover];
    $postTagNames = array_filter(array_map('trim', explode(',', $tagsInput)));
}

$pageTitle = $isNew ? 'New Post' : 'Edit: ' . ($post['title'] ?? '');

adminLayout($pageTitle, function() use ($blog, $blogId, $post, $postId, $isNew, $errors, $postTagNames) { ?>
<div class="page-header">
  <div>
    <h1><?= $isNew ? 'New Post' : 'Edit Post' ?></h1>
    <div class="breadcrumb"><a href="/admin/blogs">Blogs</a> / <a href="/admin/blogs/<?= $blogId ?>/posts"><?= h($blog['name']) ?></a> / <?= $isNew ? 'New' : h($post['title'] ?? '') ?></div>
  </div>
  <?php if (!$isNew && ($post['status'] ?? '') === 'published'): ?>
  <a href="<?= h(blogUrl($blog, $post['slug'])) ?>" target="_blank" class="btn">View Post &#x2197;</a>
  <?php endif; ?>
</div>

<?php if ($errors): ?>
<div class="alert alert-error">
  <?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<form method="post" id="post-form">
  <?= csrfField() ?>
  <div class="post-editor-layout">
    <!-- Main editor column -->
    <div class="editor-main">
      <div class="form-group">
        <input type="text" id="title" name="title" class="post-title-input" placeholder="Post title..."
               value="<?= h($post['title'] ?? '') ?>" required
               oninput="if(document.getElementById('slug').dataset.auto!='0') document.getElementById('slug').value = slugify(this.value)">
      </div>
      <div class="form-group">
        <textarea id="content" name="content"><?= h($post['content'] ?? '') ?></textarea>
      </div>
    </div>

    <!-- Sidebar -->
    <div class="editor-sidebar">
      <div class="sidebar-card">
        <h3>Publish</h3>
        <div class="form-group">
          <label>Status</label>
          <select name="status" id="post-status">
            <option value="draft" <?= ($post['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option>
            <option value="published" <?= ($post['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
          </select>
        </div>
        <div class="form-group">
          <label for="slug">URL Slug</label>
          <input type="text" id="slug" name="slug" value="<?= h($post['slug'] ?? '') ?>"
                 data-auto="<?= $isNew ? '1' : '0' ?>"
                 oninput="this.dataset.auto='0'" required>
          <small><?= h($blog['slug']) ?>/<span id="slug-preview"><?= h($post['slug'] ?? '') ?></span></small>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary btn-full">
            <?= $isNew ? 'Create Post' : 'Save Post' ?>
          </button>
          <a href="/admin/blogs/<?= $blogId ?>/posts" class="btn btn-full" style="margin-top:.5rem">Cancel</a>
        </div>
      </div>

      <div class="sidebar-card">
        <h3>Tags</h3>
        <div class="form-group">
          <input type="text" id="tags" name="tags" value="<?= h(implode(', ', $postTagNames)) ?>"
                 placeholder="faith, reflection, news">
          <small>Comma-separated</small>
        </div>
      </div>

      <div class="sidebar-card">
        <h3>Excerpt</h3>
        <div class="form-group">
          <textarea name="excerpt" rows="3" placeholder="Leave blank to auto-generate from content..."><?= h($post['excerpt'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="sidebar-card">
        <h3>Cover Image URL</h3>
        <div class="form-group">
          <input type="text" name="cover_image" value="<?= h($post['cover_image'] ?? '') ?>"
                 placeholder="https://... or /public/uploads/...">
          <small>Paste an image URL or upload via the editor toolbar</small>
        </div>
      </div>
    </div>
  </div>
</form>

<!-- TinyMCE -->
<script src="/public/tinymce/tinymce.min.js"></script>
<script>
tinymce.init({
  selector: '#content',
  license_key: 'gpl',
  height: 480,
  menubar: false,
  plugins: 'image link lists table code fullscreen wordcount autosave',
  toolbar: 'undo redo | bold italic underline | bullist numlist | link image table | blockquote code | fullscreen',
  content_style: 'body { font-family: Georgia, serif; font-size: 16px; line-height: 1.7; max-width: 720px; margin: 1rem auto; }',
  images_upload_url: '/api/media/upload?blog_id=<?= $blogId ?>',
  images_upload_credentials: true,
  automatic_uploads: true,
  file_picker_types: 'image',
  relative_urls: false,
  remove_script_host: false,
  init_instance_callback: function(editor) {
    editor.on('input keyup', function() {
      document.getElementById('content').value = editor.getContent();
    });
  }
});

function slugify(str) {
  return str.toLowerCase().trim().replace(/[^\w\s-]/g,'').replace(/[\s_-]+/g,'-').replace(/^-+|-+$/g,'');
}

document.getElementById('slug').addEventListener('input', function() {
  document.getElementById('slug-preview').textContent = this.value;
});

// Sync TinyMCE content before form submit
document.getElementById('post-form').addEventListener('submit', function() {
  if (typeof tinymce !== 'undefined') {
    tinymce.triggerSave();
  }
});
</script>
<?php });
