<?php
/**
 * Rotas de sessão: login, logout, dados da sessão e troca da própria senha.
 */

declare(strict_types=1);

defined('DIARIO_APP') || exit('Acesso negado.');

final class RotaAuth
{
    public static function login(): never
    {
        // Protege também o login contra CSRF: o token vem de GET auth/sessao.
        Seguranca::validarCsrf();

        $dados = Resposta::corpoJson(DIARIO_MAX_CORPO);
        $identificador = Validador::texto($dados['usuario'] ?? '', 'usuário', 160, true);
        $senha = is_string($dados['senha'] ?? null) ? $dados['senha'] : '';

        if ($senha === '') {
            throw new ErroApi('Informe a senha.', 422, ['senha' => 'obrigatório']);
        }

        $usuario = Auth::login($identificador, $senha);

        Resposta::ok([
            'usuario' => $usuario,
            'csrf'    => Seguranca::csrf(),
        ]);
    }

    public static function logout(): never
    {
        Seguranca::validarCsrf();
        Auth::logout();
        Resposta::ok(['csrf' => Seguranca::csrf()]);
    }

    /** Estado atual da sessão + token CSRF para as próximas requisições. */
    public static function sessao(): never
    {
        $usuario = Auth::atual();
        Resposta::ok([
            'autenticado' => $usuario !== null,
            'usuario'     => $usuario,
            'csrf'        => Seguranca::csrf(),
        ]);
    }

    /** Troca da própria senha (exige a senha atual). */
    public static function senha(): never
    {
        $usuario = Auth::exigirLogin();
        Seguranca::validarCsrf();

        $dados = Resposta::corpoJson(DIARIO_MAX_CORPO);
        $atual = is_string($dados['senhaAtual'] ?? null) ? $dados['senhaAtual'] : '';
        $nova  = $dados['novaSenha'] ?? '';

        $linha = Bd::primeiro('SELECT senha_hash FROM usuarios WHERE id = ?', [(int) $usuario['id']]);
        if (!$linha || !password_verify($atual, $linha['senha_hash'])) {
            Seguranca::registrarTentativa($usuario['usuario'], false);
            throw new ErroApi('Senha atual incorreta.', 401, ['senhaAtual' => 'invalida']);
        }

        $nova = Validador::senha($nova, $usuario['usuario'], $usuario['email'], $usuario['nome']);
        if (password_verify($nova, $linha['senha_hash'])) {
            throw new ErroApi('A nova senha deve ser diferente da atual.', 422, ['novaSenha' => 'repetida']);
        }

        Bd::consultar(
            'UPDATE usuarios SET senha_hash = ?, deve_trocar_senha = 0 WHERE id = ?',
            [Auth::gerarHash($nova), (int) $usuario['id']]
        );

        // Troca de senha invalida a sessão antiga e abre uma nova.
        Seguranca::renovarSessao((int) $usuario['id']);
        Auditoria::registrar('senha_alterada', 'usuario', $usuario['id']);

        Resposta::ok([
            'csrf'    => Seguranca::csrf(),
            'usuario' => Auth::atual(),
        ]);
    }
}
