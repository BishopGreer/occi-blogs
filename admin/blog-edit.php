<?php
require_once BASE_PATH . '/admin/layout.php';
Auth::requireLogin();

$id      = (int)($_GET['id'] ?? 0);
$isNew   = $id === 0;
$blog    = $isNew ? [] : Database::fetch("SELECT * FROM blogs WHERE id = ?", [$id]);
$themes  = availableThemes();
$errors  = [];

if ($blog && !$isNew) Auth::requireBlogAccess($id);
if (!$isNew && !$blog) { http_response_code(404); die('Blog not found.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    $name        = trim($_POST['name'] ?? '');
    $slug        = slugify(trim($_POST['slug'] ?? $name));
    $description = trim($_POST['description'] ?? '');
    $tagline     = trim($_POST['tagline'] ?? '');
    $theme       = $_POST['theme'] ?? 'minimal';
    $is_public   = isset($_POST['is_public']) ? 1 : 0;
    $custom_css  = trim($_POST['custom_css'] ?? '');

    if (!$name) $errors[] = 'Blog name is required.';
    if (!$slug) $errors[] = 'Slug is required.';
    if (!array_key_exists($theme, $themes)) $theme = 'minimal';

    // Check slug uniqueness
    $existing = Database::fetch("SELECT id FROM blogs WHERE slug = ? AND id != ?", [$slug, $id]);
    if ($existing) $errors[] = 'That slug is already taken. Choose another.';

    if (!$errors) {
        if ($isNew) {
            $newId = Database::insert('blogs', [
                'user_id'     => Auth::id(),
                'slug'        => $slug,
                'name'        => $name,
                'description' => $description,
                'tagline'     => $tagline,
                'theme'       => $theme,
                'is_public'   => $is_public,
                'custom_css'  => $custom_css ?: null,
            ]);
            flash('success', 'Blog created! Start writing your first post.');
            redirect("/admin/blogs/{$newId}/posts/new");
        } else {
            Database::update('blogs', [
                'name'        => $name,
                'slug'        => $slug,
                'description' => $description,
                'tagline'     => $tagline,
                'theme'       => $theme,
                'is_public'   => $is_public,
                'custom_css'  => $custom_css ?: null,
            ], 'id = ?', [$id]);
            flash('success', 'Blog settings saved.');
            redirect("/admin/blogs/{$id}/edit");
        }
    }

    // Repopulate on error
    $blog = compact('name', 'slug', 'description', 'tagline', 'theme', 'is_public');
}

$pageTitle = $isNew ? 'New Blog' : 'Edit: ' . ($blog['name'] ?? '');

adminLayout($pageTitle, function() use ($blog, $isNew, $id, $themes, $errors) { ?>
<div class="page-header">
  <h1><?= $isNew ? 'Create New Blog' : 'Blog Settings' ?></h1>
  <?php if (!$isNew): ?>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap">
    <a href="/admin/blogs/<?= $id ?>/posts" class="btn">View Posts</a>
    <a href="/admin/blogs/<?= $id ?>/federation" class="btn">&#x1F300; Fediverse</a>
    <a href="/admin/blogs/<?= $id ?>/export" class="btn">&#x2B07; Export</a>
    <a href="/admin/blogs/<?= $id ?>/import" class="btn">&#x2B06; Import</a>
  </div>
  <?php endif; ?>
</div>

<?php if ($errors): ?>
<div class="alert alert-error">
  <?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
  <form method="post" class="edit-form">
    <?= csrfField() ?>
    <div class="form-group">
      <label for="name">Blog Name *</label>
      <input type="text" id="name" name="name" value="<?= h($blog['name'] ?? '') ?>" required
             oninput="if(document.getElementById('slug').dataset.auto!='0') document.getElementById('slug').value = slugify(this.value)">
    </div>
    <div class="form-group">
      <label for="slug">URL Slug *
        <small>blogs.myocci.net/<strong id="slug-preview"><?= h($blog['slug'] ?? '') ?></strong></small>
      </label>
      <input type="text" id="slug" name="slug" value="<?= h($blog['slug'] ?? '') ?>" required
             data-auto="1" oninput="this.dataset.auto='0'; document.getElementById('slug-preview').textContent=this.value">
    </div>
    <div class="form-group">
      <label for="tagline">Tagline <span class="optional">(optional)</span></label>
      <input type="text" id="tagline" name="tagline" value="<?= h($blog['tagline'] ?? '') ?>" placeholder="A short description shown under your blog name">
    </div>
    <div class="form-group">
      <label for="description">Description <span class="optional">(optional)</span></label>
      <textarea id="description" name="description" rows="3" placeholder="Describe what your blog is about..."><?= h($blog['description'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
      <label>Theme</label>
      <div class="theme-grid">
        <?php foreach ($themes as $key => $meta): ?>
        <label class="theme-option <?= ($blog['theme'] ?? 'minimal') === $key ? 'selected' : '' ?>">
          <input type="radio" name="theme" value="<?= h($key) ?>" <?= ($blog['theme'] ?? 'minimal') === $key ? 'checked' : '' ?>>
          <div class="theme-name"><?= h($meta['name']) ?></div>
          <div class="theme-desc"><?= h($meta['description']) ?></div>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="form-group form-check">
      <label>
        <input type="checkbox" name="is_public" value="1" <?= ($blog['is_public'] ?? 1) ? 'checked' : '' ?>>
        Make this blog publicly visible
      </label>
    </div>
    <div class="form-group">
      <label for="custom_css">Custom CSS <span class="optional">(optional)</span></label>
      <textarea id="custom_css" name="custom_css" rows="6"
                placeholder="/* Override theme styles for this blog only */&#10;.post-title { color: navy; }&#10;.site-header { background: #f0f0f0; }"
                style="font-family:monospace;font-size:.85rem"><?= h($blog['custom_css'] ?? '') ?></textarea>
      <small>Applied to every page of this blog on top of the chosen theme.</small>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary"><?= $isNew ? 'Create Blog' : 'Save Settings' ?></button>
      <a href="/admin/blogs" class="btn">Cancel</a>
    </div>
  </form>
</div>
<script>
function slugify(str) {
  return str.toLowerCase().trim().replace(/[^\w\s-]/g,'').replace(/[\s_-]+/g,'-').replace(/^-+|-+$/g,'');
}
document.querySelectorAll('.theme-option input').forEach(r => {
  r.addEventListener('change', () => {
    document.querySelectorAll('.theme-option').forEach(o => o.classList.remove('selected'));
    r.closest('.theme-option').classList.add('selected');
  });
});
</script>
<?php });
