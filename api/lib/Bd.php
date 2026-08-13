<?php
/**
 * Conexão PDO com MySQL/MariaDB.
 *
 * Toda consulta da aplicação usa prepared statements com placeholders. A
 * emulação de prepares fica DESLIGADA, de modo que os parâmetros são enviados
 * separadamente da instrução ao servidor — a defesa efetiva contra SQL
 * injection. Nenhum valor vindo do usuário é concatenado em SQL; quando algo
 * precisa ser dinâmico (ordenação, por exemplo), usa-se lista branca.
 */

declare(strict_types=1);

defined('DIARIO_APP') || exit('Acesso negado.');

final class Bd
{
    private static ?PDO $pdo = null;

    public static function conectar(array $cfg): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            (int) $cfg['porta'],
            $cfg['nome'],
            $cfg['charset'] ?? 'utf8mb4'
        );

        self::$pdo = new PDO($dsn, $cfg['usuario'], $cfg['senha'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Sem emulação: o servidor recebe instrução e dados separadamente.
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
            PDO::ATTR_PERSISTENT         => false,
        ]);

        // Modo estrito: valores fora de faixa viram erro em vez de truncamento
        // silencioso — evita gravar dado corrompido sem ninguém perceber.
        self::$pdo->exec(
            "SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'"
        );

        // Alinha o fuso da sessão do banco ao do PHP. Sem isso, NOW() e as
        // colunas DATETIME ficam em um fuso e o PHP em outro — o que produz
        // horários errados nos registros e em qualquer conta de tempo.
        $offset = (new DateTime('now', new DateTimeZone(date_default_timezone_get())))
            ->format('P');
        $st = self::$pdo->prepare('SET SESSION time_zone = ?');
        $st->execute([$offset]);

        return self::$pdo;
    }

    public static function pdo(): PDO
    {
        if (!self::$pdo instanceof PDO) {
            throw new RuntimeException('Banco de dados não inicializado.');
        }
        return self::$pdo;
    }

    /** Executa uma consulta preparada e devolve o statement. */
    public static function consultar(string $sql, array $parametros = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($parametros);
        return $st;
    }

    public static function primeiro(string $sql, array $parametros = []): ?array
    {
        $linha = self::consultar($sql, $parametros)->fetch();
        return $linha === false ? null : $linha;
    }

    public static function todos(string $sql, array $parametros = []): array
    {
        return self::consultar($sql, $parametros)->fetchAll();
    }

    public static function ultimoId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }
}
