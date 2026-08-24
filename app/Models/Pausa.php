<?php
declare(strict_types=1);

namespace App\Models;

defined('APP_RUNNING') or exit;

use App\Core\Database;

/** Descansos do dia de trabalho (almoço/janta). */
final class Pausa
{
    public static function forUser(int $userId, ?string $from, ?string $to): array
    {
        $sql = 'SELECT * FROM breaks WHERE user_id = :u';
        $params = [':u' => $userId];
        if ($from) {
            $sql .= ' AND date >= :from';
            $params[':from'] = $from;
        }
        if ($to) {
            $sql .= ' AND date <= :to';
            $params[':to'] = $to;
        }
        $sql .= ' ORDER BY date, start_time';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM breaks WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(int $userId, string $date, string $type, string $start, string $end): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO breaks (user_id, date, type, start_time, end_time) VALUES (:u, :d, :t, :s, :e)'
        );
        $stmt->execute([':u' => $userId, ':d' => $date, ':t' => $type, ':s' => $start, ':e' => $end]);
        return (int)Database::pdo()->lastInsertId();
    }

    public static function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM breaks WHERE id = :id')->execute([':id' => $id]);
    }
}
