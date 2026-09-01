<?php
declare(strict_types=1);

namespace App\Models;

defined('APP_RUNNING') or exit;

use App\Core\Database;

/**
 * Registro de ponto (folha do RH): um registro por dia com a entrada
 * ("Iniciar jornada") e a saída ("Encerrar jornada") do professor.
 * Um ponto esquecido em aberto é encerrado automaticamente no horário
 * de saída da jornada semanal definida pelo próprio professor.
 */
final class Ponto
{
    public static function forUser(int $userId, ?string $from, ?string $to): array
    {
        $sql = 'SELECT * FROM time_clock WHERE user_id = :u';
        $params = [':u' => $userId];
        if ($from) {
            $sql .= ' AND date >= :from';
            $params[':from'] = $from;
        }
        if ($to) {
            $sql .= ' AND date <= :to';
            $params[':to'] = $to;
        }
        $stmt = Database::pdo()->prepare($sql . ' ORDER BY date DESC');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function forDay(int $userId, string $date): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM time_clock WHERE user_id = :u AND date = :d');
        $stmt->execute([':u' => $userId, ':d' => $date]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM time_clock WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(int $userId, string $date, string $in, ?string $out = null, int $auto = 0): int
    {
        Database::pdo()->prepare('INSERT INTO time_clock (user_id, date, clock_in, clock_out, auto_closed, created_at)
                                  VALUES (:u, :d, :i, :o, :a, :c)')
            ->execute([':u' => $userId, ':d' => $date, ':i' => $in, ':o' => $out,
                       ':a' => $auto, ':c' => date('Y-m-d H:i:s')]);
        return (int)Database::pdo()->lastInsertId();
    }

    /** Corrige a entrada/saída de um registro (saída null = jornada em aberto). */
    public static function setTimes(int $id, string $in, ?string $out, int $auto = 0): void
    {
        Database::pdo()->prepare('UPDATE time_clock SET clock_in = :i, clock_out = :o, auto_closed = :a WHERE id = :id')
            ->execute([':i' => $in, ':o' => $out, ':a' => $auto, ':id' => $id]);
    }

    public static function close(int $id, string $out, int $auto = 0): void
    {
        Database::pdo()->prepare('UPDATE time_clock SET clock_out = :o, auto_closed = :a WHERE id = :id')
            ->execute([':o' => $out, ':a' => $auto, ':id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM time_clock WHERE id = :id')->execute([':id' => $id]);
    }

    /**
     * Encerra automaticamente os pontos de dias anteriores esquecidos em
     * aberto, usando o fim da jornada semanal do professor naquele dia.
     * Sem jornada definida (ou entrada após o fim dela), usa o último
     * término real apontado no dia — e, sem apontamentos, a própria entrada.
     */
    public static function autoCloseOpen(int $userId): void
    {
        $today = date('Y-m-d');
        $stmt = Database::pdo()->prepare('SELECT * FROM time_clock WHERE user_id = :u AND clock_out IS NULL AND date < :d');
        $stmt->execute([':u' => $userId, ':d' => $today]);
        foreach ($stmt->fetchAll() as $rec) {
            $date = (string)$rec['date'];
            $in = (string)$rec['clock_in'];
            $out = null;
            $sched = Jornada::forDay($userId, (int)date('w', (int)strtotime($date)));
            if ($sched && (int)$sched['enabled'] === 1 && substr((string)$sched['end_time'], 0, 5) > $in) {
                $out = substr((string)$sched['end_time'], 0, 5);
            } else {
                $q = Database::pdo()->prepare(
                    'SELECT MAX(p.real_end) FROM phases p
                     JOIN activities a ON a.id = p.activity_id
                     WHERE a.user_id = :u AND p.real_end LIKE :d'
                );
                $q->execute([':u' => $userId, ':d' => $date . '%']);
                $lastEnd = (string)($q->fetchColumn() ?: '');
                $out = ($lastEnd !== '' && substr($lastEnd, 11, 5) > $in) ? substr($lastEnd, 11, 5) : $in;
            }
            self::close((int)$rec['id'], $out, 1);
        }
    }
}
