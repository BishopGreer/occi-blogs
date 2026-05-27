<?php
class Auth {
    private static ?array $user = null;

    const ROLE_HIERARCHY = ['blogger' => 1, 'superadmin' => 2];

    public static function init(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => (APP_ENV === 'production'),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }

        // Restore from remember-me cookie
        if (empty($_SESSION['user_id']) && !empty($_COOKIE['occi_blogs_remember'])) {
            $token = $_COOKIE['occi_blogs_remember'];
            $user  = Database::fetch("SELECT * FROM users WHERE remember_token = ?", [$token]);
            if ($user) {
                self::setSession($user);
            }
        }
    }

    public static function attempt(string $email, string $password, bool $remember = false): bool {
        $user = Database::fetch("SELECT * FROM users WHERE email = ?", [strtolower(trim($email))]);
        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }
        self::setSession($user);
        Database::update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);

        if ($remember) {
            $token = bin2hex(random_bytes(32));
            Database::update('users', ['remember_token' => $token], 'id = ?', [$user['id']]);
            setcookie('occi_blogs_remember', $token, time() + 60 * 60 * 24 * 30, '/', '', APP_ENV === 'production', true);
        }
        return true;
    }

    public static function logout(): void {
        if (isset($_COOKIE['occi_blogs_remember'])) {
            Database::update('users', ['remember_token' => null], 'id = ?', [self::id()]);
            setcookie('occi_blogs_remember', '', time() - 3600, '/');
        }
        $_SESSION = [];
        session_destroy();
        self::$user = null;
    }

    public static function check(): bool {
        return !empty($_SESSION['user_id']);
    }

    public static function id(): ?int {
        return $_SESSION['user_id'] ?? null;
    }

    public static function user(): ?array {
        if (self::$user === null && self::check()) {
            self::$user = Database::fetch("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
        }
        return self::$user;
    }

    public static function role(): string {
        return self::user()['role'] ?? '';
    }

    public static function isSuperAdmin(): bool {
        return self::role() === 'superadmin';
    }

    public static function ownsBlog(int $blogId): bool {
        if (!self::check()) return false;
        if (self::isSuperAdmin()) return true;
        $blog = Database::fetch("SELECT user_id FROM blogs WHERE id = ?", [$blogId]);
        return $blog && (int)$blog['user_id'] === self::id();
    }

    public static function requireLogin(string $redirect = '/admin/login'): void {
        if (!self::check()) {
            header('Location: ' . $redirect);
            exit;
        }
    }

    public static function requireSuperAdmin(): void {
        self::requireLogin();
        if (!self::isSuperAdmin()) {
            http_response_code(403);
            die('Access denied. Superadmin required.');
        }
    }

    public static function requireBlogAccess(int $blogId): void {
        self::requireLogin();
        if (!self::ownsBlog($blogId)) {
            http_response_code(403);
            die('Access denied. You do not own this blog.');
        }
    }

    private static function setSession(array $user): void {
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        self::$user = $user;
    }

    public static function hashPassword(string $plain): string {
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function csrf(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(): void {
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(403);
            die('Invalid CSRF token.');
        }
    }
}
