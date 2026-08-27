<?php
declare(strict_types=1);

namespace App\Models;

defined('APP_RUNNING') or exit;

use App\Core\Database;

/** Jornada de trabalho semanal (entrada/saída por dia da semana; 0=domingo). */
final class Jornada
{
    public static function forUser(int $userId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM work_schedules WHERE user_id = :u ORDER BY weekday');
        $stmt->execute([':u' => $userId]);
        return $stmt->fetchAll();
    }

    public static function forDay(int $userId, int $weekday): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM work_schedules WHERE user_id = :u AND weekday = :w');
        $stmt->execute([':u' => $userId, ':w' => $weekday]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Substitui a semana inteira do usuário de uma vez. */
    public static function saveAll(int $userId, array $days): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM work_schedules WHERE user_id = :u')->execute([':u' => $userId]);
            $ins = $pdo->prepare('INSERT INTO work_schedules (user_id, weekday, enabled, start_time, end_time)
                                  VALUES (:u, :w, :e, :s, :f)');
            foreach ($days as $d) {
                $ins->execute([
                    ':u' => $userId,
                    ':w' => $d['weekday'],
                    ':e' => $d['enabled'],
                    ':s' => $d['start'],
                    ':f' => $d['end'],
                ]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
