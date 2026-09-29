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
     * aberto, no fim da jornada semanal do professor naquele dia. O ponto
     * nunca fecha antes do último registro real do dia (início, término ou
     * pausa de etapa): se o professor passou do horário, a saída fica no
     * último registro e a administração corrige depois, se for o caso.
     * Sem jornada definida, vale só o último registro (ou a própria entrada).
     * As etapas ainda em andamento são pausadas no horário de encerramento.
     */
    public static function autoCloseOpen(int $userId): void
    {
        $today = date('Y-m-d');
        $stmt = Database::pdo()->prepare('SELECT * FROM time_clock WHERE user_id = :u AND clock_out IS NULL AND date < :d ORDER BY date');
        $stmt->execute([':u' => $userId, ':d' => $today]);
        foreach ($stmt->fetchAll() as $rec) {
            $date = (string)$rec['date'];
            $in = (string)$rec['clock_in'];
            $out = $in;
            $sched = Jornada::forDay($userId, (int)date('w', (int)strtotime($date)));
            if ($sched && (int)$sched['enabled'] === 1 && substr((string)$sched['end_time'], 0, 5) > $out) {
                $out = substr((string)$sched['end_time'], 0, 5);
            }
            $last = self::lastRecordTime($userId, $date);
            if ($last !== null && $last > $out) {
                $out = $last;
            }
            self::close((int)$rec['id'], $out, 1);
            // Etapas deixadas em andamento param de contar no encerramento
            Phase::pauseOpenOfUser($userId, "$date $out");
        }
    }

    /** Último horário (HH:MM) apontado no dia: início/término de etapa ou pausa. */
    private static function lastRecordTime(int $userId, string $date): ?string
    {
        $q = Database::pdo()->prepare(
            'SELECT MAX(t) FROM (
                SELECT p.real_start AS t FROM phases p JOIN activities a ON a.id = p.activity_id
                 WHERE a.user_id = :u1 AND p.real_start LIKE :d1
                UNION ALL
                SELECT p.real_end FROM phases p JOIN activities a ON a.id = p.activity_id
                 WHERE a.user_id = :u2 AND p.real_end LIKE :d2
                UNION ALL
                SELECT z.start_dt FROM phase_pauses z JOIN phases p ON p.id = z.phase_id
                 JOIN activities a ON a.id = p.activity_id
                 WHERE a.user_id = :u3 AND z.start_dt LIKE :d3
                UNION ALL
                SELECT z.end_dt FROM phase_pauses z JOIN phases p ON p.id = z.phase_id
                 JOIN activities a ON a.id = p.activity_id
                 WHERE a.user_id = :u4 AND z.end_dt LIKE :d4
             ) x'
        );
        $like = $date . '%';
        $q->execute([':u1' => $userId, ':d1' => $like, ':u2' => $userId, ':d2' => $like,
                     ':u3' => $userId, ':d3' => $like, ':u4' => $userId, ':d4' => $like]);
        $v = (string)($q->fetchColumn() ?: '');
        return $v !== '' ? substr($v, 11, 5) : null;
    }
}
