<?php
require_once BASE_PATH . '/admin/layout.php';

if (Auth::check()) redirect('/admin/');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Auth::attempt($_POST['email'] ?? '', $_POST['password'] ?? '', !empty($_POST['remember']))) {
        redirect('/admin/');
    }
    $error = 'Invalid email or password.';
}
$platformName = setting('platform_name', 'OCCI Blogs');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login &mdash; <?= h($platformName) ?></title>
<link rel="stylesheet" href="/public/assets/css/admin.css">
</head>
<body class="login-page">
<div class="login-box">
  <div class="login-logo">
    <h1><?= h($platformName) ?></h1>
    <p>Admin Login</p>
  </div>
  <?php if ($error): ?>
  <div class="alert alert-error"><?= h($error) ?></div>
  <?php endif; ?>
  <form method="post" class="login-form">
    <?= csrfField() ?>
    <div class="form-group">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required autofocus>
    </div>
    <div class="form-group">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>
    </div>
    <div class="form-group form-check">
      <label><input type="checkbox" name="remember"> Remember me</label>
    </div>
    <button type="submit" class="btn btn-primary btn-full">Sign In</button>
  </form>
  <p style="text-align:center;margin-top:1rem"><a href="/">View platform</a></p>
</div>
</body>
</html>
