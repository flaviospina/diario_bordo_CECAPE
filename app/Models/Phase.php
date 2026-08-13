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
