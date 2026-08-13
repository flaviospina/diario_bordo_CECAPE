<?php
declare(strict_types=1);

namespace App\Models;

defined('APP_RUNNING') or exit;

use App\Core\Database;

/** Controle de tentativas de login para bloqueio de força bruta. */
final class LoginAttempt
{
    public static function isLocked(string $ip): bool
    {
        $since = date('Y-m-d H:i:s', time() - LOGIN_WINDOW_MINUTES * 60);
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = :ip AND attempted_at >= :since'
        );
        $stmt->execute([':ip' => $ip, ':since' => $since]);
        return (int)$stmt->fetchColumn() >= LOGIN_MAX_ATTEMPTS;
    }

    public static function recordFailure(string $ip): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO login_attempts (ip, attempted_at) VALUES (:ip, :at)')
            ->execute([':ip' => $ip, ':at' => date('Y-m-d H:i:s')]);
        // Remove registros antigos para a tabela não crescer indefinidamente
        $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < :old')
            ->execute([':old' => date('Y-m-d H:i:s', time() - LOGIN_WINDOW_MINUTES * 60)]);
    }

    public static function clear(string $ip): void
    {
        Database::pdo()->prepare('DELETE FROM login_attempts WHERE ip = :ip')->execute([':ip' => $ip]);
    }
}
