#!/usr/bin/env php
<?php
/**
 * OCCI Blogs — Scheduled Post Publisher
 * Run from cron every minute:
 *
 *   * * * * * php /var/web/blogs.myocci.net/public_html/scripts/publish-scheduled.php >> /var/log/occi-blogs-scheduled.log 2>&1
 */

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/config/config.php';
require BASE_PATH . '/core/Database.php';
require BASE_PATH . '/core/Auth.php';
require BASE_PATH . '/core/helpers.php';
require BASE_PATH . '/core/HttpSignature.php';
require BASE_PATH . '/core/Federator.php';

$due = Database::fetchAll(
    "SELECT p.*, b.ap_enabled, b.slug as blog_slug
     FROM posts p JOIN blogs b ON p.blog_id = b.id
     WHERE p.status = 'scheduled' AND p.published_at <= NOW()"
);

if (!$due) exit(0);

$ts = date('Y-m-d H:i:s');
foreach ($due as $post) {
    Database::update('posts', ['status' => 'published'], 'id = ?', [$post['id']]);

    // Deliver via ActivityPub if blog has federation enabled
    if ($post['ap_enabled']) {
        $blog = Database::fetch("SELECT * FROM blogs WHERE id = ?", [$post['blog_id']]);
        if ($blog) {
            try {
                Federator::deliverPost($blog, $post);
            } catch (\Exception $e) {
                echo "[{$ts}] AP delivery error for post #{$post['id']}: {$e->getMessage()}\n";
            }
        }
    }

    echo "[{$ts}] Published post #{$post['id']}: {$post['title']}\n";
}
