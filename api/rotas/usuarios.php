<?php
/**
 * Gestão de usuários — exclusiva de administradores.
 *
 * Não existe auto-cadastro: contas só nascem pela mão de um admin, o que
 * reduz bastante a superfície exposta.
 */

declare(strict_types=1);

defined('DIARIO_APP') || exit('Acesso negado.');

final class RotaUsuarios
{
    public static function listar(): never
    {
        Auth::exigirAdmin();

        $usuarios = Bd::todos(
            'SELECT id, nome, usuario, email, perfil, ativo, deve_trocar_senha,
                    ultimo_acesso, criado_em
               FROM usuarios
              ORDER BY ativo DESC, nome ASC
              LIMIT 500'
        );

        Resposta::ok(['usuarios' => array_map(self::formatar(...), $usuarios)]);
    }

    public static function criar(): never
    {
        $admin = Auth::exigirAdmin();
        Seguranca::validarCsrf();

        $d = Resposta::corpoJson(DIARIO_MAX_CORPO);

        $nome    = Validador::texto($d['nome'] ?? '', 'nome', 120, true);
        $usuario = Validador::nomeUsuario($d['usuario'] ?? '');
        $email   = Validador::email($d['email'] ?? '', 'e-mail');
        $perfil  = Validador::opcao($d['perfil'] ?? 'usuario', 'perfil', ['admin', 'usuario'], 'usuario');
        $senha   = Validador::senha($d['senha'] ?? '', $usuario, $email, $nome);
        $trocar  = Validador::booleano($d['deveTrocarSenha'] ?? true);

        if (Bd::primeiro('SELECT id FROM usuarios WHERE usuario = ? OR email = ?', [$usuario, $email])) {
            throw new ErroApi('Já existe um usuário com este login ou e-mail.', 409, ['usuario' => 'duplicado']);
        }

        Bd::consultar(
            'INSERT INTO usuarios (nome, usuario, email, senha_hash, perfil, ativo, deve_trocar_senha)
             VALUES (?,?,?,?,?,1,?)',
            [$nome, $usuario, $email, Auth::gerarHash($senha), $perfil, $trocar ? 1 : 0]
        );
        $id = Bd::ultimoId();

        Auditoria::registrar('usuario_criado', 'usuario', (string) $id, [
            'usuario' => $usuario, 'perfil' => $perfil, 'por' => $admin['usuario'],
        ]);

        $novo = Bd::primeiro(
            'SELECT id, nome, usuario, email, perfil, ativo, deve_trocar_senha, ultimo_acesso, criado_em
               FROM usuarios WHERE id = ?',
            [$id]
        );
        Resposta::ok(['usuario' => self::formatar($novo)], 201);
    }

    public static function atualizar(): never
    {
        $admin = Auth::exigirAdmin();
        Seguranca::validarCsrf();

        $d  = Resposta::corpoJson(DIARIO_MAX_CORPO);
        $id = Validador::inteiro($d['id'] ?? null, 'id', 1, PHP_INT_MAX);

        $alvo = Bd::primeiro('SELECT * FROM usuarios WHERE id = ?', [$id]);
        if (!$alvo) {
            throw new ErroApi('Usuário não encontrado.', 404);
        }

        $nome   = Validador::texto($d['nome'] ?? $alvo['nome'], 'nome', 120, true);
        $email  = Validador::email($d['email'] ?? $alvo['email'], 'e-mail');
        $perfil = Validador::opcao($d['perfil'] ?? $alvo['perfil'], 'perfil', ['admin', 'usuario'], 'usuario');
        $ativo  = array_key_exists('ativo', $d) ? Validador::booleano($d['ativo']) : (bool) $alvo['ativo'];

        // Trava de segurança: ninguém remove o próprio acesso administrativo,
        // e o sistema nunca fica sem nenhum admin ativo.
        $ehEu = (int) $admin['id'] === $id;
        if ($ehEu && ($perfil !== 'admin' || !$ativo)) {
            throw new ErroApi('Você não pode rebaixar nem desativar a própria conta.', 422);
        }
        if (($alvo['perfil'] === 'admin' && $perfil !== 'admin')
            || ((int) $alvo['ativo'] === 1 && !$ativo && $alvo['perfil'] === 'admin')) {
            $restantes = (int) (Bd::primeiro(
                "SELECT COUNT(*) AS n FROM usuarios WHERE perfil = 'admin' AND ativo = 1 AND id <> ?",
                [$id]
            )['n'] ?? 0);
            if ($restantes === 0) {
                throw new ErroApi('É preciso manter ao menos um administrador ativo.', 422);
            }
        }

        if (Bd::primeiro('SELECT id FROM usuarios WHERE email = ? AND id <> ?', [$email, $id])) {
            throw new ErroApi('Já existe um usuário com este e-mail.', 409, ['email' => 'duplicado']);
        }

        Bd::consultar(
            'UPDATE usuarios SET nome = ?, email = ?, perfil = ?, ativo = ? WHERE id = ?',
            [$nome, $email, $perfil, $ativo ? 1 : 0, $id]
        );

        Auditoria::registrar('usuario_atualizado', 'usuario', (string) $id, [
            'perfil' => $perfil, 'ativo' => $ativo, 'por' => $admin['usuario'],
        ]);

        $atualizado = Bd::primeiro(
            'SELECT id, nome, usuario, email, perfil, ativo, deve_trocar_senha, ultimo_acesso, criado_em
               FROM usuarios WHERE id = ?',
            [$id]
        );
        Resposta::ok(['usuario' => self::formatar($atualizado)]);
    }

    /** Redefinição de senha de outro usuário, com troca obrigatória no 1º acesso. */
    public static function redefinirSenha(): never
    {
        $admin = Auth::exigirAdmin();
        Seguranca::validarCsrf();

        $d  = Resposta::corpoJson(DIARIO_MAX_CORPO);
        $id = Validador::inteiro($d['id'] ?? null, 'id', 1, PHP_INT_MAX);

        $alvo = Bd::primeiro('SELECT id, nome, usuario, email FROM usuarios WHERE id = ?', [$id]);
        if (!$alvo) {
            throw new ErroApi('Usuário não encontrado.', 404);
        }

        $senha = Validador::senha($d['senha'] ?? '', $alvo['usuario'], $alvo['email'], $alvo['nome']);

        Bd::consultar(
            'UPDATE usuarios SET senha_hash = ?, deve_trocar_senha = 1 WHERE id = ?',
            [Auth::gerarHash($senha), $id]
        );
        Seguranca::limparTentativas($alvo['usuario']);

        Auditoria::registrar('senha_redefinida', 'usuario', (string) $id, ['por' => $admin['usuario']]);
        Resposta::ok(['id' => (string) $id]);
    }

    public static function excluir(): never
    {
        $admin = Auth::exigirAdmin();
        Seguranca::validarCsrf();

        $d  = Resposta::corpoJson(DIARIO_MAX_CORPO);
        $id = Validador::inteiro($d['id'] ?? null, 'id', 1, PHP_INT_MAX);

        if ((int) $admin['id'] === $id) {
            throw new ErroApi('Você não pode excluir a própria conta.', 422);
        }

        $alvo = Bd::primeiro('SELECT usuario, perfil FROM usuarios WHERE id = ?', [$id]);
        if (!$alvo) {
            throw new ErroApi('Usuário não encontrado.', 404);
        }
        if ($alvo['perfil'] === 'admin') {
            $restantes = (int) (Bd::primeiro(
                "SELECT COUNT(*) AS n FROM usuarios WHERE perfil = 'admin' AND ativo = 1 AND id <> ?",
                [$id]
            )['n'] ?? 0);
            if ($restantes === 0) {
                throw new ErroApi('É preciso manter ao menos um administrador ativo.', 422);
            }
        }

        Bd::consultar('DELETE FROM usuarios WHERE id = ?', [$id]);
        Auditoria::registrar('usuario_excluido', 'usuario', (string) $id, [
            'usuario' => $alvo['usuario'], 'por' => $admin['usuario'],
        ]);

        Resposta::ok(['id' => (string) $id]);
    }

    /** Trilha de auditoria — leitura restrita a administradores. */
    public static function auditoria(): never
    {
        Auth::exigirAdmin();
        $limite = Validador::inteiro($_GET['limite'] ?? 100, 'limite', 1, 500, 100);

        // LIMIT também vai por parâmetro vinculado (PARAM_INT), sem concatenação.
        $st = Bd::pdo()->prepare(
            'SELECT id, usuario_nome, acao, entidade, entidade_id, detalhes, ip, criado_em
               FROM auditoria ORDER BY id DESC LIMIT :limite'
        );
        $st->bindValue(':limite', $limite, PDO::PARAM_INT);
        $st->execute();
        $registros = $st->fetchAll();

        Resposta::ok(['registros' => $registros]);
    }

    private static function formatar(array $u): array
    {
        return [
            'id'              => (string) $u['id'],
            'nome'            => $u['nome'],
            'usuario'         => $u['usuario'],
            'email'           => $u['email'],
            'perfil'          => $u['perfil'],
            'ativo'           => (bool) $u['ativo'],
            'deveTrocarSenha' => (bool) $u['deve_trocar_senha'],
            'ultimoAcesso'    => $u['ultimo_acesso'] ?? null,
            'criadoEm'        => $u['criado_em'] ?? null,
        ];
    }
}
