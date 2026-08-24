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
        $pdo->exec('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            rm TEXT NOT NULL DEFAULT "",
            role TEXT NOT NULL DEFAULT "professor",
            phases_json TEXT,
            password_hash TEXT NOT NULL,
            active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS activities (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
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
        $pdo->exec('CREATE TABLE IF NOT EXISTS breaks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            date TEXT NOT NULL,
            type TEXT NOT NULL,
            start_time TEXT NOT NULL,
            end_time TEXT NOT NULL
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
        // Migração de banco antigo (single-user): adiciona a coluna user_id
        // antes dos índices que dependem dela
        $cols = array_column($pdo->query('PRAGMA table_info(activities)')->fetchAll(), 'name');
        if (!in_array('user_id', $cols, true)) {
            $pdo->exec('ALTER TABLE activities ADD COLUMN user_id INTEGER');
        }

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_activities_user_date ON activities(user_id, date)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_phases_activity ON phases(activity_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_breaks_user ON breaks(user_id, date)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts(ip, attempted_at)');

        // Semeia as contas iniciais no primeiro acesso
        if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
            // O admin herda a senha já cadastrada no banco antigo, se houver
            $legacy = $pdo->query('SELECT value FROM settings WHERE key = "admin_password_hash"')->fetchColumn();
            $ins = $pdo->prepare('INSERT INTO users (username, name, rm, role, phases_json, password_hash, created_at)
                                  VALUES (:u, :n, "", :r, :p, :h, :c)');
            foreach (SEED_USERS as $seed) {
                $isAdmin = $seed['role'] === 'admin';
                $ins->execute([
                    ':u' => $seed['username'],
                    ':n' => $seed['name'],
                    ':r' => $seed['role'],
                    ':p' => $seed['role'] === 'gestor' ? null : json_encode(DEFAULT_PHASES, JSON_UNESCAPED_UNICODE),
                    ':h' => ($isAdmin && $legacy) ? (string)$legacy : DEFAULT_ADMIN_PASSWORD_HASH,
                    ':c' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        // Atividades antigas sem dono passam a pertencer ao admin
        $adminId = $pdo->query('SELECT id FROM users WHERE role = "admin" ORDER BY id LIMIT 1')->fetchColumn();
        if ($adminId) {
            $pdo->prepare('UPDATE activities SET user_id = :a WHERE user_id IS NULL')
                ->execute([':a' => (int)$adminId]);
        }
    }
}
