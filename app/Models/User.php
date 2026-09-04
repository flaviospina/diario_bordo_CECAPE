<?php
declare(strict_types=1);

namespace App\Models;

defined('APP_RUNNING') or exit;

use App\Core\Database;

final class User
{
    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Busca por usuário ativo (para login). */
    public static function findByUsername(string $username): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE username = :u AND active = 1');
        $stmt->execute([':u' => $username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function all(): array
    {
        return Database::pdo()
            ->query("SELECT * FROM users ORDER BY role = 'admin' DESC, name")
            ->fetchAll();
    }

    /** Usuários que registram apontamentos (admin e professores) ativos. */
    public static function professorCapable(): array
    {
        return Database::pdo()
            ->query("SELECT * FROM users WHERE role != 'gestor' AND active = 1 ORDER BY role = 'admin' DESC, name")
            ->fetchAll();
    }

    public static function create(string $username, string $name, string $rm, string $role,
                                  ?array $phases, string $passwordHash, array $director = []): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO users (username, name, rm, role, phases_json, password_hash,
                                director_name, director_unit, created_at)
             VALUES (:u, :n, :rm, :r, :p, :h, :dn, :du, :c)'
        );
        $stmt->execute([
            ':u' => $username,
            ':n' => $name,
            ':rm' => $rm,
            ':r' => $role,
            ':p' => $phases === null ? null : json_encode($phases, JSON_UNESCAPED_UNICODE),
            ':h' => $passwordHash,
            ':dn' => (string)($director['name'] ?? ''),
            ':du' => (string)($director['unit'] ?? ''),
            ':c' => date('Y-m-d H:i:s'),
        ]);
        return (int)Database::pdo()->lastInsertId();
    }

    /** Atualiza apenas os campos permitidos que forem informados. */
    public static function update(int $id, array $fields): void
    {
        $allowed = ['name', 'rm', 'phases_json', 'password_hash', 'active', 'director_name', 'director_unit'];
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
        Database::pdo()->prepare('UPDATE users SET ' . implode(', ', $set) . ' WHERE id = :id')
            ->execute($params);
    }

    /** Modelo de fases do usuário (ou o padrão do sistema). */
    public static function phases(array $user): array
    {
        $decoded = json_decode((string)($user['phases_json'] ?? ''), true);
        return (is_array($decoded) && $decoded) ? $decoded : DEFAULT_PHASES;
    }

    /**
     * Direção que responde pelo professor (assina os relatórios). Sem
     * cadastro próprio, vale a direção padrão do sistema — professores de
     * áreas diferentes podem responder a direções e lotações distintas.
     */
    public static function director(array $u): array
    {
        $name = trim((string)($u['director_name'] ?? ''));
        $unit = trim((string)($u['director_unit'] ?? ''));
        return [
            'name' => $name !== '' ? $name : DIRECTOR_NAME,
            'unit' => $unit !== '' ? $unit : DIRECTOR_UNIT,
        ];
    }

    /** Representação segura para o front-end (sem hash de senha). */
    public static function publicView(array $u): array
    {
        $director = self::director($u);
        return [
            'id' => (int)$u['id'],
            'username' => $u['username'],
            'name' => $u['name'],
            'rm' => $u['rm'],
            'role' => $u['role'],
            'active' => (int)$u['active'],
            'phases' => $u['role'] === 'gestor' ? null : self::phases($u),
            // Vazios significam "usa a direção padrão" (útil no formulário)
            'director_name' => (string)($u['director_name'] ?? ''),
            'director_unit' => (string)($u['director_unit'] ?? ''),
            'director' => $director['name'],
            'director_role' => 'Direção · ' . $director['unit'],
        ];
    }
}
