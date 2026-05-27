<?php
/**
 * OCCI Blogs — Feed Generator
 * Produces RSS 2.0 and Atom 1.0 feeds for a blog.
 */
class Feed
{
    private const LIMIT = 20;

    // -------------------------------------------------------
    // RSS 2.0
    // -------------------------------------------------------
    public static function rss(array $blog): void
    {
        $posts   = self::posts($blog['id']);
        $blogUrl = blogUrl($blog);
        $feedUrl = blogUrl($blog, 'feed');

        header('Content-Type: application/rss+xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<rss version="2.0"'
            . ' xmlns:atom="http://www.w3.org/2005/Atom"'
            . ' xmlns:content="http://purl.org/rss/1.0/modules/content/"'
            . '>' . "\n";
        echo '<channel>' . "\n";
        echo '  <title>' . self::x($blog['name']) . '</title>' . "\n";
        echo '  <link>' . self::x($blogUrl) . '</link>' . "\n";
        echo '  <description>' . self::x($blog['description'] ?? $blog['tagline'] ?? '') . '</description>' . "\n";
        echo '  <language>en-us</language>' . "\n";
        echo '  <generator>OCCI Blogs</generator>' . "\n";
        echo '  <atom:link href="' . self::x($feedUrl) . '" rel="self" type="application/rss+xml"/>' . "\n";

        if ($posts) {
            echo '  <lastBuildDate>' . date('r', strtotime($posts[0]['updated_at'] ?? $posts[0]['created_at'])) . '</lastBuildDate>' . "\n";
        }

        foreach ($posts as $p) {
            $url     = blogUrl($blog, $p['slug']);
            $pubDate = date('r', strtotime($p['published_at'] ?? $p['created_at']));
            $summary = $p['excerpt'] ?: excerpt($p['content'] ?? '', 50);

            echo '  <item>' . "\n";
            echo '    <title>' . self::x($p['title']) . '</title>' . "\n";
            echo '    <link>' . self::x($url) . '</link>' . "\n";
            echo '    <guid isPermaLink="true">' . self::x($url) . '</guid>' . "\n";
            echo '    <pubDate>' . $pubDate . '</pubDate>' . "\n";
            echo '    <description><![CDATA[' . $summary . ']]></description>' . "\n";
            if ($p['content']) {
                echo '    <content:encoded><![CDATA[' . $p['content'] . ']]></content:encoded>' . "\n";
            }
            echo '  </item>' . "\n";
        }

        echo '</channel>' . "\n";
        echo '</rss>';
    }

    // -------------------------------------------------------
    // Atom 1.0
    // -------------------------------------------------------
    public static function atom(array $blog): void
    {
        $posts   = self::posts($blog['id']);
        $blogUrl = blogUrl($blog);
        $feedUrl = blogUrl($blog, 'feed/atom');
        $updated = $posts
            ? date('c', strtotime($posts[0]['updated_at'] ?? $posts[0]['created_at']))
            : date('c');
        $subtitle = $blog['description'] ?? $blog['tagline'] ?? '';

        header('Content-Type: application/atom+xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<feed xmlns="http://www.w3.org/2005/Atom">' . "\n";
        echo '  <title>' . self::x($blog['name']) . '</title>' . "\n";
        if ($subtitle) {
            echo '  <subtitle>' . self::x($subtitle) . '</subtitle>' . "\n";
        }
        echo '  <link href="' . self::x($blogUrl) . '"/>' . "\n";
        echo '  <link rel="self" href="' . self::x($feedUrl) . '"/>' . "\n";
        echo '  <id>' . self::x($blogUrl) . '</id>' . "\n";
        echo '  <updated>' . $updated . '</updated>' . "\n";
        echo '  <generator>OCCI Blogs</generator>' . "\n";

        foreach ($posts as $p) {
            $url       = blogUrl($blog, $p['slug']);
            $published = date('c', strtotime($p['published_at'] ?? $p['created_at']));
            $updatedP  = date('c', strtotime($p['updated_at'] ?? $p['created_at']));

            echo '  <entry>' . "\n";
            echo '    <title>' . self::x($p['title']) . '</title>' . "\n";
            echo '    <link href="' . self::x($url) . '"/>' . "\n";
            echo '    <id>' . self::x($url) . '</id>' . "\n";
            echo '    <published>' . $published . '</published>' . "\n";
            echo '    <updated>' . $updatedP . '</updated>' . "\n";
            if ($p['excerpt']) {
                echo '    <summary type="html"><![CDATA[' . $p['excerpt'] . ']]></summary>' . "\n";
            }
            if ($p['content']) {
                echo '    <content type="html"><![CDATA[' . $p['content'] . ']]></content>' . "\n";
            }
            echo '  </entry>' . "\n";
        }

        echo '</feed>';
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------
    private static function posts(int $blogId): array
    {
        return Database::fetchAll(
            "SELECT * FROM posts WHERE blog_id = ? AND status = 'published' ORDER BY published_at DESC LIMIT " . self::LIMIT,
            [$blogId]
        );
    }

    /** XML-safe attribute/element escaping */
    private static function x(string $str): string
    {
        return htmlspecialchars($str, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
