<?php
/**
 * Diário de Bordo CECAPE — redefinição de senha de USO ÚNICO (sem terminal).
 *
 * Quando usar: o administrador esqueceu a senha e não consegue entrar.
 *
 * Como usar (tudo pelo Gerenciador de Arquivos do cPanel):
 *  1. Dentro da pasta "data" do sistema, crie um arquivo vazio chamado
 *        liberar-reset.txt
 *     (botão "+ Arquivo" / "New File"). Isso prova que você tem acesso ao
 *     servidor — sem esse arquivo o script não faz nada.
 *  2. Abra no navegador:
 *        https://cecapescs.com.br/diariobordo/tools/reset-senha.php
 *  3. O script grava a senha abaixo na conta indicada (no MESMO banco que o
 *     sistema usa), reativa a conta se estiver desativada, limpa o bloqueio
 *     por tentativas de login, mostra um diagnóstico e APAGA o marcador e a
 *     si mesmo. Entre e troque a senha no painel (Diário → Trocar minha senha).
 *
 * Para outra conta ou outra senha, edite as duas constantes abaixo
 * (Gerenciador de Arquivos → botão direito no arquivo → Editar).
 */
declare(strict_types=1);

const RESET_USERNAME = 'flavio';   // conta a redefinir
const RESET_PASSWORD = '123456';   // nova senha provisória

header('Content-Type: text/plain; charset=utf-8');

// Funciona tanto em tools/ (padrão) quanto copiado para a raiz do sistema
$root = is_file(__DIR__ . '/app/bootstrap.php') ? __DIR__ : dirname(__DIR__);
if (!is_file($root . '/app/bootstrap.php')) {
    http_response_code(500);
    exit("Não encontrei app/bootstrap.php. Este arquivo deve ficar em diariobordo/tools/ (ou na raiz do sistema).\n");
}
$marker = $root . '/data/liberar-reset.txt';
if (!is_file($marker)) {
    http_response_code(403);
    exit("Bloqueado: para liberar a redefinição, crie um arquivo vazio chamado\n\n"
        . "    liberar-reset.txt\n\n"
        . "dentro da pasta \"data\" do sistema (" . $root . "/data/) e recarregue esta página.\n"
        . "Esse passo garante que só quem tem acesso ao servidor consegue redefinir a senha.\n");
}

require $root . '/app/bootstrap.php';

try {
    $pdo = App\Core\Database::pdo();
    echo 'Banco em uso pelo sistema: ', App\Core\Database::isMysql() ? 'MySQL' : 'SQLite', "\n";
    if (App\Core\Database::isMysql()) {
        echo 'Banco MySQL: ', DB_MYSQL_NAME, "\n";
    }
    $st = $pdo->prepare('SELECT id, username, name, role, active FROM users WHERE username = :u');
    $st->execute([':u' => RESET_USERNAME]);
    $u = $st->fetch();
    if (!$u) {
        echo "\nA conta '", RESET_USERNAME, "' NÃO existe neste banco. Contas cadastradas:\n";
        foreach ($pdo->query('SELECT username, role, active FROM users') as $r) {
            echo '  - ', $r['username'], ' (', $r['role'], (int)$r['active'] === 1 ? '' : ', DESATIVADA', ")\n";
        }
        echo "\nEdite RESET_USERNAME neste arquivo com o nome certo e recarregue.\n";
        exit;
    }
    $pdo->prepare('UPDATE users SET password_hash = :h, active = 1 WHERE id = :id')
        ->execute([':h' => password_hash(RESET_PASSWORD, PASSWORD_DEFAULT), ':id' => (int)$u['id']]);
    $apagadas = (int)$pdo->exec('DELETE FROM login_attempts');
    echo "\nSenha da conta '", $u['username'], "' (", $u['name'], ', ', $u['role'], ") redefinida com sucesso.\n";
    echo 'Conta ', (int)$u['active'] === 1 ? 'já estava ativa' : 'estava DESATIVADA e foi reativada', ".\n";
    echo 'Bloqueio por tentativas de login: ', $apagadas, " registro(s) limpo(s).\n";
    echo "\nAgora entre com o usuário '", $u['username'], "' e a senha definida — e troque-a no painel.\n";
} catch (Throwable $e) {
    echo "\nERRO: ", $e->getMessage(), "\n";
} finally {
    @unlink($marker);
    echo @unlink(__FILE__)
        ? "\nO marcador e este script foram apagados do servidor automaticamente.\n"
        : "\nATENÇÃO: não foi possível apagar este script — exclua tools/reset-senha.php manualmente agora.\n";
}
