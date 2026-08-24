<?php
declare(strict_types=1);

namespace App\Models;

defined('APP_RUNNING') or exit;

use App\Core\Database;
use DateTime;

final class Activity
{
    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM activities WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Lista as atividades de um professor (com fases e status derivado). */
    public static function allWithPhases(int $userId, ?string $from, ?string $to, ?string $q): array
    {
        $sql = 'SELECT * FROM activities WHERE user_id = :uid';
        $params = [':uid' => $userId];
        if ($from) {
            $sql .= ' AND date >= :from';
            $params[':from'] = $from;
        }
        if ($to) {
            $sql .= ' AND date <= :to';
            $params[':to'] = $to;
        }
        if ($q) {
            $sql .= ' AND (title LIKE :q OR description LIKE :q OR category LIKE :q)';
            $params[':q'] = '%' . addcslashes($q, '%_\\') . '%';
        }
        $sql .= ' ORDER BY date DESC, prev_start ASC, id ASC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $activities = $stmt->fetchAll();

        $phStmt = Database::pdo()->prepare('SELECT * FROM phases WHERE activity_id = :id ORDER BY ord, id');
        foreach ($activities as &$a) {
            $phStmt->execute([':id' => $a['id']]);
            $a['phases'] = $phStmt->fetchAll();
            $a['status'] = self::deriveStatus($a['phases']);
            $starts = array_filter(array_column($a['phases'], 'real_start'));
            $ends = array_column($a['phases'], 'real_end');
            $a['real_start'] = $starts ? min($starts) : null;
            $a['real_end'] = ($ends && !in_array(null, $ends, true) && !in_array('', $ends, true)) ? max($ends) : null;
        }
        return $activities;
    }

    /** Cria a atividade e suas fases com a previsão já calculada. */
    public static function create(int $userId, array $meta, array $phasePreviews): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO activities (user_id, title, description, category, date, prev_start, prev_end, created_at)
                                   VALUES (:u, :t, :d, :c, :dt, :ps, :pe, :ca)');
            $stmt->execute([
                ':u' => $userId,
                ':t' => $meta['title'],
                ':d' => $meta['description'],
                ':c' => $meta['category'],
                ':dt' => $meta['date'],
                ':ps' => $phasePreviews[0]['prev_start'],
                ':pe' => $phasePreviews[count($phasePreviews) - 1]['prev_end'],
                ':ca' => date('Y-m-d H:i:s'),
            ]);
            $id = (int)$pdo->lastInsertId();
            $ph = $pdo->prepare('INSERT INTO phases (activity_id, name, ord, prev_start, prev_end)
                                 VALUES (:a, :n, :o, :ps, :pe)');
            foreach ($phasePreviews as $p) {
                $ph->execute([':a' => $id, ':n' => $p['name'], ':o' => $p['ord'],
                              ':ps' => $p['prev_start'], ':pe' => $p['prev_end']]);
            }
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function updateMeta(int $id, string $title, string $description, string $category): void
    {
        $stmt = Database::pdo()->prepare('UPDATE activities SET title = :t, description = :d, category = :c WHERE id = :id');
        $stmt->execute([':t' => $title, ':d' => $description, ':c' => $category, ':id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM activities WHERE id = :id')->execute([':id' => $id]);
    }

    /**
     * Gera a previsão de cada etapa distribuindo a duração total pelos pesos,
     * em sequência a partir do horário de início informado.
     * Os descansos registrados no dia (almoço/janta) são janelas bloqueadas:
     * a agenda pula esses intervalos — nenhuma etapa é prevista dentro deles.
     */
    public static function buildPhasePreviews(string $date, string $startTime, int $durationMin,
                                              array $phases, array $breaks = []): array
    {
        $totalWeight = 0;
        foreach ($phases as $p) {
            $totalWeight += max(0, (float)($p['weight'] ?? 0));
        }
        if ($totalWeight <= 0) {
            foreach ($phases as &$p) {
                $p['weight'] = 1;
            }
            unset($p);
            $totalWeight = count($phases);
        }

        // Janelas de descanso do dia, em ordem
        $windows = [];
        foreach ($breaks as $b) {
            if (($b['date'] ?? '') === $date) {
                $windows[] = [new DateTime("$date {$b['start_time']}"), new DateTime("$date {$b['end_time']}")];
            }
        }
        usort($windows, fn($a, $b) => $a[0] <=> $b[0]);

        // Move o instante para fora de qualquer descanso
        $skip = function (DateTime $t) use ($windows): DateTime {
            $moved = true;
            while ($moved) {
                $moved = false;
                foreach ($windows as [$s, $e]) {
                    if ($t >= $s && $t < $e) {
                        $t = clone $e;
                        $moved = true;
                    }
                }
            }
            return $t;
        };
        // Avança N minutos de trabalho, pulando os descansos
        $advance = function (DateTime $t, int $mins) use ($windows, $skip): DateTime {
            $t = $skip(clone $t);
            $rem = $mins;
            while ($rem > 0) {
                $next = null;
                foreach ($windows as [$s]) {
                    if ($s > $t && ($next === null || $s < $next)) {
                        $next = $s;
                    }
                }
                if ($next === null) {
                    $t->modify("+{$rem} minutes");
                    $rem = 0;
                } else {
                    $chunk = min($rem, intdiv($next->getTimestamp() - $t->getTimestamp(), 60));
                    $t->modify("+{$chunk} minutes");
                    $rem -= $chunk;
                    $t = $skip($t);
                }
            }
            return $t;
        };

        $cursor = new DateTime("$date $startTime");
        $out = [];
        $n = count($phases);
        $used = 0;
        foreach (array_values($phases) as $i => $p) {
            // A última etapa recebe o restante para fechar exatamente a duração total
            $mins = ($i === $n - 1)
                ? $durationMin - $used
                : (int)round($durationMin * ((float)$p['weight'] / $totalWeight));
            $mins = max(1, $mins);
            $used += $mins;
            $start = $skip(clone $cursor);
            $end = $advance(clone $start, $mins);
            $cursor = clone $end;
            $out[] = [
                'name' => trim((string)$p['name']),
                'ord' => $i,
                'prev_start' => $start->format('Y-m-d H:i'),
                'prev_end' => $end->format('Y-m-d H:i'),
            ];
        }
        return $out;
    }

    private static function deriveStatus(array $phases): string
    {
        if (!$phases) {
            return 'prevista';
        }
        $allDone = true;
        $anyStarted = false;
        foreach ($phases as $p) {
            if (!empty($p['real_start'])) {
                $anyStarted = true;
            }
            if (empty($p['real_end'])) {
                $allDone = false;
            }
        }
        if ($allDone) {
            return 'concluida';
        }
        return $anyStarted ? 'em_andamento' : 'prevista';
    }
}
