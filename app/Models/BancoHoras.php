<?php
declare(strict_types=1);

namespace App\Models;

defined('APP_RUNNING') or exit;

use App\Core\Database;

/** Banco de horas: créditos de trabalho além do fim da jornada (um por dia). */
final class BancoHoras
{
    public static function forUser(int $userId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM hour_bank WHERE user_id = :u ORDER BY date DESC, id DESC');
        $stmt->execute([':u' => $userId]);
        return $stmt->fetchAll();
    }

    public static function totalMinutes(int $userId): int
    {
        $stmt = Database::pdo()->prepare('SELECT COALESCE(SUM(minutes), 0) FROM hour_bank WHERE user_id = :u');
        $stmt->execute([':u' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM hour_bank WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Um registro por usuário/dia: registrar de novo atualiza os minutos. */
    public static function upsert(int $userId, string $date, int $minutes, string $note): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id FROM hour_bank WHERE user_id = :u AND date = :d');
        $stmt->execute([':u' => $userId, ':d' => $date]);
        $id = $stmt->fetchColumn();
        if ($id) {
            $pdo->prepare('UPDATE hour_bank SET minutes = :m, note = :n, created_at = :c WHERE id = :id')
                ->execute([':m' => $minutes, ':n' => $note, ':c' => date('Y-m-d H:i:s'), ':id' => (int)$id]);
        } else {
            $pdo->prepare('INSERT INTO hour_bank (user_id, date, minutes, note, created_at)
                           VALUES (:u, :d, :m, :n, :c)')
                ->execute([':u' => $userId, ':d' => $date, ':m' => $minutes, ':n' => $note,
                           ':c' => date('Y-m-d H:i:s')]);
        }
    }

    public static function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM hour_bank WHERE id = :id')->execute([':id' => $id]);
    }
}
