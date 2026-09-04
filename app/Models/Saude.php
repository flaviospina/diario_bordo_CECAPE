<?php
declare(strict_types=1);

namespace App\Models;

defined('APP_RUNNING') or exit;

use App\Core\Database;

/**
 * Registros de saúde, separados dos descansos:
 *  - saida: saída ao médico no dia (retorno opcional — vazio = não retornou);
 *  - afastamento: 1 ou mais dias inteiros (date .. end_date).
 * O atestado fica em data/atestados/ com nome aleatório, fora do alcance
 * do navegador; o download passa pela API autenticada.
 */
final class Saude
{
    public static function atestadoDir(): string
    {
        $dir = BASE_PATH . '/data/atestados';
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
        return $dir;
    }

    /** Registros do usuário que tocam o período (afastamentos incluem o intervalo). */
    public static function forUser(int $userId, ?string $from, ?string $to): array
    {
        $sql = 'SELECT * FROM medical_leaves WHERE user_id = :u';
        $params = [':u' => $userId];
        if ($to) {
            $sql .= ' AND date <= :to';
            $params[':to'] = $to;
        }
        if ($from) {
            $sql .= ' AND end_date >= :from';
            $params[':from'] = $from;
        }
        $sql .= ' ORDER BY date, start_time';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM medical_leaves WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(int $userId, array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO medical_leaves (user_id, type, date, end_date, start_time, end_time, note,
                                         certificate_file, certificate_name, created_at)
             VALUES (:u, :t, :d, :ed, :s, :e, :n, :cf, :cn, :c)'
        );
        $stmt->execute([
            ':u' => $userId,
            ':t' => $data['type'],
            ':d' => $data['date'],
            ':ed' => $data['end_date'],
            ':s' => $data['start_time'],
            ':e' => $data['end_time'],
            ':n' => $data['note'],
            ':cf' => $data['certificate_file'],
            ':cn' => $data['certificate_name'],
            ':c' => date('Y-m-d H:i:s'),
        ]);
        return (int)Database::pdo()->lastInsertId();
    }

    /**
     * Anexa (ou substitui) o atestado de um registro já existente — o
     * atestado costuma chegar às mãos só depois do atendimento médico.
     */
    public static function setCertificate(array $leave, ?string $file, ?string $name): void
    {
        if (!empty($leave['certificate_file']) && $leave['certificate_file'] !== $file) {
            $old = self::atestadoDir() . '/' . basename((string)$leave['certificate_file']);
            if (is_file($old)) {
                @unlink($old);
            }
        }
        Database::pdo()->prepare('UPDATE medical_leaves SET certificate_file = :f, certificate_name = :n WHERE id = :id')
            ->execute([':f' => $file, ':n' => $name, ':id' => (int)$leave['id']]);
    }

    /** Remove o registro e o arquivo do atestado, se houver. */
    public static function delete(array $leave): void
    {
        if (!empty($leave['certificate_file'])) {
            $path = self::atestadoDir() . '/' . basename((string)$leave['certificate_file']);
            if (is_file($path)) {
                @unlink($path);
            }
        }
        Database::pdo()->prepare('DELETE FROM medical_leaves WHERE id = :id')
            ->execute([':id' => (int)$leave['id']]);
    }
}
