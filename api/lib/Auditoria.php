<?php
/**
 * Trilha de auditoria: quem fez o quê, quando e de onde.
 *
 * Nunca registra senhas nem hashes — apenas identificadores e campos alterados.
 */

declare(strict_types=1);

defined('DIARIO_APP') || exit('Acesso negado.');

final class Auditoria
{
    private const CAMPOS_SIGILOSOS = ['senha', 'senha_hash', 'senhaAtual', 'novaSenha', 'csrf'];

    public static function registrar(
        string $acao,
        ?string $entidade = null,
        ?string $entidadeId = null,
        array $detalhes = []
    ): void {
        try {
            $usuario = null;
            $id = (int) ($_SESSION['usuario_id'] ?? 0);
            if ($id > 0) {
                $usuario = Bd::primeiro('SELECT id, nome FROM usuarios WHERE id = ?', [$id]);
            }

            Bd::consultar(
                'INSERT INTO auditoria (usuario_id, usuario_nome, acao, entidade, entidade_id, detalhes, ip)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $usuario['id'] ?? null,
                    $usuario['nome'] ?? null,
                    mb_substr($acao, 0, 40),
                    $entidade === null ? null : mb_substr($entidade, 0, 40),
                    ($entidadeId === null || $entidadeId === '') ? null : mb_substr($entidadeId, 0, 40),
                    $detalhes === [] ? null : json_encode(
                        self::limpar($detalhes),
                        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
                    ),
                    Seguranca::ip(),
                ]
            );
        } catch (Throwable $e) {
            // A auditoria nunca pode derrubar a operação principal.
            error_log('Falha ao registrar auditoria: ' . $e->getMessage());
        }
    }

    private static function limpar(array $detalhes): array
    {
        foreach ($detalhes as $chave => $valor) {
            if (in_array((string) $chave, self::CAMPOS_SIGILOSOS, true)) {
                $detalhes[$chave] = '***';
            } elseif (is_array($valor)) {
                $detalhes[$chave] = self::limpar($valor);
            } elseif (is_string($valor) && mb_strlen($valor) > 300) {
                $detalhes[$chave] = mb_substr($valor, 0, 300) . '…';
            }
        }
        return $detalhes;
    }
}
