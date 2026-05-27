<?php
/**
 * OCCI Blogs — Front Controller
 * All requests route through here (except /themes/, /public/, /install/).
 */

define('BASE_PATH', __DIR__);
require BASE_PATH . '/config/config.php';
require BASE_PATH . '/core/Database.php';
require BASE_PATH . '/core/Auth.php';
require BASE_PATH . '/core/helpers.php';
require BASE_PATH . '/core/Media.php';
require BASE_PATH . '/core/Updater.php';

Auth::init();

// Run any pending DB migrations silently on every request
if (Auth::check()) {
    Updater::runPendingMigrations();
}

$uri    = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$uri    = '/' . trim($uri, '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// -------------------------------------------------------
// Install redirect
// -------------------------------------------------------
if (!file_exists(BASE_PATH . '/config/install.lock') && !str_starts_with($uri, '/install')) {
    header('Location: /install/');
    exit;
}

// -------------------------------------------------------
// Logout
// -------------------------------------------------------
if ($uri === '/admin/logout') {
    Auth::logout();
    redirect('/admin/login');
}

// -------------------------------------------------------
// Admin routes
// -------------------------------------------------------
if (str_starts_with($uri, '/admin')) {
    $adminUri = substr($uri, 6) ?: '/';
    $adminUri = '/' . trim($adminUri, '/');

    // Map admin URI to file
    $parts = explode('/', trim($adminUri, '/'));
    $p0    = $parts[0] ?? '';
    $p1    = $parts[1] ?? '';
    $p2    = $parts[2] ?? '';
    $p3    = $parts[3] ?? '';

    match (true) {
        $adminUri === '/login'                                          => require BASE_PATH . '/admin/login.php',
        $adminUri === '/' || $adminUri === ''                          => require BASE_PATH . '/admin/index.php',
        $adminUri === '/blogs'                                         => require BASE_PATH . '/admin/blogs.php',
        $adminUri === '/blogs/new'                                     => require BASE_PATH . '/admin/blog-edit.php',
        $p0 === 'blogs' && $p2 === 'edit'                              => (function() use ($p1) { $_GET['id'] = (int)$p1; require BASE_PATH . '/admin/blog-edit.php'; })(),
        $p0 === 'blogs' && $p2 === 'posts' && $p3 === 'new'            => (function() use ($p1) { $_GET['blog_id'] = (int)$p1; require BASE_PATH . '/admin/post-edit.php'; })(),
        $p0 === 'blogs' && $p2 === 'posts' && is_numeric($p3) && (($parts[4] ?? '') === 'edit') => (function() use ($p1, $p3) { $_GET['blog_id'] = (int)$p1; $_GET['id'] = (int)$p3; require BASE_PATH . '/admin/post-edit.php'; })(),
        $p0 === 'blogs' && $p2 === 'posts'                             => (function() use ($p1) { $_GET['blog_id'] = (int)$p1; require BASE_PATH . '/admin/posts.php'; })(),
        $p0 === 'blogs' && $p2 === 'analytics'                        => (function() use ($p1) { $_GET['blog_id'] = (int)$p1; require BASE_PATH . '/admin/analytics.php'; })(),
        $adminUri === '/media'                                         => require BASE_PATH . '/admin/media.php',
        $adminUri === '/tags'                                          => require BASE_PATH . '/admin/tags.php',
        $adminUri === '/settings'                                      => require BASE_PATH . '/admin/settings.php',
        $adminUri === '/users'                                         => require BASE_PATH . '/admin/users.php',
        $adminUri === '/superadmin'                                    => require BASE_PATH . '/admin/superadmin.php',
        $adminUri === '/ajax/media-upload'                             => require BASE_PATH . '/admin/ajax/media-upload.php',
        $adminUri === '/ajax/slug-check'                               => require BASE_PATH . '/admin/ajax/slug-check.php',
        default => (function() { http_response_code(404); require BASE_PATH . '/templates/404.php'; })(),
    };
    exit;
}

// -------------------------------------------------------
// API routes
// -------------------------------------------------------
if (str_starts_with($uri, '/api')) {
    match (true) {
        $uri === '/api/media/upload' => require BASE_PATH . '/api/media.php',
        default => json(['error' => 'Not found'], 404),
    };
    exit;
}

// -------------------------------------------------------
// Install
// -------------------------------------------------------
if (str_starts_with($uri, '/install')) {
    require BASE_PATH . '/install/index.php';
    exit;
}

// -------------------------------------------------------
// Platform home
// -------------------------------------------------------
if ($uri === '/') {
    require BASE_PATH . '/templates/home.php';
    exit;
}

// -------------------------------------------------------
// Sitemap
// -------------------------------------------------------
if ($uri === '/sitemap.xml') {
    header('Content-Type: application/xml; charset=utf-8');
    $blogs = Database::fetchAll("SELECT * FROM blogs WHERE is_public = 1 ORDER BY created_at DESC");
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    echo '<url><loc>' . h(siteUrl()) . '</loc></url>';
    foreach ($blogs as $b) {
        echo '<url><loc>' . h(blogUrl($b)) . '</loc></url>';
        $posts = Database::fetchAll("SELECT slug, updated_at FROM posts WHERE blog_id = ? AND status = 'published' ORDER BY published_at DESC LIMIT 100", [$b['id']]);
        foreach ($posts as $p) {
            echo '<url><loc>' . h(blogUrl($b, $p['slug'])) . '</loc><lastmod>' . substr($p['updated_at'], 0, 10) . '</lastmod></url>';
        }
    }
    echo '</urlset>';
    exit;
}

// -------------------------------------------------------
// Public blog routes: /{slug}[/...]
// -------------------------------------------------------
$parts = explode('/', trim($uri, '/'));
$seg1  = $parts[0] ?? '';
$seg2  = $parts[1] ?? '';
$seg3  = $parts[2] ?? '';

if (!$seg1) { http_response_code(404); require BASE_PATH . '/templates/404.php'; exit; }

// Load blog
$blog = Database::fetch("SELECT * FROM blogs WHERE slug = ? AND is_public = 1", [$seg1]);
if (!$blog) { http_response_code(404); require BASE_PATH . '/templates/404.php'; exit; }

$blogUrl  = blogUrl($blog);
$feedUrl  = blogUrl($blog, 'feed');
$themePath = BASE_PATH . '/themes/' . ($blog['theme'] ?? 'minimal');
if (!is_dir($themePath)) $themePath = BASE_PATH . '/themes/minimal';

// Sub-routes
if (!$seg2) {
    // Blog index
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = POSTS_PER_PAGE;
    $total   = (int)Database::fetch("SELECT COUNT(*) as n FROM posts WHERE blog_id = ? AND status = 'published'", [$blog['id']])['n'];
    $posts   = Database::fetchAll(
        "SELECT * FROM posts WHERE blog_id = ? AND status = 'published' ORDER BY published_at DESC LIMIT ? OFFSET ?",
        [$blog['id'], $perPage, ($page - 1) * $perPage]
    );
    $pagination = pagination($total, $page, $perPage, blogUrl($blog));
    require $themePath . '/index.php';
    exit;
}

if ($seg2 === 'feed') {
    if (file_exists(BASE_PATH . '/core/Feed.php')) {
        require BASE_PATH . '/core/Feed.php';
        Feed::rss($blog);
    } else {
        header('Content-Type: application/rss+xml; charset=utf-8');
        $posts = Database::fetchAll("SELECT * FROM posts WHERE blog_id = ? AND status = 'published' ORDER BY published_at DESC LIMIT 20", [$blog['id']]);
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<rss version="2.0"><channel>';
        echo '<title>' . h($blog['name']) . '</title>';
        echo '<link>' . h($blogUrl) . '</link>';
        echo '<description>' . h($blog['description'] ?? '') . '</description>';
        foreach ($posts as $p) {
            echo '<item><title>' . h($p['title']) . '</title>';
            echo '<link>' . h(blogUrl($blog, $p['slug'])) . '</link>';
            echo '<pubDate>' . date('r', strtotime($p['published_at'] ?? $p['created_at'])) . '</pubDate>';
            echo '<description><![CDATA[' . ($p['excerpt'] ?: excerpt($p['content'] ?? '')) . ']]></description></item>';
        }
        echo '</channel></rss>';
    }
    exit;
}

if ($seg2 === 'tag' && $seg3) {
    $tag  = Database::fetch("SELECT * FROM tags WHERE blog_id = ? AND slug = ?", [$blog['id'], $seg3]);
    if (!$tag) { http_response_code(404); require BASE_PATH . '/templates/404.php'; exit; }
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = POSTS_PER_PAGE;
    $total   = (int)Database::fetch("SELECT COUNT(*) as n FROM post_tags pt JOIN posts p ON pt.post_id = p.id WHERE pt.tag_id = ? AND p.status = 'published'", [$tag['id']])['n'];
    $posts   = Database::fetchAll(
        "SELECT p.* FROM posts p JOIN post_tags pt ON p.id = pt.post_id WHERE pt.tag_id = ? AND p.status = 'published' ORDER BY p.published_at DESC LIMIT ? OFFSET ?",
        [$tag['id'], $perPage, ($page - 1) * $perPage]
    );
    $pagination = pagination($total, $page, $perPage, blogUrl($blog, 'tag/' . $seg3));
    require $themePath . '/tag.php';
    exit;
}

// Track analytics
if (file_exists(BASE_PATH . '/core/Analytics.php')) {
    require_once BASE_PATH . '/core/Analytics.php';
}

// Single post
$post = Database::fetch("SELECT * FROM posts WHERE blog_id = ? AND slug = ? AND status = 'published'", [$blog['id'], $seg2]);
if (!$post) { http_response_code(404); require BASE_PATH . '/templates/404.php'; exit; }

// Track view
if (setting('analytics_enabled', '1') === '1') {
    $ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . date('Y-m-d'));
    $ua     = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
    $isBot  = preg_match('/bot|crawl|spider|slurp|facebookexternalhit|whatsapp|telegram/i', $ua);
    $device = $isBot ? 'bot' : (preg_match('/mobile|android|iphone|ipad/i', $ua) ? 'mobile' : 'desktop');
    try {
        Database::insert('post_views', [
            'post_id'   => $post['id'],
            'blog_id'   => $blog['id'],
            'ip_hash'   => $ipHash,
            'referer'   => substr($_SERVER['HTTP_REFERER'] ?? '', 0, 500),
            'device'    => $device,
        ]);
    } catch (\Exception) { /* non-fatal */ }
}

$tags = Database::fetchAll(
    "SELECT t.* FROM tags t JOIN post_tags pt ON t.id = pt.tag_id WHERE pt.post_id = ?",
    [$post['id']]
);
require $themePath . '/post.php';
exit;
