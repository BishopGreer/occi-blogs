<?php
require_once BASE_PATH . '/admin/layout.php';
Auth::requireLogin();

$user   = Auth::user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action = $_POST['_action'] ?? 'profile';

    // ── Profile update (all users) ───────────────────────────
    if ($action === 'profile') {
        $displayName = trim($_POST['display_name'] ?? '');
        $bio         = trim($_POST['bio'] ?? '');
        $newPass     = $_POST['new_password'] ?? '';
        $newPass2    = $_POST['new_password2'] ?? '';

        if ($newPass && strlen($newPass) < 8)  $errors[] = 'New password must be at least 8 characters.';
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

    // ── Platform settings (superadmin only) ─────────────────
    if ($action === 'platform' && Auth::isSuperAdmin()) {
        $settings = [
            'platform_name'     => trim($_POST['platform_name'] ?? ''),
            'platform_tagline'  => trim($_POST['platform_tagline'] ?? ''),
            'admin_email'       => strtolower(trim($_POST['admin_email'] ?? '')),
            'analytics_enabled' => isset($_POST['analytics_enabled']) ? '1' : '0',
        ];

        if (!$settings['platform_name']) $errors[] = 'Platform name is required.';

        if (!$errors) {
            foreach ($settings as $key => $value) {
                if (Database::fetch("SELECT 1 FROM settings WHERE `key` = ?", [$key])) {
                    Database::update('settings', ['value' => $value], '`key` = ?', [$key]);
                } else {
                    Database::insert('settings', ['key' => $key, 'value' => $value]);
                }
            }
            flash('success', 'Platform settings saved.');
            redirect('/admin/settings');
        }
    }
}

// Reload user after possible update
$user = Auth::user();

adminLayout('Settings', function() use ($user, $errors) { ?>
<div class="page-header"><h1>Settings</h1></div>

<?php if ($errors): ?>
<div class="alert alert-error">
  <?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Profile ── -->
<div class="card" style="max-width:600px;margin-bottom:1.5rem">
  <div class="card-header"><h2>Your Profile</h2></div>
  <form method="post" class="edit-form">
    <?= csrfField() ?>
    <input type="hidden" name="_action" value="profile">
    <div class="form-group">
      <label>Username</label>
      <input type="text" value="<?= h($user['username']) ?>" disabled class="input-disabled">
    </div>
    <div class="form-group">
      <label>Email</label>
      <input type="text" value="<?= h($user['email']) ?>" disabled class="input-disabled">
      <small>Contact a superadmin to change your email.</small>
    </div>
    <div class="form-group">
      <label for="display_name">Display Name</label>
      <input type="text" id="display_name" name="display_name"
             value="<?= h($user['display_name'] ?? '') ?>">
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
      <input type="password" id="new_password" name="new_password" minlength="8" autocomplete="new-password">
    </div>
    <div class="form-group">
      <label for="new_password2">Confirm New Password</label>
      <input type="password" id="new_password2" name="new_password2" autocomplete="new-password">
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Save Profile</button>
    </div>
  </form>
</div>

<!-- ── Platform settings (superadmin only) ── -->
<?php if (Auth::isSuperAdmin()): ?>
<div class="card" style="max-width:600px">
  <div class="card-header">
    <h2>Platform Settings</h2>
    <small style="color:#888">Visible to superadmins only</small>
  </div>
  <form method="post" class="edit-form">
    <?= csrfField() ?>
    <input type="hidden" name="_action" value="platform">
    <div class="form-group">
      <label for="platform_name">Platform Name</label>
      <input type="text" id="platform_name" name="platform_name"
             value="<?= h(setting('platform_name', 'OCCI Blogs')) ?>" required>
      <small>Shown in the admin sidebar and on the home page.</small>
    </div>
    <div class="form-group">
      <label for="platform_tagline">Platform Tagline</label>
      <input type="text" id="platform_tagline" name="platform_tagline"
             value="<?= h(setting('platform_tagline', '')) ?>"
             placeholder="Independent Catholic voices">
    </div>
    <div class="form-group">
      <label for="admin_email">Admin Email</label>
      <input type="email" id="admin_email" name="admin_email"
             value="<?= h(setting('admin_email', '')) ?>"
             placeholder="admin@myocci.net">
      <small>Used for system notifications (future).</small>
    </div>
    <div class="form-group form-check">
      <label>
        <input type="checkbox" name="analytics_enabled" value="1"
               <?= setting('analytics_enabled', '1') === '1' ? 'checked' : '' ?>>
        Enable page view analytics
      </label>
      <small>When off, no view data is recorded for any blog.</small>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Save Platform Settings</button>
    </div>
  </form>
</div>
<?php endif; ?>
<?php });
