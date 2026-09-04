<?php
declare(strict_types=1);

namespace App\Core;

defined('APP_RUNNING') or exit;

use PDO;

/**
 * Conexão com o banco de dados.
 *
 * Driver escolhido pela configuração (app/Config/config.local.php):
 *  - Com DB_MYSQL_HOST/NAME/USER definidos → MySQL (gerenciável no phpMyAdmin).
 *  - Sem eles → SQLite em data/ (padrão, zero configuração).
 *
 * Na primeira conexão MySQL com banco vazio, os dados de uma instalação
 * SQLite existente em data/ são importados automaticamente; o arquivo
 * .sqlite é então renomeado para .importado-<data> e vira backup.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::isMysql() ? self::connectMysql() : self::connectSqlite();
        }
        return self::$pdo;
    }

    public static function isMysql(): bool
    {
        return defined('DB_MYSQL_HOST') && defined('DB_MYSQL_NAME') && defined('DB_MYSQL_USER');
    }

    /* ================= MySQL ================= */

    private static function connectMysql(): PDO
    {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_MYSQL_HOST . ';dbname=' . DB_MYSQL_NAME . ';charset=utf8mb4',
                DB_MYSQL_USER,
                defined('DB_MYSQL_PASS') ? DB_MYSQL_PASS : '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (\PDOException $e) {
            error_log('[diario_bordo] Falha na conexão MySQL: ' . $e->getMessage());
            throw new \RuntimeException(
                'Não foi possível conectar ao MySQL — confira as credenciais em app/Config/config.local.php.'
            );
        }
        self::migrateMysql($pdo);
        return $pdo;
    }

    private static function migrateMysql(PDO $pdo): void
    {
        $suffix = ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $pdo->exec('CREATE TABLE IF NOT EXISTS `users` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(30) NOT NULL UNIQUE,
            `name` VARCHAR(200) NOT NULL,
            `rm` VARCHAR(30) NOT NULL DEFAULT \'\',
            `role` VARCHAR(20) NOT NULL DEFAULT \'professor\',
            `phases_json` TEXT NULL,
            `password_hash` VARCHAR(255) NOT NULL,
            `active` TINYINT NOT NULL DEFAULT 1,
            `director_name` VARCHAR(200) NOT NULL DEFAULT \'\',
            `director_unit` VARCHAR(200) NOT NULL DEFAULT \'\',
            `created_at` VARCHAR(19) NOT NULL' . $suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS `activities` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NULL,
            `title` VARCHAR(200) NOT NULL,
            `description` TEXT NULL,
            `category` VARCHAR(100) NOT NULL DEFAULT \'\',
            `date` VARCHAR(10) NOT NULL,
            `prev_start` VARCHAR(16) NULL,
            `prev_end` VARCHAR(16) NULL,
            `created_at` VARCHAR(19) NOT NULL,
            KEY `idx_activities_user_date` (`user_id`, `date`)' . $suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS `phases` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `activity_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(200) NOT NULL,
            `ord` INT NOT NULL DEFAULT 0,
            `prev_start` VARCHAR(16) NULL,
            `prev_end` VARCHAR(16) NULL,
            `real_start` VARCHAR(16) NULL,
            `real_end` VARCHAR(16) NULL,
            KEY `idx_phases_activity` (`activity_id`),
            CONSTRAINT `fk_phases_activity` FOREIGN KEY (`activity_id`)
                REFERENCES `activities` (`id`) ON DELETE CASCADE' . $suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS `phase_pauses` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `phase_id` INT UNSIGNED NOT NULL,
            `start_dt` VARCHAR(16) NOT NULL,
            `end_dt` VARCHAR(16) NULL,
            KEY `idx_phase_pauses` (`phase_id`),
            CONSTRAINT `fk_pauses_phase` FOREIGN KEY (`phase_id`)
                REFERENCES `phases` (`id`) ON DELETE CASCADE' . $suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS `breaks` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `date` VARCHAR(10) NOT NULL,
            `type` VARCHAR(20) NOT NULL,
            `start_time` VARCHAR(5) NOT NULL,
            `end_time` VARCHAR(5) NOT NULL,
            KEY `idx_breaks_user` (`user_id`, `date`)' . $suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS `login_attempts` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `ip` VARCHAR(45) NOT NULL,
            `attempted_at` VARCHAR(19) NOT NULL,
            KEY `idx_login_attempts_ip` (`ip`, `attempted_at`)' . $suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS `work_schedules` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `weekday` TINYINT NOT NULL,
            `enabled` TINYINT NOT NULL DEFAULT 1,
            `start_time` VARCHAR(5) NOT NULL,
            `end_time` VARCHAR(5) NOT NULL,
            UNIQUE KEY `uq_schedule` (`user_id`, `weekday`)' . $suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS `hour_bank` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `date` VARCHAR(10) NOT NULL,
            `minutes` INT NOT NULL,
            `note` VARCHAR(200) NOT NULL DEFAULT \'\',
            `created_at` VARCHAR(19) NOT NULL,
            UNIQUE KEY `uq_hour_bank` (`user_id`, `date`)' . $suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS `time_clock` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `date` VARCHAR(10) NOT NULL,
            `clock_in` VARCHAR(5) NOT NULL,
            `clock_out` VARCHAR(5) NULL,
            `auto_closed` TINYINT NOT NULL DEFAULT 0,
            `created_at` VARCHAR(19) NOT NULL,
            UNIQUE KEY `uq_time_clock` (`user_id`, `date`)' . $suffix);
        $pdo->exec('CREATE TABLE IF NOT EXISTS `medical_leaves` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `type` VARCHAR(20) NOT NULL,
            `date` VARCHAR(10) NOT NULL,
            `end_date` VARCHAR(10) NOT NULL,
            `start_time` VARCHAR(5) NULL,
            `end_time` VARCHAR(5) NULL,
            `note` VARCHAR(300) NOT NULL DEFAULT \'\',
            `certificate_file` VARCHAR(80) NULL,
            `certificate_name` VARCHAR(200) NULL,
            `created_at` VARCHAR(19) NOT NULL,
            KEY `idx_medical_user` (`user_id`, `date`, `end_date`)' . $suffix);

        // Instalação anterior: ganha as colunas da direção responsável
        $userCols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
                                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'")
                        ->fetchAll(\PDO::FETCH_COLUMN);
        foreach (['director_name', 'director_unit'] as $col) {
            if (!in_array($col, $userCols, true)) {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `$col` VARCHAR(200) NOT NULL DEFAULT ''");
            }
        }

        // Alarga a coluna type de instalações anteriores (para "saida_medica")
        $len = $pdo->query("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
                            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'breaks' AND COLUMN_NAME = 'type'")
                   ->fetchColumn();
        if ($len !== false && (int)$len < 20) {
            $pdo->exec('ALTER TABLE `breaks` MODIFY `type` VARCHAR(20) NOT NULL');
        }

        self::moveLegacyMedicalBreaks($pdo);

        // Banco vazio: importa a instalação SQLite existente ou semeia as contas
        if ((int)$pdo->query('SELECT COUNT(*) FROM `users`')->fetchColumn() === 0) {
            if (!self::importFromSqlite($pdo)) {
                self::seedUsers($pdo);
            }
        }
    }

    /**
     * Copia todos os dados de um banco SQLite existente em data/ para o MySQL
     * (preservando os IDs) e renomeia o arquivo importado como backup.
     */
    private static function importFromSqlite(PDO $mysql): bool
    {
        $dir = BASE_PATH . '/data';
        $file = null;
        $marker = $dir . '/dbname.php';
        if (is_file($marker)) {
            $name = (string)require $marker;
            if ($name !== '' && is_file("$dir/$name")) {
                $file = "$dir/$name";
            }
        }
        if ($file === null) {
            foreach (glob("$dir/*.sqlite") ?: [] as $f) {
                $file = $f;
                break;
            }
        }
        if ($file === null) {
            return false;
        }

        $lite = new PDO('sqlite:' . $file, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        // Garante que o SQLite está no formato mais recente antes de copiar
        self::migrateSqliteSchema($lite);

        $tables = [
            'users' => ['id', 'username', 'name', 'rm', 'role', 'phases_json', 'password_hash', 'active',
                        'director_name', 'director_unit', 'created_at'],
            'activities' => ['id', 'user_id', 'title', 'description', 'category', 'date', 'prev_start', 'prev_end', 'created_at'],
            'phases' => ['id', 'activity_id', 'name', 'ord', 'prev_start', 'prev_end', 'real_start', 'real_end'],
            'phase_pauses' => ['id', 'phase_id', 'start_dt', 'end_dt'],
            'breaks' => ['id', 'user_id', 'date', 'type', 'start_time', 'end_time'],
            'work_schedules' => ['id', 'user_id', 'weekday', 'enabled', 'start_time', 'end_time'],
            'hour_bank' => ['id', 'user_id', 'date', 'minutes', 'note', 'created_at'],
            'time_clock' => ['id', 'user_id', 'date', 'clock_in', 'clock_out', 'auto_closed', 'created_at'],
            'medical_leaves' => ['id', 'user_id', 'type', 'date', 'end_date', 'start_time', 'end_time',
                                 'note', 'certificate_file', 'certificate_name', 'created_at'],
        ];
        $mysql->beginTransaction();
        try {
            foreach ($tables as $table => $cols) {
                $rows = $lite->query('SELECT ' . implode(', ', $cols) . " FROM $table")->fetchAll();
                if (!$rows) {
                    continue;
                }
                $sql = 'INSERT INTO `' . $table . '` (`' . implode('`, `', $cols) . '`) VALUES ('
                     . implode(', ', array_fill(0, count($cols), '?')) . ')';
                $ins = $mysql->prepare($sql);
                foreach ($rows as $r) {
                    $ins->execute(array_map(fn($c) => $r[$c], $cols));
                }
            }
            $mysql->commit();
        } catch (\Throwable $e) {
            $mysql->rollBack();
            throw $e;
        }

        // O SQLite vira backup e não é importado de novo
        $lite = null;
        @rename($file, $file . '.importado-' . date('Ymd-His'));
        if (is_file($marker)) {
            @rename($marker, $marker . '.importado');
        }
        error_log('[diario_bordo] Dados do SQLite importados para o MySQL com sucesso.');
        return true;
    }

    /* ================= SQLite ================= */

    private static function connectSqlite(): PDO
    {
        $pdo = new PDO('sqlite:' . self::sqlitePath(), null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        self::migrateSqliteSchema($pdo);
        if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
            self::seedUsers($pdo, self::legacySqliteHash($pdo));
        }
        // Atividades antigas sem dono passam a pertencer ao admin
        $adminId = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")->fetchColumn();
        if ($adminId) {
            $pdo->prepare('UPDATE activities SET user_id = :a WHERE user_id IS NULL')
                ->execute([':a' => (int)$adminId]);
        }
        return $pdo;
    }

    /**
     * Caminho do arquivo SQLite: DB_PATH_OVERRIDE (config.local.php) ou
     * data/ com nome aleatório gravado em data/dbname.php — não adivinhável
     * por URL mesmo em servidores que ignorem .htaccess.
     */
    private static function sqlitePath(): string
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

    /** Cria/atualiza o esquema SQLite (usado na conexão e na importação). */
    private static function migrateSqliteSchema(PDO $pdo): void
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
            director_name TEXT NOT NULL DEFAULT "",
            director_unit TEXT NOT NULL DEFAULT "",
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
        $pdo->exec('CREATE TABLE IF NOT EXISTS phase_pauses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            phase_id INTEGER NOT NULL REFERENCES phases(id) ON DELETE CASCADE,
            start_dt TEXT NOT NULL,
            end_dt TEXT
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
        $pdo->exec('CREATE TABLE IF NOT EXISTS work_schedules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            weekday INTEGER NOT NULL,
            enabled INTEGER NOT NULL DEFAULT 1,
            start_time TEXT NOT NULL,
            end_time TEXT NOT NULL,
            UNIQUE(user_id, weekday)
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS hour_bank (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            date TEXT NOT NULL,
            minutes INTEGER NOT NULL,
            note TEXT NOT NULL DEFAULT "",
            created_at TEXT NOT NULL,
            UNIQUE(user_id, date)
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS time_clock (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            date TEXT NOT NULL,
            clock_in TEXT NOT NULL,
            clock_out TEXT,
            auto_closed INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            UNIQUE(user_id, date)
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS medical_leaves (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            type TEXT NOT NULL,
            date TEXT NOT NULL,
            end_date TEXT NOT NULL,
            start_time TEXT,
            end_time TEXT,
            note TEXT NOT NULL DEFAULT "",
            certificate_file TEXT,
            certificate_name TEXT,
            created_at TEXT NOT NULL
        )');

        // Banco antigo (single-user): ganha a coluna user_id antes dos índices
        $cols = array_column($pdo->query('PRAGMA table_info(activities)')->fetchAll(), 'name');
        if (!in_array('user_id', $cols, true)) {
            $pdo->exec('ALTER TABLE activities ADD COLUMN user_id INTEGER');
        }

        // Instalação anterior: ganha as colunas da direção responsável
        $userCols = array_column($pdo->query('PRAGMA table_info(users)')->fetchAll(), 'name');
        foreach (['director_name', 'director_unit'] as $col) {
            if (!in_array($col, $userCols, true)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN $col TEXT NOT NULL DEFAULT \"\"");
            }
        }

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_activities_user_date ON activities(user_id, date)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_phases_activity ON phases(activity_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_breaks_user ON breaks(user_id, date)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_phase_pauses ON phase_pauses(phase_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts(ip, attempted_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_medical_user ON medical_leaves(user_id, date, end_date)');

        self::moveLegacyMedicalBreaks($pdo);

        // Instalação antiga sem usuários: semeia herdando a senha legada
        if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
            self::seedUsers($pdo, self::legacySqliteHash($pdo));
        }
        $adminId = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")->fetchColumn();
        if ($adminId) {
            $pdo->prepare('UPDATE activities SET user_id = :a WHERE user_id IS NULL')
                ->execute([':a' => (int)$adminId]);
        }
    }

    /* ================= Comum ================= */

    /**
     * Migra saídas médicas registradas como "descanso" em versões anteriores
     * para a tabela própria de registros de saúde.
     */
    private static function moveLegacyMedicalBreaks(PDO $pdo): void
    {
        $rows = $pdo->query("SELECT * FROM breaks WHERE type = 'saida_medica'")->fetchAll();
        if (!$rows) {
            return;
        }
        $ins = $pdo->prepare('INSERT INTO medical_leaves (user_id, type, date, end_date, start_time, end_time, note, created_at)
                              VALUES (:u, :t, :d, :ed, :s, :e, :n, :c)');
        foreach ($rows as $r) {
            $ins->execute([
                ':u' => $r['user_id'],
                ':t' => 'saida',
                ':d' => $r['date'],
                ':ed' => $r['date'],
                ':s' => $r['start_time'],
                ':e' => $r['end_time'],
                ':n' => '',
                ':c' => date('Y-m-d H:i:s'),
            ]);
        }
        $pdo->exec("DELETE FROM breaks WHERE type = 'saida_medica'");
    }

    /** Senha do admin gravada por versões antigas (single-user) do sistema. */
    private static function legacySqliteHash(PDO $sqlite): ?string
    {
        try {
            $v = $sqlite->query("SELECT value FROM settings WHERE key = 'admin_password_hash'")->fetchColumn();
            return $v === false ? null : (string)$v;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Cria as contas iniciais (admin herda a senha legada, se existir). */
    private static function seedUsers(PDO $pdo, ?string $legacyAdminHash = null): void
    {
        $ins = $pdo->prepare('INSERT INTO users (username, name, rm, role, phases_json, password_hash, created_at)
                              VALUES (:u, :n, \'\', :r, :p, :h, :c)');
        foreach (SEED_USERS as $seed) {
            $isAdmin = $seed['role'] === 'admin';
            $ins->execute([
                ':u' => $seed['username'],
                ':n' => $seed['name'],
                ':r' => $seed['role'],
                ':p' => $seed['role'] === 'gestor' ? null : json_encode(DEFAULT_PHASES, JSON_UNESCAPED_UNICODE),
                ':h' => ($isAdmin && $legacyAdminHash) ? $legacyAdminHash : DEFAULT_ADMIN_PASSWORD_HASH,
                ':c' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
