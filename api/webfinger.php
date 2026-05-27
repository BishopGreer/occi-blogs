<?php
/**
 * WebFinger — GET /.well-known/webfinger?resource=acct:{slug}@{host}
 * Also handles resource=https://{host}/{slug}
 * Called from index.php.
 */

$resource = $_GET['resource'] ?? '';
if (!$resource) {
    http_response_code(400);
    json(['error' => 'resource parameter required'], 400);
    exit;
}

$host = parse_url(siteUrl(), PHP_URL_HOST);
$slug = null;

// acct:{slug}@{host}
if (preg_match('/^acct:([^@]+)@' . preg_quote($host, '/') . '$/', $resource, $m)) {
    $slug = $m[1];
}
// https://{host}/{slug}
elseif (preg_match('#^https?://' . preg_quote($host, '/') . '/([^/?#]+)$#', $resource, $m)) {
    $slug = $m[1];
}

if (!$slug) {
    http_response_code(404);
    exit;
}

$blog = Database::fetch("SELECT * FROM blogs WHERE slug = ? AND ap_enabled = 1 AND is_public = 1", [$slug]);
if (!$blog) {
    http_response_code(404);
    exit;
}

$actorUrl = Federator::actorUrl($blog);

header('Content-Type: application/jrd+json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
echo json_encode([
    'subject' => 'acct:' . $blog['slug'] . '@' . $host,
    'aliases' => [$actorUrl],
    'links'   => [
        [
            'rel'  => 'self',
            'type' => 'application/activity+json',
            'href' => $actorUrl,
        ],
        [
            'rel'  => 'http://webfinger.net/rel/profile-page',
            'type' => 'text/html',
            'href' => $actorUrl,
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
exit;
