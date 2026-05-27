<?php
require_once BASE_PATH . '/admin/layout.php';
Auth::requireLogin();

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'delete') {
    Auth::verifyCsrf();
    $id = (int)($_POST['id'] ?? 0);
    $m  = Database::fetch("SELECT * FROM media WHERE id = ?", [$id]);
    if ($m && ($m['user_id'] === Auth::id() || Auth::isSuperAdmin())) {
        Media::delete($id);
    }
    redirect('/admin/media');
}

// Handle upload
$uploadError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    Auth::verifyCsrf();
    try {
        Media::upload($_FILES['file'], Auth::id());
        flash('success', 'Image uploaded.');
        redirect('/admin/media');
    } catch (\Exception $e) {
        $uploadError = $e->getMessage();
    }
}

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 24;
$userId  = Auth::id();

$total  = Auth::isSuperAdmin()
    ? (int)Database::fetch("SELECT COUNT(*) as n FROM media")['n']
    : (int)Database::fetch("SELECT COUNT(*) as n FROM media WHERE user_id = ?", [$userId])['n'];
$media = Auth::isSuperAdmin()
    ? Database::fetchAll("SELECT * FROM media ORDER BY created_at DESC LIMIT $perPage OFFSET " . (($page-1)*$perPage))
    : Database::fetchAll("SELECT * FROM media WHERE user_id = ? ORDER BY created_at DESC LIMIT $perPage OFFSET " . (($page-1)*$perPage), [$userId]);
$pager = pagination($total, $page, $perPage, '/admin/media');

adminLayout('Media Library', function() use ($media, $pager, $uploadError, $perPage) { ?>
<div class="page-header">
  <h1>Media Library</h1>
</div>

<div class="card" style="margin-bottom:1.5rem;max-width:480px">
  <form method="post" enctype="multipart/form-data" class="upload-form">
    <?= csrfField() ?>
    <div class="form-group">
      <label>Upload Image</label>
      <input type="file" name="file" accept="image/*" required>
    </div>
    <?php if ($uploadError): ?><div class="alert alert-error"><?= h($uploadError) ?></div><?php endif; ?>
    <button type="submit" class="btn btn-primary">Upload</button>
  </form>
</div>

<div class="media-grid">
  <?php foreach ($media as $m): ?>
  <div class="media-item">
    <div class="media-thumb">
      <img src="<?= h(Media::url($m, true)) ?>" alt="<?= h($m['alt_text'] ?? $m['original_name']) ?>" loading="lazy">
    </div>
    <div class="media-info">
      <div class="media-name" title="<?= h($m['original_name']) ?>"><?= h(truncate($m['original_name'], 24)) ?></div>
      <div class="media-actions">
        <a href="<?= h(Media::url($m)) ?>" target="_blank" class="btn btn-sm">View</a>
        <button type="button" class="btn btn-sm" onclick="copyUrl('<?= h(Media::url($m)) ?>')">Copy URL</button>
        <form method="post" style="display:inline" onsubmit="return confirm('Delete this image?')">
          <?= csrfField() ?>
          <input type="hidden" name="_action" value="delete">
          <input type="hidden" name="id" value="<?= $m['id'] ?>">
          <button type="submit" class="btn btn-sm btn-danger">Delete</button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php if (!$media): ?>
<div class="empty-state"><p>No images uploaded yet.</p></div>
<?php endif; ?>

<?= $pager ?>
<script>
function copyUrl(url) {
  navigator.clipboard.writeText(url).then(() => alert('URL copied!')).catch(() => prompt('Copy this URL:', url));
}
</script>
<?php });
