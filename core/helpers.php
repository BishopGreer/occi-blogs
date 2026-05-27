<?php

function cspNonce(): string {
    static $nonce = null;
    if ($nonce === null) $nonce = base64_encode(random_bytes(16));
    return $nonce;
}

function h(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function slugify(string $str): string {
    $str = mb_strtolower(trim($str));
    $str = preg_replace('/[^\w\s-]/', '', $str);
    $str = preg_replace('/[\s_-]+/', '-', $str);
    return trim($str, '-');
}

function uniqueSlug(string $base, string $table, int $excludeId = 0, string $scopeCol = '', int $scopeVal = 0): string {
    $slug = slugify($base);
    $orig = $slug;
    $i    = 1;
    while (true) {
        $sql    = "SELECT id FROM `$table` WHERE slug = ? AND id != ?";
        $params = [$slug, $excludeId];
        if ($scopeCol && $scopeVal) {
            $sql    .= " AND `$scopeCol` = ?";
            $params[] = $scopeVal;
        }
        $row = Database::fetch($sql, $params);
        if (!$row) break;
        $slug = $orig . '-' . $i++;
    }
    return $slug;
}

function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

function flash(string $key, string $message = ''): ?string {
    if ($message !== '') {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function formatDate(string $datetime, string $format = 'F j, Y'): string {
    return date($format, strtotime($datetime));
}

function excerpt(string $content, int $words = 40): string {
    $text = strip_tags($content);
    $arr  = explode(' ', $text);
    if (count($arr) <= $words) return $text;
    return implode(' ', array_slice($arr, 0, $words)) . '&hellip;';
}

function siteUrl(string $path = ''): string {
    return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
}

function adminUrl(string $path = ''): string {
    return siteUrl('admin/' . ltrim($path, '/'));
}

function blogUrl(array $blog, string $path = ''): string {
    return siteUrl($blog['slug'] . ($path ? '/' . ltrim($path, '/') : ''));
}

function themeAsset(array $blog, string $file): string {
    $theme = $blog['theme'] ?? 'minimal';
    return siteUrl('themes/' . $theme . '/assets/' . ltrim($file, '/'));
}

function isCurrentPage(string $url): bool {
    $request = rtrim(strtok($_SERVER['REQUEST_URI'] ?? '', '?'), '/');
    $target  = rtrim(parse_url($url, PHP_URL_PATH) ?? '', '/');
    return $request === $target;
}

function json(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function jsonError(string $message, int $status = 400): never {
    json(['error' => $message], $status);
}

function truncate(string $str, int $len = 80): string {
    return mb_strlen($str) > $len ? mb_substr($str, 0, $len - 1) . '&hellip;' : $str;
}

function setting(string $key, string $default = ''): string {
    return Database::setting($key, $default);
}

function csrfField(): string {
    return '<input type="hidden" name="_csrf" value="' . h(Auth::csrf()) . '">';
}

function pagination(int $total, int $page, int $perPage, string $baseUrl): string {
    $pages = (int) ceil($total / $perPage);
    if ($pages <= 1) return '';
    $html = '<nav class="pagination" aria-label="Pagination">';
    for ($i = 1; $i <= $pages; $i++) {
        $active = $i === $page ? ' active' : '';
        $sep    = str_contains($baseUrl, '?') ? '&' : '?';
        $html  .= '<a href="' . $baseUrl . $sep . 'page=' . $i . '" class="page-link' . $active . '">' . $i . '</a>';
    }
    $html .= '</nav>';
    return $html;
}

function availableThemes(): array {
    $themes = [];
    $dir    = BASE_PATH . '/themes';
    if (!is_dir($dir)) return $themes;
    foreach (scandir($dir) as $folder) {
        if ($folder[0] === '.') continue;
        $path = $dir . '/' . $folder;
        if (!is_dir($path)) continue;
        $meta = ['name' => ucfirst($folder), 'description' => '', 'preview' => ''];
        $jsonFile = $path . '/theme.json';
        if (file_exists($jsonFile)) {
            $decoded = json_decode(file_get_contents($jsonFile), true);
            if (is_array($decoded)) $meta = array_merge($meta, $decoded);
        }
        $themes[$folder] = $meta;
    }
    return $themes;
}
