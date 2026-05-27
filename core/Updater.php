<?php
class Updater {

    const LOCK_FILE      = BASE_PATH . '/config/install.lock';
    const MIGRATIONS_DIR = BASE_PATH . '/install/migrations';
    const GITHUB_REPO    = 'BishopGreer/occi-blogs';
    const GITHUB_API     = 'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest';

    public static function installedVersion(): string {
        if (!file_exists(self::LOCK_FILE)) return 'unknown';
        $data = json_decode(file_get_contents(self::LOCK_FILE), true);
        return $data['version'] ?? 'unknown';
    }

    public static function updateLockVersion(string $version): void {
        $data = [];
        if (file_exists(self::LOCK_FILE)) {
            $data = json_decode(file_get_contents(self::LOCK_FILE), true) ?? [];
        }
        $data['version']    = $version;
        $data['updated_at'] = date('c');
        file_put_contents(self::LOCK_FILE, json_encode($data, JSON_PRETTY_PRINT));
    }

    public static function allMigrations(): array {
        if (!is_dir(self::MIGRATIONS_DIR)) return [];
        $files = glob(self::MIGRATIONS_DIR . '/*.sql');
        sort($files);
        return array_map(fn($f) => [
            'file'    => $f,
            'version' => basename($f, '.sql'),
        ], $files);
    }

    public static function appliedMigrations(): array {
        try {
            $rows = Database::fetchAll("SELECT version FROM migrations ORDER BY version ASC");
            return array_column($rows, 'version');
        } catch (\PDOException) {
            return [];
        }
    }

    public static function pendingMigrations(): array {
        $applied = self::appliedMigrations();
        return array_filter(
            self::allMigrations(),
            fn($m) => !in_array($m['version'], $applied, true)
        );
    }

    public static function runPendingMigrations(): array {
        $pending = self::pendingMigrations();
        $results = [];

        foreach ($pending as $m) {
            $sql = file_get_contents($m['file']);
            if ($sql === false) {
                $results[$m['version']] = ['ok' => false, 'error' => 'Could not read file.'];
                continue;
            }
            try {
                $pdo = Database::get();
                $statements = array_filter(
                    array_map(function(string $raw): string {
                        $lines = explode("\n", $raw);
                        while ($lines && preg_match('/^\s*(--|$)/', $lines[0])) {
                            array_shift($lines);
                        }
                        return trim(implode("\n", $lines));
                    }, explode(';', $sql)),
                    fn($s) => $s !== ''
                );
                foreach ($statements as $stmt) {
                    if (!empty(trim($stmt))) {
                        $pdo->exec($stmt);
                    }
                }
                Database::insert('migrations', [
                    'version'    => $m['version'],
                    'applied_at' => date('Y-m-d H:i:s'),
                ]);
                $results[$m['version']] = ['ok' => true, 'error' => null];
            } catch (\PDOException $e) {
                $results[$m['version']] = ['ok' => false, 'error' => $e->getMessage()];
                break;
            }
        }
        return $results;
    }
}
