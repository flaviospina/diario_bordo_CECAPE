<?php
/**
 * Configurações da aplicação (identificação e jornada de trabalho) e
 * importação de backup JSON gerado pela versão local.
 */

declare(strict_types=1);

defined('DIARIO_APP') || exit('Acesso negado.');

final class RotaConfiguracoes
{
    private const CHAVES = ['organizacao', 'subtitulo', 'jornada'];

    public static function obter(): never
    {
        Auth::exigirLogin();
        Resposta::ok(['config' => self::carregar()]);
    }

    public static function salvar(): never
    {
        $admin = Auth::exigirAdmin();
        Seguranca::validarCsrf();

        $d = Resposta::corpoJson(DIARIO_MAX_CORPO);

        $valores = [
            'organizacao' => Validador::texto($d['organizacao'] ?? 'CECAPE', 'organização', 80, true),
            'subtitulo'   => Validador::texto($d['subtitulo'] ?? '', 'subtítulo', 120),
            'jornada'     => json_encode(
                self::validarJornada($d['jornada'] ?? []),
                JSON_UNESCAPED_UNICODE
            ),
        ];

        foreach ($valores as $chave => $valor) {
            Bd::consultar(
                'INSERT INTO configuracoes (chave, valor, atualizado_por) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE valor = VALUES(valor), atualizado_por = VALUES(atualizado_por)',
                [$chave, $valor, (int) $admin['id']]
            );
        }

        Auditoria::registrar('config_salva', 'configuracao', null, ['organizacao' => $valores['organizacao']]);
        Resposta::ok(['config' => self::carregar()]);
    }

    /**
     * Importa um backup JSON (o mesmo formato exportado pela aplicação).
     * Só administradores; sempre dentro de uma transação.
     */
    public static function importar(): never
    {
        $admin = Auth::exigirAdmin();
        Seguranca::validarCsrf();

        $d = Resposta::corpoJson(DIARIO_MAX_IMPORTACAO);
        $atividades = $d['atividades'] ?? null;
        $substituir = Validador::booleano($d['substituir'] ?? false);

        if (!is_array($atividades)) {
            throw new ErroApi('Arquivo inválido: não contém a lista de atividades.', 422);
        }
        if (count($atividades) > 5000) {
            throw new ErroApi('O backup excede o limite de 5000 atividades por importação.', 422);
        }

        $pdo = Bd::pdo();
        $pdo->beginTransaction();
        try {
            if ($substituir) {
                // TRUNCATE não pode: precisa respeitar a FK das fases.
                Bd::consultar('DELETE FROM atividades');
            }

            $importadas = 0;
            foreach ($atividades as $bruta) {
                if (!is_array($bruta)) {
                    continue;
                }
                RotaAtividades::importarUma($bruta, (int) $admin['id']);
                $importadas++;
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        Auditoria::registrar('backup_importado', 'atividade', null, [
            'quantidade' => $importadas, 'substituiu' => $substituir,
        ]);

        Resposta::ok(['importadas' => $importadas]);
    }

    /* ------------------------------------------------------------ interno */

    public static function carregar(): array
    {
        $linhas = Bd::todos('SELECT chave, valor FROM configuracoes');
        $mapa = [];
        foreach ($linhas as $l) {
            $mapa[$l['chave']] = $l['valor'];
        }

        $jornada = json_decode((string) ($mapa['jornada'] ?? ''), true);

        return [
            'organizacao' => (string) ($mapa['organizacao'] ?? 'CECAPE'),
            'subtitulo'   => (string) ($mapa['subtitulo'] ?? 'Diário de Bordo — Registro de Atividades'),
            'jornada'     => is_array($jornada) ? $jornada : self::jornadaPadrao(),
        ];
    }

    private static function jornadaPadrao(): array
    {
        return [
            'modo' => 'comercial', 'dias' => [1, 2, 3, 4, 5],
            'inicio' => '08:00', 'fim' => '18:00',
            'pausaAtiva' => true, 'pausaInicio' => '12:00', 'pausaFim' => '13:00',
        ];
    }

    private static function validarJornada(mixed $j): array
    {
        if (!is_array($j)) {
            return self::jornadaPadrao();
        }

        $modo = Validador::opcao($j['modo'] ?? 'comercial', 'modo', ['comercial', 'continuo'], 'comercial');

        $dias = [];
        foreach ((array) ($j['dias'] ?? []) as $dia) {
            $n = Validador::inteiro($dia, 'dia da semana', 0, 6, 0);
            if (!in_array($n, $dias, true)) {
                $dias[] = $n;
            }
        }
        sort($dias);
        if ($modo === 'comercial' && $dias === []) {
            throw new ErroApi('Selecione ao menos um dia útil.', 422, ['dias' => 'obrigatório']);
        }

        $hora = static function (mixed $v, string $campo, string $padrao): string {
            if (!is_string($v) || !preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $v)) {
                if ($v === null || $v === '') {
                    return $padrao;
                }
                throw new ErroApi("Horário inválido em \"{$campo}\".", 422, [$campo => 'formato']);
            }
            return $v;
        };

        $inicio = $hora($j['inicio'] ?? null, 'início do expediente', '08:00');
        $fim    = $hora($j['fim'] ?? null, 'fim do expediente', '18:00');
        if ($modo === 'comercial' && $fim <= $inicio) {
            throw new ErroApi('O fim do expediente deve ser posterior ao início.', 422, ['fim' => 'ordem']);
        }

        $pausaInicio = $hora($j['pausaInicio'] ?? null, 'início do intervalo', '12:00');
        $pausaFim    = $hora($j['pausaFim'] ?? null, 'fim do intervalo', '13:00');
        $pausaAtiva  = Validador::booleano($j['pausaAtiva'] ?? false);
        if ($pausaAtiva && $pausaFim <= $pausaInicio) {
            throw new ErroApi('O fim do intervalo deve ser posterior ao início.', 422, ['pausaFim' => 'ordem']);
        }

        return [
            'modo' => $modo, 'dias' => $dias,
            'inicio' => $inicio, 'fim' => $fim,
            'pausaAtiva' => $pausaAtiva, 'pausaInicio' => $pausaInicio, 'pausaFim' => $pausaFim,
        ];
    }
}
