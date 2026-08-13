<?php
declare(strict_types=1);

namespace App\Models;

defined('APP_RUNNING') or exit;

use App\Core\Database;

final class Setting
{
    public static function get(string $key): ?string
    {
        $stmt = Database::pdo()->prepare('SELECT value FROM settings WHERE key = :k');
        $stmt->execute([':k' => $key]);
        $v = $stmt->fetchColumn();
        return $v === false ? null : (string)$v;
    }

    public static function set(string $key, string $value): void
    {
        $stmt = Database::pdo()->prepare('INSERT INTO settings (key, value) VALUES (:k, :v)
                                          ON CONFLICT(key) DO UPDATE SET value = :v');
        $stmt->execute([':k' => $key, ':v' => $value]);
    }
}
