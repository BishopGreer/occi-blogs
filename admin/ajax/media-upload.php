<?php
/**
 * AJAX media upload handler for TinyMCE images_upload_url
 * Returns {"location": "https://..."} on success
 */
require_once dirname(dirname(dirname(__FILE__))) . '/config/config.php';
require_once BASE_PATH . '/core/Database.php';
require_once BASE_PATH . '/core/Auth.php';
require_once BASE_PATH . '/core/helpers.php';
require_once BASE_PATH . '/core/Media.php';

Auth::init();
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

$blogId = (int)($_GET['blog_id'] ?? $_POST['blog_id'] ?? 0);

try {
    $media = Media::upload($_FILES['file'], Auth::id(), $blogId ?: null);
    $url   = Media::url($media);
    header('Content-Type: application/json');
    echo json_encode(['location' => $url]);
} catch (\Exception $e) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
