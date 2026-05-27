<?php
require_once BASE_PATH . '/admin/layout.php';
Auth::requireLogin();

$user   = Auth::user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    $displayName = trim($_POST['display_name'] ?? '');
    $bio         = trim($_POST['bio'] ?? '');
    $newPass     = $_POST['new_password'] ?? '';
    $newPass2    = $_POST['new_password2'] ?? '';

    if ($newPass && strlen($newPass) < 8) $errors[] = 'New password must be at least 8 characters.';
    if ($newPass && $newPass !== $newPass2) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $data = ['display_name' => $displayName, 'bio' => $bio];
        if ($newPass) {
            $old = trim($_POST['current_password'] ?? '');
            if (!password_verify($old, $user['password'])) {
                $errors[] = 'Current password is incorrect.';
            } else {
                $data['password'] = Auth::hashPassword($newPass);
            }
        }
        if (!$errors) {
            Database::update('users', $data, 'id = ?', [Auth::id()]);
            flash('success', 'Profile updated.');
            redirect('/admin/settings');
        }
    }
}

adminLayout('Profile Settings', function() use ($user, $errors) { ?>
<div class="page-header"><h1>Profile Settings</h1></div>

<?php if ($errors): ?>
<div class="alert alert-error">
  <?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card" style="max-width:600px">
  <form method="post" class="edit-form">
    <?= csrfField() ?>
    <div class="form-group">
      <label>Username</label>
      <input type="text" value="<?= h($user['username']) ?>" disabled class="input-disabled">
    </div>
    <div class="form-group">
      <label>Email</label>
      <input type="text" value="<?= h($user['email']) ?>" disabled class="input-disabled">
    </div>
    <div class="form-group">
      <label for="display_name">Display Name</label>
      <input type="text" id="display_name" name="display_name" value="<?= h($user['display_name'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label for="bio">Bio <span class="optional">(optional)</span></label>
      <textarea id="bio" name="bio" rows="3"><?= h($user['bio'] ?? '') ?></textarea>
    </div>
    <hr style="margin:1.5rem 0">
    <h3 style="margin-bottom:1rem">Change Password</h3>
    <div class="form-group">
      <label for="current_password">Current Password</label>
      <input type="password" id="current_password" name="current_password" autocomplete="current-password">
    </div>
    <div class="form-group">
      <label for="new_password">New Password</label>
      <input type="password" id="new_password" name="new_password" autocomplete="new-password">
    </div>
    <div class="form-group">
      <label for="new_password2">Confirm New Password</label>
      <input type="password" id="new_password2" name="new_password2" autocomplete="new-password">
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Save Changes</button>
    </div>
  </form>
</div>
<?php });
