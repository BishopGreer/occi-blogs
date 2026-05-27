<?php
/**
 * OCCI Blogs — ActivityPub Federator
 * Handles building AP activities, queuing delivery, and processing the queue.
 */
class Federator
{
    // -------------------------------------------------------
    // Actor helpers
    // -------------------------------------------------------

    public static function actorUrl(array $blog): string
    {
        return siteUrl() . '/' . $blog['slug'];
    }

    public static function actorJson(array $blog): array
    {
        $url = self::actorUrl($blog);
        return [
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                'https://w3id.org/security/v1',
            ],
            'id'                => $url,
            'type'              => 'Person',
            'preferredUsername' => $blog['slug'],
            'name'              => $blog['name'],
            'summary'           => $blog['description'] ?? $blog['tagline'] ?? '',
            'url'               => $url,
            'inbox'             => $url . '/inbox',
            'outbox'            => $url . '/outbox',
            'followers'         => $url . '/followers',
            'publicKey'         => [
                'id'           => $url . '#main-key',
                'owner'        => $url,
                'publicKeyPem' => $blog['ap_public_key'] ?? '',
            ],
        ];
    }

    // -------------------------------------------------------
    // Publish / delete posts
    // -------------------------------------------------------

    public static function deliverPost(array $blog, array $post): void
    {
        if (!$blog['ap_enabled']) return;

        $actorUrl = self::actorUrl($blog);
        $postUrl  = siteUrl() . '/' . $blog['slug'] . '/' . $post['slug'];

        $article = [
            'id'           => $postUrl,
            'type'         => 'Article',
            'attributedTo' => $actorUrl,
            'name'         => $post['title'],
            'content'      => $post['content'] ?? '',
            'summary'      => $post['excerpt'] ?: excerpt($post['content'] ?? '', 50),
            'url'          => $postUrl,
            'to'           => ['https://www.w3.org/ns/activitystreams#Public'],
            'cc'           => [$actorUrl . '/followers'],
            'published'    => date('c', strtotime($post['published_at'] ?? $post['created_at'])),
            'updated'      => date('c', strtotime($post['updated_at'] ?? $post['created_at'])),
        ];

        $activity = [
            '@context'  => 'https://www.w3.org/ns/activitystreams',
            'id'        => $actorUrl . '/activities/create-' . $post['id'],
            'type'      => 'Create',
            'actor'     => $actorUrl,
            'published' => $article['published'],
            'to'        => ['https://www.w3.org/ns/activitystreams#Public'],
            'cc'        => [$actorUrl . '/followers'],
            'object'    => $article,
        ];

        self::enqueueToFollowers($blog, $activity);
    }

    public static function deletePost(array $blog, array $post): void
    {
        if (!$blog['ap_enabled']) return;

        $actorUrl = self::actorUrl($blog);
        $postUrl  = siteUrl() . '/' . $blog['slug'] . '/' . $post['slug'];

        $activity = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id'       => $postUrl . '#delete',
            'type'     => 'Delete',
            'actor'    => $actorUrl,
            'to'       => ['https://www.w3.org/ns/activitystreams#Public'],
            'object'   => ['id' => $postUrl, 'type' => 'Tombstone'],
        ];

        self::enqueueToFollowers($blog, $activity);

        // Record tombstone
        try {
            Database::insert('tombstones', [
                'post_id' => $post['id'],
                'blog_id' => $blog['id'],
                'uri'     => $postUrl,
            ]);
        } catch (\Exception) {}
    }

    // -------------------------------------------------------
    // Queue management
    // -------------------------------------------------------

    /** Enqueue an activity to all followers of this blog */
    private static function enqueueToFollowers(array $blog, array $activity): void
    {
        $followers = Database::fetchAll(
            "SELECT ra.inbox_url, ra.shared_inbox_url
             FROM blog_followers bf
             JOIN remote_actors ra ON bf.remote_actor_id = ra.id
             WHERE bf.blog_id = ?",
            [$blog['id']]
        );

        $inboxes = [];
        foreach ($followers as $f) {
            $inbox = $f['shared_inbox_url'] ?: $f['inbox_url'];
            if ($inbox) $inboxes[$inbox] = true;
        }

        $keyId = self::actorUrl($blog) . '#main-key';
        foreach (array_keys($inboxes) as $inbox) {
            self::enqueue($blog['id'], $activity, $inbox, $keyId);
        }
    }

    /** Insert one delivery job into the queue */
    public static function enqueue(int $blogId, array $activity, string $inboxUrl, string $keyId): void
    {
        Database::insert('federation_queue', [
            'blog_id'      => $blogId,
            'activity'     => json_encode($activity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'inbox_url'    => $inboxUrl,
            'key_id'       => $keyId,
            'next_attempt' => date('Y-m-d H:i:s'),
        ]);
    }

    // -------------------------------------------------------
    // Queue worker (called by scripts/deliver.php)
    // -------------------------------------------------------

    public static function processQueue(int $limit = 20): array
    {
        $jobs = Database::fetchAll(
            "SELECT * FROM federation_queue
             WHERE attempts < 5 AND next_attempt <= NOW()
             ORDER BY next_attempt ASC
             LIMIT ?",
            [$limit]
        );

        $results = ['ok' => 0, 'fail' => 0, 'errors' => []];

        foreach ($jobs as $job) {
            [$ok, $err] = self::deliverOne($job);
            if ($ok) {
                Database::delete('federation_queue', 'id = ?', [$job['id']]);
                $results['ok']++;
            } else {
                $attempts = $job['attempts'] + 1;
                $backoff  = min(3600, 60 * (2 ** $attempts)); // 2m, 4m, 8m, 16m, 60m
                Database::update('federation_queue', [
                    'attempts'     => $attempts,
                    'next_attempt' => date('Y-m-d H:i:s', time() + $backoff),
                    'last_error'   => $err,
                ], 'id = ?', [$job['id']]);
                $results['fail']++;
                $results['errors'][] = "Job #{$job['id']}: $err";
            }
        }

        return $results;
    }

    /** Deliver one queue item. Returns [bool $success, string $error] */
    private static function deliverOne(array $job): array
    {
        // Look up private key from blog (avoids storing keys in queue table)
        $blog = Database::fetch("SELECT ap_private_key FROM blogs WHERE id = ?", [$job['blog_id']]);
        if (!$blog || !$blog['ap_private_key']) {
            return [false, 'Blog has no private key'];
        }

        $body    = $job['activity'];
        $url     = $job['inbox_url'];
        $keyId   = $job['key_id'];
        $privKey = $blog['ap_private_key'];

        $headers = [
            'Content-Type' => 'application/activity+json',
            'Accept'       => 'application/activity+json',
        ];

        try {
            HttpSignature::sign($headers, $url, 'POST', $body, $keyId, $privKey);
        } catch (\Exception $e) {
            return [false, 'Sign error: ' . $e->getMessage()];
        }

        $curlHeaders = [];
        foreach ($headers as $k => $v) {
            $curlHeaders[] = "$k: $v";
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $curlHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_USERAGENT      => 'OCCI-Blogs/1.1 (ActivityPub; +' . siteUrl() . ')',
        ]);

        curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) return [false, "curl: $curlErr"];
        if ($httpCode < 200 || $httpCode >= 300) return [false, "HTTP $httpCode"];

        return [true, ''];
    }

    // -------------------------------------------------------
    // Remote actor fetching
    // -------------------------------------------------------

    /** Fetch a remote actor's JSON, caching in remote_actors table (24h TTL) */
    public static function fetchRemoteActor(string $uri): ?array
    {
        // Strip any fragment (#main-key etc.)
        $uri = preg_replace('/#.*$/', '', $uri);

        // Check cache
        $cached = Database::fetch("SELECT * FROM remote_actors WHERE uri = ?", [$uri]);
        if ($cached && $cached['fetched_at'] && strtotime($cached['fetched_at']) > time() - 86400) {
            return $cached;
        }

        // Fetch from remote
        $ch = curl_init($uri);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_HTTPHEADER     => ['Accept: application/activity+json, application/ld+json'],
            CURLOPT_USERAGENT      => 'OCCI-Blogs/1.1 (ActivityPub)',
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || !$body) return $cached ?: null;

        $data = json_decode($body, true);
        if (!$data || empty($data['id'])) return $cached ?: null;

        $row = [
            'uri'               => $uri,
            'inbox_url'         => $data['inbox'] ?? '',
            'shared_inbox_url'  => $data['endpoints']['sharedInbox'] ?? null,
            'public_key_pem'    => $data['publicKey']['publicKeyPem'] ?? null,
            'username'          => $data['preferredUsername'] ?? null,
            'domain'            => parse_url($uri, PHP_URL_HOST),
            'fetched_at'        => date('Y-m-d H:i:s'),
        ];

        if (!$row['inbox_url']) return null;

        if ($cached) {
            Database::update('remote_actors', $row, 'uri = ?', [$uri]);
            return Database::fetch("SELECT * FROM remote_actors WHERE uri = ?", [$uri]);
        } else {
            $id = Database::insert('remote_actors', $row);
            return Database::fetch("SELECT * FROM remote_actors WHERE id = ?", [$id]);
        }
    }
}
