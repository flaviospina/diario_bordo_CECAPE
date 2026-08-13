<?php
/**
 * Rotas de atividades e fases.
 *
 * Leitura: qualquer usuário autenticado. Escrita: apenas administradores.
 * Toda consulta usa parâmetros vinculados; o único trecho dinâmico do SQL é a
 * ordenação, resolvida por lista branca.
 */

declare(strict_types=1);

defined('DIARIO_APP') || exit('Acesso negado.');

final class RotaAtividades
{
    private const ORDENACOES = [
        'inicio_desc' => 'a.inicio_previsto DESC, a.id DESC',
        'inicio_asc'  => 'a.inicio_previsto ASC, a.id ASC',
        'titulo'      => 'a.titulo ASC',
        'criacao'     => 'a.criado_em DESC',
    ];

    /* ---------------------------------------------------------- leitura -- */

    public static function listar(): never
    {
        Auth::exigirLogin();

        $filtros = [
            'busca'       => Validador::texto($_GET['busca'] ?? '', 'busca', 120),
            'responsavel' => Validador::texto($_GET['responsavel'] ?? '', 'responsável', 80),
            'categoria'   => Validador::texto($_GET['categoria'] ?? '', 'categoria', 60),
            'de'          => Validador::data($_GET['de'] ?? null, 'de'),
            'ate'         => Validador::data($_GET['ate'] ?? null, 'até'),
        ];
        $ordem = Validador::opcao(
            $_GET['ordem'] ?? null,
            'ordem',
            array_keys(self::ORDENACOES),
            'inicio_desc'
        );

        $onde = [];
        $par  = [];

        if ($filtros['busca'] !== '') {
            // LIKE com parâmetro vinculado; os curingas entram no valor, não no SQL.
            $termo = '%' . self::escaparLike($filtros['busca']) . '%';
            $onde[] = "(a.titulo LIKE ? ESCAPE '\\\\'
                     OR a.descricao LIKE ? ESCAPE '\\\\'
                     OR a.responsavel LIKE ? ESCAPE '\\\\'
                     OR a.local LIKE ? ESCAPE '\\\\'
                     OR a.categoria LIKE ? ESCAPE '\\\\'
                     OR EXISTS (SELECT 1 FROM fases f2
                                 WHERE f2.atividade_id = a.id
                                   AND f2.nome LIKE ? ESCAPE '\\\\'))";
            array_push($par, $termo, $termo, $termo, $termo, $termo, $termo);
        }
        if ($filtros['responsavel'] !== '') {
            $onde[] = 'a.responsavel = ?';
            $par[]  = $filtros['responsavel'];
        }
        if ($filtros['categoria'] !== '') {
            $onde[] = 'a.categoria = ?';
            $par[]  = $filtros['categoria'];
        }
        if ($filtros['de'] !== null) {
            $onde[] = 'a.inicio_previsto >= ?';
            $par[]  = $filtros['de'] . ' 00:00:00';
        }
        if ($filtros['ate'] !== null) {
            $onde[] = 'a.inicio_previsto <= ?';
            $par[]  = $filtros['ate'] . ' 23:59:59';
        }

        $sql = 'SELECT a.* FROM atividades a'
            . ($onde ? ' WHERE ' . implode(' AND ', $onde) : '')
            . ' ORDER BY ' . self::ORDENACOES[$ordem]
            . ' LIMIT 2000';

        $atividades = Bd::todos($sql, $par);
        Resposta::ok(['atividades' => self::comFases($atividades)]);
    }

    public static function obter(): never
    {
        Auth::exigirLogin();
        $id = Validador::inteiro($_GET['id'] ?? null, 'id', 1, PHP_INT_MAX);

        $atividade = Bd::primeiro('SELECT * FROM atividades WHERE id = ?', [$id]);
        if (!$atividade) {
            throw new ErroApi('Atividade não encontrada.', 404);
        }

        $lista = self::comFases([$atividade]);
        Resposta::ok(['atividade' => $lista[0]]);
    }

    /* ---------------------------------------------------------- escrita -- */

    public static function criar(): never
    {
        $usuario = Auth::exigirAdmin();
        Seguranca::validarCsrf();

        $dados = Resposta::corpoJson(DIARIO_MAX_CORPO);
        $atv   = self::validarAtividade($dados);
        $fases = self::validarFases($dados['fases'] ?? []);

        $pdo = Bd::pdo();
        $pdo->beginTransaction();
        try {
            Bd::consultar(
                'INSERT INTO atividades
                    (titulo, descricao, responsavel, categoria, local, modelo,
                     inicio_previsto, fim_previsto, inicio_real, fim_real, cancelada,
                     criado_por, atualizado_por)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $atv['titulo'], $atv['descricao'], $atv['responsavel'], $atv['categoria'],
                    $atv['local'], $atv['modelo'], $atv['inicio_previsto'], $atv['fim_previsto'],
                    $atv['inicio_real'], $atv['fim_real'], $atv['cancelada'],
                    (int) $usuario['id'], (int) $usuario['id'],
                ]
            );
            $id = Bd::ultimoId();
            self::gravarFases($id, $fases);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        Auditoria::registrar('atividade_criada', 'atividade', (string) $id, ['titulo' => $atv['titulo']]);

        $lista = self::comFases([Bd::primeiro('SELECT * FROM atividades WHERE id = ?', [$id])]);
        Resposta::ok(['atividade' => $lista[0]], 201);
    }

    public static function atualizar(): never
    {
        $usuario = Auth::exigirAdmin();
        Seguranca::validarCsrf();

        $dados = Resposta::corpoJson(DIARIO_MAX_CORPO);
        $id    = Validador::inteiro($dados['id'] ?? null, 'id', 1, PHP_INT_MAX);

        if (!Bd::primeiro('SELECT id FROM atividades WHERE id = ?', [$id])) {
            throw new ErroApi('Atividade não encontrada.', 404);
        }

        $atv   = self::validarAtividade($dados);
        $fases = self::validarFases($dados['fases'] ?? []);

        $pdo = Bd::pdo();
        $pdo->beginTransaction();
        try {
            Bd::consultar(
                'UPDATE atividades
                    SET titulo = ?, descricao = ?, responsavel = ?, categoria = ?, local = ?,
                        modelo = ?, inicio_previsto = ?, fim_previsto = ?, inicio_real = ?,
                        fim_real = ?, cancelada = ?, atualizado_por = ?
                  WHERE id = ?',
                [
                    $atv['titulo'], $atv['descricao'], $atv['responsavel'], $atv['categoria'],
                    $atv['local'], $atv['modelo'], $atv['inicio_previsto'], $atv['fim_previsto'],
                    $atv['inicio_real'], $atv['fim_real'], $atv['cancelada'],
                    (int) $usuario['id'], $id,
                ]
            );
            Bd::consultar('DELETE FROM fases WHERE atividade_id = ?', [$id]);
            self::gravarFases($id, $fases);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        Auditoria::registrar('atividade_atualizada', 'atividade', (string) $id, ['titulo' => $atv['titulo']]);

        $lista = self::comFases([Bd::primeiro('SELECT * FROM atividades WHERE id = ?', [$id])]);
        Resposta::ok(['atividade' => $lista[0]]);
    }

    public static function excluir(): never
    {
        Auth::exigirAdmin();
        Seguranca::validarCsrf();

        $dados = Resposta::corpoJson(DIARIO_MAX_CORPO);
        $id    = Validador::inteiro($dados['id'] ?? null, 'id', 1, PHP_INT_MAX);

        $atv = Bd::primeiro('SELECT titulo FROM atividades WHERE id = ?', [$id]);
        if (!$atv) {
            throw new ErroApi('Atividade não encontrada.', 404);
        }

        // As fases somem junto pela FK ON DELETE CASCADE.
        Bd::consultar('DELETE FROM atividades WHERE id = ?', [$id]);
        Auditoria::registrar('atividade_excluida', 'atividade', (string) $id, ['titulo' => $atv['titulo']]);

        Resposta::ok(['id' => (string) $id]);
    }

    /**
     * Insere uma atividade vinda de um backup JSON. Passa exatamente pelas
     * mesmas validações do cadastro normal — arquivo importado é entrada de
     * usuário como qualquer outra.
     *
     * Deve ser chamada dentro de uma transação (ver RotaConfiguracoes::importar).
     */
    public static function importarUma(array $bruta, int $usuarioId): int
    {
        $atv = self::validarAtividade($bruta);

        $fases = $bruta['fases'] ?? [];
        if (!is_array($fases) || $fases === []) {
            // Backup sem fases: cria uma fase única cobrindo a atividade.
            $fases = [[
                'nome'           => 'Atividade',
                'peso'           => 100,
                'duracaoMin'     => max(1, (int) round(
                    (strtotime($atv['fim_previsto']) - strtotime($atv['inicio_previsto'])) / 60
                )),
                'inicioPrevisto' => $atv['inicio_previsto'],
                'fimPrevisto'    => $atv['fim_previsto'],
                'inicioReal'     => $atv['inicio_real'],
                'fimReal'        => $atv['fim_real'],
            ]];
        }
        $fases = self::validarFases($fases);

        Bd::consultar(
            'INSERT INTO atividades
                (titulo, descricao, responsavel, categoria, local, modelo,
                 inicio_previsto, fim_previsto, inicio_real, fim_real, cancelada,
                 criado_por, atualizado_por)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $atv['titulo'], $atv['descricao'], $atv['responsavel'], $atv['categoria'],
                $atv['local'], $atv['modelo'], $atv['inicio_previsto'], $atv['fim_previsto'],
                $atv['inicio_real'], $atv['fim_real'], $atv['cancelada'], $usuarioId, $usuarioId,
            ]
        );

        $id = Bd::ultimoId();
        self::gravarFases($id, $fases);

        return $id;
    }

    /* ----------------------------------------------------------- auxiliares */

    /** Carrega as fases de várias atividades sem cair em consulta por linha. */
    private static function comFases(array $atividades): array
    {
        $atividades = array_values(array_filter($atividades));
        if (!$atividades) {
            return [];
        }

        $ids = array_map(static fn(array $a): int => (int) $a['id'], $atividades);
        // Placeholders gerados a partir da CONTAGEM de ids, nunca dos valores.
        $marcadores = implode(',', array_fill(0, count($ids), '?'));

        $fases = Bd::todos(
            "SELECT * FROM fases WHERE atividade_id IN ($marcadores) ORDER BY atividade_id, ordem",
            $ids
        );

        $porAtividade = [];
        foreach ($fases as $f) {
            $porAtividade[(int) $f['atividade_id']][] = [
                'id'             => (string) $f['id'],
                'nome'           => $f['nome'],
                'peso'           => (float) $f['peso'],
                'duracaoMin'     => (int) $f['duracao_min'],
                'inicioPrevisto' => self::paraEntrada($f['inicio_previsto']),
                'fimPrevisto'    => self::paraEntrada($f['fim_previsto']),
                'inicioReal'     => self::paraEntrada($f['inicio_real']),
                'fimReal'        => self::paraEntrada($f['fim_real']),
                'observacao'     => (string) ($f['observacao'] ?? ''),
            ];
        }

        return array_map(static function (array $a) use ($porAtividade): array {
            return [
                'id'             => (string) $a['id'],
                'titulo'         => $a['titulo'],
                'descricao'      => (string) ($a['descricao'] ?? ''),
                'responsavel'    => (string) ($a['responsavel'] ?? ''),
                'categoria'      => (string) ($a['categoria'] ?? ''),
                'local'          => (string) ($a['local'] ?? ''),
                'modelo'         => (string) ($a['modelo'] ?? ''),
                'inicioPrevisto' => self::paraEntrada($a['inicio_previsto']),
                'fimPrevisto'    => self::paraEntrada($a['fim_previsto']),
                'inicioReal'     => self::paraEntrada($a['inicio_real']),
                'fimReal'        => self::paraEntrada($a['fim_real']),
                'cancelada'      => (bool) $a['cancelada'],
                'criadoEm'       => (string) $a['criado_em'],
                'atualizadoEm'   => (string) $a['atualizado_em'],
                'fases'          => $porAtividade[(int) $a['id']] ?? [],
            ];
        }, $atividades);
    }

    /** "2025-05-05 10:00:00" -> "2025-05-05T10:00" (formato do input HTML). */
    private static function paraEntrada(?string $valor): string
    {
        if ($valor === null || $valor === '' || str_starts_with($valor, '0000')) {
            return '';
        }
        return str_replace(' ', 'T', substr($valor, 0, 16));
    }

    private static function escaparLike(string $termo): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $termo);
    }

    private static function validarAtividade(array $d): array
    {
        $inicio = Validador::dataHora($d['inicioPrevisto'] ?? null, 'início previsto', true);
        $fim    = Validador::dataHora($d['fimPrevisto'] ?? null, 'término previsto', true);

        if ($fim < $inicio) {
            throw new ErroApi(
                'O término previsto não pode ser anterior ao início.',
                422,
                ['fimPrevisto' => 'ordem']
            );
        }

        $inicioReal = Validador::dataHora($d['inicioReal'] ?? null, 'início real');
        $fimReal    = Validador::dataHora($d['fimReal'] ?? null, 'término real');
        if ($inicioReal !== null && $fimReal !== null && $fimReal < $inicioReal) {
            throw new ErroApi(
                'O término real não pode ser anterior ao início real.',
                422,
                ['fimReal' => 'ordem']
            );
        }

        return [
            'titulo'          => Validador::texto($d['titulo'] ?? '', 'título', 160, true),
            'descricao'       => Validador::texto($d['descricao'] ?? '', 'descrição', 5000),
            'responsavel'     => Validador::texto($d['responsavel'] ?? '', 'responsável', 80),
            'categoria'       => Validador::texto($d['categoria'] ?? '', 'categoria', 60),
            'local'           => Validador::texto($d['local'] ?? '', 'local', 80),
            'modelo'          => Validador::texto($d['modelo'] ?? '', 'modelo', 30),
            'inicio_previsto' => $inicio,
            'fim_previsto'    => $fim,
            'inicio_real'     => $inicioReal,
            'fim_real'        => $fimReal,
            'cancelada'       => Validador::booleano($d['cancelada'] ?? false) ? 1 : 0,
        ];
    }

    private static function validarFases(mixed $fases): array
    {
        if (!is_array($fases) || $fases === []) {
            throw new ErroApi('Cadastre ao menos uma fase.', 422, ['fases' => 'obrigatório']);
        }
        if (count($fases) > 60) {
            throw new ErroApi('Uma atividade pode ter no máximo 60 fases.', 422, ['fases' => 'limite']);
        }

        $limpas = [];
        foreach (array_values($fases) as $i => $f) {
            if (!is_array($f)) {
                throw new ErroApi('Formato inválido na lista de fases.', 422, ['fases' => 'formato']);
            }
            $rotulo = 'fase ' . ($i + 1);

            $inicio = Validador::dataHora($f['inicioPrevisto'] ?? null, "início da {$rotulo}", true);
            $fim    = Validador::dataHora($f['fimPrevisto'] ?? null, "término da {$rotulo}", true);
            if ($fim < $inicio) {
                throw new ErroApi("O término da {$rotulo} é anterior ao início.", 422, ['fases' => 'ordem']);
            }

            $inicioReal = Validador::dataHora($f['inicioReal'] ?? null, "início real da {$rotulo}");
            $fimReal    = Validador::dataHora($f['fimReal'] ?? null, "término real da {$rotulo}");
            if ($inicioReal !== null && $fimReal !== null && $fimReal < $inicioReal) {
                throw new ErroApi(
                    "O término real da {$rotulo} é anterior ao início real.",
                    422,
                    ['fases' => 'ordem']
                );
            }
            if ($fimReal !== null && $inicioReal === null) {
                throw new ErroApi(
                    "A {$rotulo} tem término real sem início real.",
                    422,
                    ['fases' => 'incompleta']
                );
            }

            $limpas[] = [
                'ordem'           => $i + 1,
                'nome'            => Validador::texto($f['nome'] ?? '', "nome da {$rotulo}", 120, true),
                'peso'            => Validador::decimal($f['peso'] ?? 0, "peso da {$rotulo}", 0, 9999),
                'duracao_min'     => Validador::inteiro($f['duracaoMin'] ?? 1, "duração da {$rotulo}", 1, 5256000, 1),
                'inicio_previsto' => $inicio,
                'fim_previsto'    => $fim,
                'inicio_real'     => $inicioReal,
                'fim_real'        => $fimReal,
                'observacao'      => Validador::texto($f['observacao'] ?? '', "observação da {$rotulo}", 500),
            ];
        }

        return $limpas;
    }

    private static function gravarFases(int $atividadeId, array $fases): void
    {
        $sql = 'INSERT INTO fases
                    (atividade_id, ordem, nome, peso, duracao_min,
                     inicio_previsto, fim_previsto, inicio_real, fim_real, observacao)
                VALUES (?,?,?,?,?,?,?,?,?,?)';
        $st = Bd::pdo()->prepare($sql);

        foreach ($fases as $f) {
            $st->execute([
                $atividadeId, $f['ordem'], $f['nome'], $f['peso'], $f['duracao_min'],
                $f['inicio_previsto'], $f['fim_previsto'], $f['inicio_real'],
                $f['fim_real'], $f['observacao'],
            ]);
        }
    }
}
