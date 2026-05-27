<?php
function adminLayout(string $pageTitle, callable $body): void {
    $user      = Auth::user();
    $isSuperAdmin = Auth::isSuperAdmin();
    $platformName = setting('platform_name', 'OCCI Blogs');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle) ?> &mdash; <?= h($platformName) ?> Admin</title>
<link rel="stylesheet" href="/public/assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <a href="/admin/" class="site-name"><?= h($platformName) ?></a>
      <span class="site-subtitle">Admin Panel</span>
    </div>
    <nav class="sidebar-nav">
      <a href="/admin/" class="nav-item <?= isCurrentPage(adminUrl()) ? 'active' : '' ?>">
        <span class="nav-icon">&#x2302;</span> Dashboard
      </a>
      <a href="/admin/blogs" class="nav-item <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/admin/blogs') ? 'active' : '' ?>">
        <span class="nav-icon">&#x270D;</span> My Blogs
      </a>
      <a href="/admin/media" class="nav-item <?= isCurrentPage(adminUrl('media')) ? 'active' : '' ?>">
        <span class="nav-icon">&#x1F4F7;</span> Media
      </a>
      <a href="/admin/settings" class="nav-item <?= isCurrentPage(adminUrl('settings')) ? 'active' : '' ?>">
        <span class="nav-icon">&#x2699;</span> Profile
      </a>
      <?php if ($isSuperAdmin): ?>
      <div class="nav-divider">Platform Admin</div>
      <a href="/admin/users" class="nav-item <?= isCurrentPage(adminUrl('users')) ? 'active' : '' ?>">
        <span class="nav-icon">&#x1F465;</span> Users
      </a>
      <a href="/admin/superadmin" class="nav-item <?= isCurrentPage(adminUrl('superadmin')) ? 'active' : '' ?>">
        <span class="nav-icon">&#x1F4CA;</span> Platform Stats
      </a>
      <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
      <span class="user-name"><?= h($user['display_name'] ?: $user['username']) ?></span>
      <a href="/admin/logout" class="logout-link">Log out</a>
    </div>
  </aside>

  <!-- Main content -->
  <main class="admin-main">
    <?php
    $successMsg = flash('success');
    $errorMsg   = flash('error');
    if ($successMsg): ?>
    <div class="alert alert-success"><?= h($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
    <div class="alert alert-error"><?= h($errorMsg) ?></div>
    <?php endif; ?>
    <?php $body(); ?>
  </main>
</div>
<script src="/public/assets/js/admin.js"></script>
</body>
</html>
<?php
}
