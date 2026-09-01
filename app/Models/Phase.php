<?php
declare(strict_types=1);

namespace App\Models;

defined('APP_RUNNING') or exit;

use App\Core\Database;

final class Phase
{
    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM phases WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Pausas da etapa, em ordem. */
    public static function pauses(int $phaseId): array
    {
        $stmt = Database::pdo()->prepare('SELECT id, start_dt, end_dt FROM phase_pauses WHERE phase_id = :p ORDER BY start_dt, id');
        $stmt->execute([':p' => $phaseId]);
        return $stmt->fetchAll();
    }

    /** Pausa em aberto (etapa pausada agora), se houver. */
    public static function openPause(int $phaseId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM phase_pauses WHERE phase_id = :p AND end_dt IS NULL ORDER BY id DESC LIMIT 1');
        $stmt->execute([':p' => $phaseId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function addPause(int $phaseId, string $start): int
    {
        Database::pdo()->prepare('INSERT INTO phase_pauses (phase_id, start_dt) VALUES (:p, :s)')
            ->execute([':p' => $phaseId, ':s' => $start]);
        return (int)Database::pdo()->lastInsertId();
    }

    public static function closePause(int $pauseId, string $end): void
    {
        Database::pdo()->prepare('UPDATE phase_pauses SET end_dt = :e WHERE id = :id')
            ->execute([':e' => $end, ':id' => $pauseId]);
    }

    public static function findPause(int $pauseId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM phase_pauses WHERE id = :id');
        $stmt->execute([':id' => $pauseId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function deletePause(int $pauseId): void
    {
        Database::pdo()->prepare('DELETE FROM phase_pauses WHERE id = :id')->execute([':id' => $pauseId]);
    }

    public static function deletePauses(int $phaseId): void
    {
        Database::pdo()->prepare('DELETE FROM phase_pauses WHERE phase_id = :p')->execute([':p' => $phaseId]);
    }

    /**
     * Encerra uma pausa em aberto no término real da etapa (ou a remove, se
     * começou depois dele). Evita que uma pausa esquecida deixe a madrugada
     * contar como tempo de trabalho.
     */
    public static function healOpenPause(int $phaseId, string $realEnd): void
    {
        $open = self::openPause($phaseId);
        if (!$open) {
            return;
        }
        if ($open['start_dt'] >= $realEnd) {
            Database::pdo()->prepare('DELETE FROM phase_pauses WHERE id = :id')->execute([':id' => (int)$open['id']]);
        } else {
            self::closePause((int)$open['id'], $realEnd);
        }
    }

    /** Atualiza os horários reais (null limpa o campo). */
    public static function setTimes(int $id, array $fields): void
    {
        $allowed = ['real_start', 'real_end'];
        $set = [];
        $params = [':id' => $id];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $fields)) {
                $set[] = "$f = :$f";
                $params[":$f"] = $fields[$f];
            }
        }
        if (!$set) {
            return;
        }
        $sql = 'UPDATE phases SET ' . implode(', ', $set) . ' WHERE id = :id';
        Database::pdo()->prepare($sql)->execute($params);
    }
}
