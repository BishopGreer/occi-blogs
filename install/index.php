<?php
/**
 * OCCI Blogs — Web-based Installer
 * 5 steps: Requirements → Database → Platform → Admin Account → Finish
 */

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/install/installer.php';

// Redirect if already installed
if (Installer::isAlreadyInstalled() && ($_GET['step'] ?? '') !== 'done') {
    header('Location: /admin/login');
    exit;
}

$step    = (int)($_GET['step'] ?? 1);
$errors  = [];
$success = '';

// -------------------------------------------------------
// Step processors
// -------------------------------------------------------

// STEP 2: Test DB connection
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim($_POST['db_host'] ?? 'localhost');
    $name = trim($_POST['db_name'] ?? '');
    $user = trim($_POST['db_user'] ?? '');
    $pass = $_POST['db_pass'] ?? '';
    $port = (int)($_POST['db_port'] ?? 3306);

    $result = Installer::testDbConnection($host, $name, $user, $pass, $port);
    if ($result['ok']) {
        // Store in session for later steps
        session_start();
        $_SESSION['install'] = compact('host', 'name', 'user', 'pass', 'port');
        header('Location: /install/?step=3');
        exit;
    } else {
        $errors[] = 'Database connection failed: ' . $result['error'];
    }
}

// STEP 3: Platform info
if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    if (empty($_SESSION['install'])) { header('Location: /install/?step=2'); exit; }
    $_SESSION['install']['platform_name'] = trim($_POST['platform_name'] ?? 'OCCI Blogs');
    $_SESSION['install']['tagline']       = trim($_POST['tagline'] ?? '');
    $_SESSION['install']['admin_email']   = trim($_POST['admin_email'] ?? '');
    header('Location: /install/?step=4');
    exit;
}

// STEP 4: Admin account
if ($step === 4 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    if (empty($_SESSION['install'])) { header('Location: /install/?step=2'); exit; }

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $pass1    = $_POST['password'] ?? '';
    $pass2    = $_POST['password2'] ?? '';

    if (!$username)           $errors[] = 'Username is required.';
    if (!$email)              $errors[] = 'Email is required.';
    if (strlen($pass1) < 8)  $errors[] = 'Password must be at least 8 characters.';
    if ($pass1 !== $pass2)    $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $db = $_SESSION['install'];
        // Import schema
        $schemaResult = Installer::importSchema($db['host'], $db['name'], $db['user'], $db['pass'], $db['port']);
        if (!$schemaResult['ok']) {
            $errors[] = 'Database setup failed: ' . $schemaResult['error'];
        } else {
            $pdo = Installer::makePdo($db['host'], $db['name'], $db['user'], $db['pass'], $db['port']);
            Installer::createAdminUser($pdo, $username, $email, $pass1);
            Installer::writeSiteSettings($pdo, [
                'platform_name' => $db['platform_name'] ?? 'OCCI Blogs',
                'tagline'       => $db['tagline'] ?? '',
                'admin_email'   => $db['admin_email'] ?? $email,
            ]);

            // Write config.local.php
            Installer::writeConfig([
                'db_host_q'   => var_export($db['host'], true),
                'db_name_q'   => var_export($db['name'], true),
                'db_user_q'   => var_export($db['user'], true),
                'db_pass_q'   => var_export($db['pass'], true),
                'base_path_q' => var_export(BASE_PATH, true),
                'env'         => 'production',
            ]);

            Installer::writeLock(Installer::VERSION);
            header('Location: /install/?step=5');
            exit;
        }
    }
}

// Load session for display
if ($step >= 2) { if (session_status() === PHP_SESSION_NONE) session_start(); }
$checks = ($step === 1) ? Installer::checkRequirements() : [];
$hasBlocking = Installer::hasBlockingFailures($checks);

// -------------------------------------------------------
// HTML
// -------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>OCCI Blogs &mdash; Installer</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f0f2f5; color: #1a1a2e; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
  .installer { background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.08); max-width: 560px; width: 100%; padding: 2.5rem; }
  .logo { text-align: center; margin-bottom: 2rem; }
  .logo h1 { font-size: 1.6rem; font-weight: 700; color: #4a2c6e; }
  .logo p { color: #666; font-size: .9rem; margin-top: .25rem; }
  .steps { display: flex; gap: .5rem; margin-bottom: 2rem; }
  .step-dot { flex: 1; height: 4px; border-radius: 2px; background: #e0e0e0; }
  .step-dot.done { background: #4a2c6e; }
  .step-dot.active { background: #7c4dbd; }
  h2 { font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem; color: #1a1a2e; }
  .form-group { margin-bottom: 1.25rem; }
  label { display: block; font-size: .85rem; font-weight: 500; color: #555; margin-bottom: .4rem; }
  input[type=text], input[type=email], input[type=password], input[type=number] {
    width: 100%; padding: .65rem .9rem; border: 1.5px solid #d0d0d0; border-radius: 8px;
    font-size: .95rem; transition: border-color .2s; outline: none;
  }
  input:focus { border-color: #7c4dbd; }
  .btn { display: inline-block; padding: .75rem 1.75rem; background: #4a2c6e; color: #fff; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: background .2s; }
  .btn:hover { background: #7c4dbd; }
  .btn-row { margin-top: 1.75rem; display: flex; justify-content: flex-end; }
  .errors { background: #fef2f2; border: 1px solid #fca5a5; border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; color: #b91c1c; font-size: .9rem; }
  .errors li { list-style: disc; margin-left: 1.25rem; }
  .check-list { list-style: none; }
  .check-list li { display: flex; align-items: center; gap: .75rem; padding: .5rem 0; border-bottom: 1px solid #f0f0f0; font-size: .9rem; }
  .check-list li:last-child { border-bottom: none; }
  .badge { display: inline-block; padding: .15rem .55rem; border-radius: 4px; font-size: .75rem; font-weight: 700; }
  .badge-ok { background: #d1fae5; color: #065f46; }
  .badge-fail { background: #fee2e2; color: #991b1b; }
  .badge-warn { background: #fef3c7; color: #92400e; }
  .success-icon { font-size: 3rem; text-align: center; margin-bottom: 1rem; }
  small { color: #888; font-size: .8rem; display: block; margin-top: .25rem; }
  .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
</style>
</head>
<body>
<div class="installer">
  <div class="logo">
    <h1>OCCI Blogs</h1>
    <p>Platform Installer</p>
  </div>

  <!-- Progress dots -->
  <div class="steps">
    <?php for ($i = 1; $i <= 5; $i++): ?>
      <div class="step-dot <?= $i < $step ? 'done' : ($i === $step ? 'active' : '') ?>"></div>
    <?php endfor; ?>
  </div>

  <?php if ($errors): ?>
  <div class="errors"><ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <?php
  // ---- STEP 1: Requirements ----
  if ($step === 1): ?>
  <h2>Step 1: System Requirements</h2>
  <ul class="check-list">
    <?php foreach ($checks as $c): ?>
    <li>
      <span class="badge <?= $c['pass'] ? 'badge-ok' : ($c['fatal'] ? 'badge-fail' : 'badge-warn') ?>">
        <?= $c['pass'] ? 'OK' : ($c['fatal'] ? 'FAIL' : 'WARN') ?>
      </span>
      <span><?= h($c['label']) ?></span>
      <span style="margin-left:auto;color:#888;font-size:.8rem"><?= h($c['value']) ?></span>
    </li>
    <?php endforeach; ?>
  </ul>
  <div class="btn-row">
    <?php if (!$hasBlocking): ?>
    <a href="/install/?step=2" class="btn">Continue</a>
    <?php else: ?>
    <span style="color:#b91c1c;font-size:.9rem">Please fix the issues above before continuing.</span>
    <?php endif; ?>
  </div>

  <?php elseif ($step === 2): ?>
  <h2>Step 2: Database Connection</h2>
  <form method="post">
    <div class="form-group"><label>Database Host</label><input type="text" name="db_host" value="localhost"></div>
    <div class="row-2">
      <div class="form-group"><label>Database Name</label><input type="text" name="db_name" placeholder="occi_blogs" required></div>
      <div class="form-group"><label>Port</label><input type="number" name="db_port" value="3306"></div>
    </div>
    <div class="row-2">
      <div class="form-group"><label>Database User</label><input type="text" name="db_user" required></div>
      <div class="form-group"><label>Password</label><input type="password" name="db_pass"></div>
    </div>
    <small>The installer will create the database if it does not exist.</small>
    <div class="btn-row"><button type="submit" class="btn">Test &amp; Continue</button></div>
  </form>

  <?php elseif ($step === 3): ?>
  <h2>Step 3: Platform Info</h2>
  <form method="post">
    <div class="form-group"><label>Platform Name</label><input type="text" name="platform_name" value="OCCI Blogs" required></div>
    <div class="form-group"><label>Tagline <span style="font-weight:400">(optional)</span></label><input type="text" name="tagline" placeholder="Independent Catholic voices"></div>
    <div class="form-group"><label>Admin Email</label><input type="email" name="admin_email" required></div>
    <div class="btn-row"><button type="submit" class="btn">Continue</button></div>
  </form>

  <?php elseif ($step === 4): ?>
  <h2>Step 4: Create Admin Account</h2>
  <form method="post">
    <div class="form-group"><label>Username</label><input type="text" name="username" required autocomplete="off"></div>
    <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
    <div class="form-group"><label>Password <span style="font-weight:400">(8+ characters)</span></label><input type="password" name="password" required minlength="8"></div>
    <div class="form-group"><label>Confirm Password</label><input type="password" name="password2" required></div>
    <div class="btn-row"><button type="submit" class="btn">Install OCCI Blogs</button></div>
  </form>

  <?php elseif ($step === 5): ?>
  <div class="success-icon">&#x2705;</div>
  <h2 style="text-align:center">Installation Complete!</h2>
  <p style="text-align:center;color:#555;margin-bottom:1.5rem">OCCI Blogs has been installed successfully.</p>
  <div class="btn-row" style="justify-content:center">
    <a href="/admin/login" class="btn">Go to Admin Panel</a>
  </div>
  <?php endif; ?>
</div>
<?php
function h(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
</body>
</html>
