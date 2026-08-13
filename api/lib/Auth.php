<?php
/**
 * Autenticação e autorização.
 *
 * As senhas são guardadas com password_hash (Argon2id quando disponível,
 * bcrypt como alternativa) — nunca em texto puro, nunca com hash reversível.
 * A verificação de perfil acontece SEMPRE no servidor: a interface esconder um
 * botão é conveniência, não controle de acesso.
 */

declare(strict_types=1);

defined('DIARIO_APP') || exit('Acesso negado.');

final class Auth
{
    /** Hash descartável para igualar o tempo de resposta de usuário inexistente. */
    private const HASH_FALSO = '$2y$12$usuarioInexistenteXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';

    public static function algoritmo(): string
    {
        return defined('PASSWORD_ARGON2ID') && in_array('argon2id', password_algos(), true)
            ? PASSWORD_ARGON2ID
            : PASSWORD_BCRYPT;
    }

    public static function gerarHash(string $senha): string
    {
        $algo = self::algoritmo();
        $opcoes = $algo === PASSWORD_BCRYPT ? ['cost' => 12] : [];
        return password_hash($senha, $algo, $opcoes);
    }

    /**
     * Autentica por nome de usuário ou e-mail.
     * A mensagem de erro é sempre a mesma, para não revelar quais contas existem.
     */
    public static function login(string $identificador, string $senha): array
    {
        $identificador = mb_strtolower(trim($identificador));

        $espera = Seguranca::segundosBloqueado($identificador);
        if ($espera > 0) {
            throw new ErroApi(
                'Muitas tentativas malsucedidas. Tente novamente em ' . ceil($espera / 60) . ' minuto(s).',
                429
            );
        }

        $usuario = Bd::primeiro(
            'SELECT id, nome, usuario, email, senha_hash, perfil, ativo, deve_trocar_senha
               FROM usuarios
              WHERE usuario = ? OR email = ?
              LIMIT 1',
            [$identificador, $identificador]
        );

        // Verifica sempre um hash, mesmo sem usuário: o tempo de resposta não
        // deve denunciar se a conta existe (enumeração por temporização).
        $hash = $usuario['senha_hash'] ?? self::HASH_FALSO;
        $confere = password_verify($senha, $hash);

        if (!$usuario || !$confere || (int) $usuario['ativo'] !== 1) {
            Seguranca::registrarTentativa($identificador, false);
            Auditoria::registrar('login_negado', 'usuario', (string) ($usuario['id'] ?? ''), [
                'identificador' => $identificador,
                'motivo'        => !$usuario ? 'inexistente' : (!$confere ? 'senha' : 'inativo'),
            ]);
            throw new ErroApi('Usuário ou senha inválidos.', 401);
        }

        // Reforça o hash se o algoritmo/custo mudou desde o cadastro.
        if (password_needs_rehash($hash, self::algoritmo())) {
            Bd::consultar('UPDATE usuarios SET senha_hash = ? WHERE id = ?', [
                self::gerarHash($senha), (int) $usuario['id'],
            ]);
        }

        Seguranca::registrarTentativa($identificador, true);
        Seguranca::limparTentativas($identificador);
        Seguranca::renovarSessao((int) $usuario['id']);

        Bd::consultar('UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?', [(int) $usuario['id']]);
        Auditoria::registrar('login', 'usuario', (string) $usuario['id']);

        return self::formatar($usuario);
    }

    public static function logout(): void
    {
        if (!empty($_SESSION['usuario_id'])) {
            Auditoria::registrar('logout', 'usuario', (string) $_SESSION['usuario_id']);
        }
        Seguranca::encerrarSessao();
    }

    /** Usuário autenticado nesta sessão, ou null. */
    public static function atual(): ?array
    {
        $id = (int) ($_SESSION['usuario_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        $usuario = Bd::primeiro(
            'SELECT id, nome, usuario, email, perfil, ativo, deve_trocar_senha, ultimo_acesso
               FROM usuarios WHERE id = ? LIMIT 1',
            [$id]
        );

        // Conta desativada ou removida derruba a sessão na hora.
        if (!$usuario || (int) $usuario['ativo'] !== 1) {
            Seguranca::encerrarSessao();
            return null;
        }

        return self::formatar($usuario);
    }

    public static function exigirLogin(): array
    {
        $usuario = self::atual();
        if ($usuario === null) {
            throw new ErroApi('Sessão expirada. Entre novamente.', 401);
        }
        return $usuario;
    }

    public static function exigirAdmin(): array
    {
        $usuario = self::exigirLogin();
        if ($usuario['perfil'] !== 'admin') {
            Auditoria::registrar('acesso_negado', 'rota', (string) ($_GET['r'] ?? ''));
            throw new ErroApi('Ação permitida apenas para administradores.', 403);
        }
        return $usuario;
    }

    /**
     * Enquanto a troca obrigatória de senha estiver pendente, só as rotas de
     * sessão e de troca de senha ficam liberadas.
     */
    public static function bloquearSeSenhaPendente(array $usuario, string $rota): void
    {
        $liberadas = ['auth/sessao', 'auth/logout', 'auth/senha'];
        if ((int) $usuario['deveTrocarSenha'] === 1 && !in_array($rota, $liberadas, true)) {
            throw new ErroApi('Troque sua senha antes de continuar.', 428);
        }
    }

    private static function formatar(array $u): array
    {
        return [
            'id'              => (string) $u['id'],
            'nome'            => $u['nome'],
            'usuario'         => $u['usuario'],
            'email'           => $u['email'],
            'perfil'          => $u['perfil'],
            'deveTrocarSenha' => (int) ($u['deve_trocar_senha'] ?? 0),
            'ultimoAcesso'    => isset($u['ultimo_acesso']) && $u['ultimo_acesso']
                ? str_replace(' ', 'T', substr((string) $u['ultimo_acesso'], 0, 16))
                : null,
        ];
    }
}
