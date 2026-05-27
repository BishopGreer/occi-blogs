#!/usr/bin/env php
<?php
/**
 * OCCI Blogs — Federation Queue Worker
 * Run from cron every 2 minutes:
 *
 *   * /2 * * * * php /var/web/blogs.myocci.net/public_html/scripts/deliver.php >> /var/log/occi-blogs-deliver.log 2>&1
 */

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/config/config.php';
require BASE_PATH . '/core/Database.php';
require BASE_PATH . '/core/Auth.php';
require BASE_PATH . '/core/helpers.php';
require BASE_PATH . '/core/HttpSignature.php';
require BASE_PATH . '/core/Federator.php';

$start   = microtime(true);
$results = Federator::processQueue(50);
$elapsed = round((microtime(true) - $start) * 1000);

$ts = date('Y-m-d H:i:s');
echo "[{$ts}] Delivered: {$results['ok']}, Failed: {$results['fail']}, Time: {$elapsed}ms\n";

if ($results['errors']) {
    foreach ($results['errors'] as $err) {
        echo "[{$ts}]   ERROR: {$err}\n";
    }
}
