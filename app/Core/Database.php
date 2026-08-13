<?php
declare(strict_types=1);

namespace App\Core;

defined('APP_RUNNING') or exit;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $path = self::resolvePath();
            self::$pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            self::$pdo->exec('PRAGMA foreign_keys = ON');
            self::migrate(self::$pdo);
        }
        return self::$pdo;
    }

    /**
     * Caminho do arquivo do banco.
     *
     * Preferência: DB_PATH_OVERRIDE (config.local.php) apontando para fora da
     * raiz web. Sem override, usa data/ com um nome ALEATÓRIO gravado em
     * data/dbname.php — assim, mesmo num servidor que ignore .htaccess
     * (Nginx, Apache sem AllowOverride), o banco não é adivinhável por URL e
     * o marcador, por ser PHP com guarda, devolve página vazia se acessado.
     */
    private static function resolvePath(): string
    {
        if (defined('DB_PATH_OVERRIDE')) {
            $dir = dirname(DB_PATH_OVERRIDE);
            if (!is_dir($dir)) {
                mkdir($dir, 0770, true);
            }
            return DB_PATH_OVERRIDE;
        }

        $dir = BASE_PATH . '/data';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $marker = $dir . '/dbname.php';
        if (is_file($marker)) {
            $name = (string)require $marker;
        } else {
            $name = 'diario-' . bin2hex(random_bytes(16)) . '.sqlite';
            // Migra instalação antiga que usava nome fixo
            if (is_file($dir . '/diario.sqlite')) {
                rename($dir . '/diario.sqlite', $dir . '/' . $name);
            }
            $content = "<?php defined('APP_RUNNING') or exit; return " . var_export($name, true) . ";\n";
            if (file_put_contents($marker, $content, LOCK_EX) === false) {
                throw new \RuntimeException('Sem permissão de escrita em data/. Ajuste as permissões da pasta.');
            }
        }
        return $dir . '/' . $name;
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS activities (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT "",
            category TEXT NOT NULL DEFAULT "",
            date TEXT NOT NULL,
            prev_start TEXT,
            prev_end TEXT,
            created_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS phases (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            activity_id INTEGER NOT NULL REFERENCES activities(id) ON DELETE CASCADE,
            name TEXT NOT NULL,
            ord INTEGER NOT NULL DEFAULT 0,
            prev_start TEXT,
            prev_end TEXT,
            real_start TEXT,
            real_end TEXT
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip TEXT NOT NULL,
            attempted_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_activities_date ON activities(date)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_phases_activity ON phases(activity_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts(ip, attempted_at)');

        // Semeia a senha do administrador no primeiro acesso
        $stmt = $pdo->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (:k, :v)');
        $stmt->execute([':k' => 'admin_password_hash', ':v' => DEFAULT_ADMIN_PASSWORD_HASH]);
    }
}
