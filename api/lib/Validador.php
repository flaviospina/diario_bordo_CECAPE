<?php
/**
 * Validação e normalização de entrada.
 *
 * Regra da casa: nenhum dado do cliente chega ao banco sem passar por aqui.
 * Tudo é validado por tipo, faixa e formato, e campos desconhecidos são
 * simplesmente ignorados (lista branca, nunca lista negra).
 */

declare(strict_types=1);

defined('DIARIO_APP') || exit('Acesso negado.');

final class Validador
{
    /** Texto livre já normalizado; remove caracteres de controle. */
    public static function texto(
        mixed $valor,
        string $campo,
        int $maximo = 255,
        bool $obrigatorio = false
    ): string {
        if (is_int($valor) || is_float($valor)) {
            $valor = (string) $valor;
        }
        if (!is_string($valor)) {
            $valor = '';
        }

        $valor = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $valor) ?? '');
        // Normaliza para evitar homoglifos combinados em nomes de usuário etc.
        if (class_exists('Normalizer')) {
            $valor = Normalizer::normalize($valor, Normalizer::FORM_C) ?: $valor;
        }

        if ($obrigatorio && $valor === '') {
            throw new ErroApi("O campo \"{$campo}\" é obrigatório.", 422, [$campo => 'obrigatório']);
        }
        if (mb_strlen($valor) > $maximo) {
            throw new ErroApi(
                "O campo \"{$campo}\" excede {$maximo} caracteres.",
                422,
                [$campo => 'tamanho']
            );
        }

        return $valor;
    }

    public static function inteiro(mixed $valor, string $campo, int $min, int $max, int $padrao = 0): int
    {
        if ($valor === null || $valor === '') {
            return $padrao;
        }
        if (!is_numeric($valor)) {
            throw new ErroApi("O campo \"{$campo}\" deve ser numérico.", 422, [$campo => 'tipo']);
        }
        $n = (int) $valor;
        if ($n < $min || $n > $max) {
            throw new ErroApi(
                "O campo \"{$campo}\" deve estar entre {$min} e {$max}.",
                422,
                [$campo => 'faixa']
            );
        }
        return $n;
    }

    public static function decimal(mixed $valor, string $campo, float $min, float $max): float
    {
        if ($valor === null || $valor === '') {
            return 0.0;
        }
        if (!is_numeric($valor)) {
            throw new ErroApi("O campo \"{$campo}\" deve ser numérico.", 422, [$campo => 'tipo']);
        }
        $n = round((float) $valor, 2);
        if ($n < $min || $n > $max) {
            throw new ErroApi("O campo \"{$campo}\" está fora da faixa permitida.", 422, [$campo => 'faixa']);
        }
        return $n;
    }

    public static function booleano(mixed $valor): bool
    {
        return filter_var($valor, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
    }

    /**
     * Aceita "AAAA-MM-DDTHH:MM" (input datetime-local) ou "AAAA-MM-DD HH:MM[:SS]"
     * e devolve o formato do MySQL. Devolve null quando vazio.
     */
    public static function dataHora(mixed $valor, string $campo, bool $obrigatorio = false): ?string
    {
        if (!is_string($valor) || trim($valor) === '') {
            if ($obrigatorio) {
                throw new ErroApi("O campo \"{$campo}\" é obrigatório.", 422, [$campo => 'obrigatório']);
            }
            return null;
        }

        $valor = trim($valor);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(?::(\d{2}))?$/', $valor, $m)) {
            throw new ErroApi("O campo \"{$campo}\" não é uma data/hora válida.", 422, [$campo => 'formato']);
        }

        [$ano, $mes, $dia, $hora, $min] = [(int) $m[1], (int) $m[2], (int) $m[3], (int) $m[4], (int) $m[5]];
        $seg = isset($m[6]) ? (int) $m[6] : 0;

        if (!checkdate($mes, $dia, $ano) || $ano < 1970 || $ano > 2200
            || $hora > 23 || $min > 59 || $seg > 59) {
            throw new ErroApi("O campo \"{$campo}\" não é uma data/hora válida.", 422, [$campo => 'formato']);
        }

        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $ano, $mes, $dia, $hora, $min, $seg);
    }

    /** Data simples "AAAA-MM-DD" usada nos filtros. */
    public static function data(mixed $valor, string $campo): ?string
    {
        if (!is_string($valor) || trim($valor) === '') {
            return null;
        }
        $valor = trim($valor);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $m)
            || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw new ErroApi("O campo \"{$campo}\" não é uma data válida.", 422, [$campo => 'formato']);
        }
        return $valor;
    }

    /** Lista branca — usada também para colunas de ordenação. */
    public static function opcao(mixed $valor, string $campo, array $permitidos, ?string $padrao = null): ?string
    {
        if ($valor === null || $valor === '') {
            return $padrao;
        }
        if (!is_string($valor) || !in_array($valor, $permitidos, true)) {
            throw new ErroApi("Valor inválido para \"{$campo}\".", 422, [$campo => 'opcao']);
        }
        return $valor;
    }

    public static function email(mixed $valor, string $campo, bool $obrigatorio = true): string
    {
        $valor = self::texto($valor, $campo, 160, $obrigatorio);
        if ($valor === '') {
            return '';
        }
        $valor = mb_strtolower($valor);
        if (!filter_var($valor, FILTER_VALIDATE_EMAIL)) {
            throw new ErroApi('Informe um e-mail válido.', 422, [$campo => 'formato']);
        }
        return $valor;
    }

    /** Nome de usuário: apenas letras, números, ponto, hífen e sublinhado. */
    public static function nomeUsuario(mixed $valor): string
    {
        $valor = mb_strtolower(self::texto($valor, 'usuário', 60, true));
        if (!preg_match('/^[a-z0-9._-]{3,60}$/', $valor)) {
            throw new ErroApi(
                'O nome de usuário deve ter de 3 a 60 caracteres, usando apenas letras, números, ponto, hífen e sublinhado.',
                422,
                ['usuario' => 'formato']
            );
        }
        return $valor;
    }

    /**
     * Política de senha. Evita as senhas óbvias e as que repetem os dados
     * da própria conta.
     */
    public static function senha(mixed $valor, string ...$dadosDaConta): string
    {
        if (!is_string($valor)) {
            $valor = '';
        }
        if (mb_strlen($valor) < 10) {
            throw new ErroApi('A senha deve ter ao menos 10 caracteres.', 422, ['senha' => 'curta']);
        }
        if (mb_strlen($valor) > 200) {
            throw new ErroApi('A senha é longa demais.', 422, ['senha' => 'longa']);
        }
        if (!preg_match('/[a-zA-ZÀ-ÿ]/u', $valor) || !preg_match('/\d/', $valor)) {
            throw new ErroApi('A senha deve conter letras e números.', 422, ['senha' => 'fraca']);
        }

        $comparavel = mb_strtolower($valor);
        foreach ($dadosDaConta as $dado) {
            $dado = mb_strtolower(trim($dado));
            if ($dado !== '' && mb_strlen($dado) >= 3 && str_contains($comparavel, $dado)) {
                throw new ErroApi(
                    'A senha não pode conter seu nome de usuário, e-mail ou nome.',
                    422,
                    ['senha' => 'previsivel']
                );
            }
        }

        $proibidas = [
            'senha123456', '1234567890', 'password123', 'qwerty12345',
            'admin123456', 'diariodebordo', '0123456789', 'abcd123456',
        ];
        foreach ($proibidas as $p) {
            if ($comparavel === $p) {
                throw new ErroApi('Esta senha é previsível demais. Escolha outra.', 422, ['senha' => 'comum']);
            }
        }

        return $valor;
    }
}
