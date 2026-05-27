<?php
/**
 * ActivityPub Inbox — POST /{slug}/inbox
 * Handles Follow and Undo{Follow} activities.
 * Called from index.php with $_GET['blog_slug'] set.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$blogSlug = $_GET['blog_slug'] ?? '';
$blog     = Database::fetch("SELECT * FROM blogs WHERE slug = ? AND ap_enabled = 1", [$blogSlug]);
if (!$blog) {
    http_response_code(404);
    exit;
}

// Read body
$rawBody  = file_get_contents('php://input');
$activity = json_decode($rawBody, true);
if (!$activity || empty($activity['type'])) {
    http_response_code(400);
    exit;
}

// Verify HTTP signature --------------------------------------------------
$sigHeader = $_SERVER['HTTP_SIGNATURE'] ?? '';
if (!$sigHeader) {
    http_response_code(401);
    exit;
}

preg_match('/keyId="([^"]+)"/', $sigHeader, $m);
$keyId     = $m[1] ?? '';
$actorUri  = preg_replace('/#.*$/', '', $keyId);
if (!$actorUri) {
    http_response_code(401);
    exit;
}

$remoteActor = Federator::fetchRemoteActor($actorUri);
if (!$remoteActor || !$remoteActor['public_key_pem']) {
    http_response_code(401);
    exit;
}

// Build lowercase header map for signature verification
$reqHeaders = [];
foreach ($_SERVER as $k => $v) {
    if (str_starts_with($k, 'HTTP_')) {
        $name             = strtolower(str_replace(['HTTP_', '_'], ['', '-'], $k));
        $reqHeaders[$name] = $v;
    }
}
// PHP puts Content-Type in CONTENT_TYPE, not HTTP_CONTENT_TYPE
if (isset($_SERVER['CONTENT_TYPE'])) {
    $reqHeaders['content-type'] = $_SERVER['CONTENT_TYPE'];
}

$path = '/' . $blogSlug . '/inbox';
if (!HttpSignature::verify('POST', $path, $reqHeaders, $remoteActor['public_key_pem'])) {
    http_response_code(401);
    exit;
}

// Process activity -------------------------------------------------------
$type = $activity['type'];

// Follow
if ($type === 'Follow') {
    $senderUri  = $activity['actor'] ?? '';
    $sender     = $senderUri ? Federator::fetchRemoteActor($senderUri) : null;
    if (!$sender || !$sender['inbox_url']) {
        http_response_code(400);
        exit;
    }

    // Store follower (ignore duplicate)
    try {
        Database::insert('blog_followers', [
            'blog_id'            => $blog['id'],
            'remote_actor_id'    => $sender['id'],
            'follow_activity_id' => $activity['id'] ?? null,
        ]);
        Database::query(
            "UPDATE blogs SET followers_count = followers_count + 1 WHERE id = ?",
            [$blog['id']]
        );
    } catch (\Exception) {
        // Already following — still send Accept
    }

    // Send Accept back
    $actorUrl = Federator::actorUrl($blog);
    $accept   = [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id'       => $actorUrl . '#accept-follow-' . time(),
        'type'     => 'Accept',
        'actor'    => $actorUrl,
        'object'   => $activity,
    ];
    Federator::enqueue($blog['id'], $accept, $sender['inbox_url'], $actorUrl . '#main-key');

    http_response_code(202);
    exit;
}

// Undo{Follow}
if ($type === 'Undo') {
    $inner = $activity['object'] ?? [];
    if (($inner['type'] ?? '') === 'Follow') {
        $senderUri = $activity['actor'] ?? '';
        $sender    = $senderUri ? Federator::fetchRemoteActor($senderUri) : null;
        if ($sender) {
            Database::delete('blog_followers', 'blog_id = ? AND remote_actor_id = ?', [$blog['id'], $sender['id']]);
            Database::query(
                "UPDATE blogs SET followers_count = GREATEST(0, followers_count - 1) WHERE id = ?",
                [$blog['id']]
            );
        }
    }
    http_response_code(202);
    exit;
}

// All other activities acknowledged
http_response_code(202);
