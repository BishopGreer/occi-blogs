<?php
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once BASE_PATH . '/core/Database.php';
require_once BASE_PATH . '/core/Auth.php';
require_once BASE_PATH . '/core/helpers.php';

Auth::init();
header('Content-Type: application/json');

if (!Auth::check()) { echo json_encode(['available' => false]); exit; }

$slug    = slugify($_GET['slug'] ?? '');
$table   = $_GET['table'] ?? 'blogs';
$exclude = (int)($_GET['exclude'] ?? 0);
$blogId  = (int)($_GET['blog_id'] ?? 0);

if (!$slug) { echo json_encode(['available' => false, 'slug' => '']); exit; }

if ($table === 'posts' && $blogId) {
    $exists = Database::fetch("SELECT id FROM posts WHERE blog_id = ? AND slug = ? AND id != ?", [$blogId, $slug, $exclude]);
} else {
    $exists = Database::fetch("SELECT id FROM blogs WHERE slug = ? AND id != ?", [$slug, $exclude]);
}

echo json_encode(['available' => !$exists, 'slug' => $slug]);
