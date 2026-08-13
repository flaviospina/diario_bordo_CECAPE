<?php
/**
 * Cria (ou promove) o primeiro administrador do sistema.
 *
 * Executa APENAS pela linha de comando — se alguém tentar abrir pela web, sai.
 * Assim não fica um "instalador" acessível no servidor depois do deploy.
 *
 *   php ferramentas/criar-admin.php
 *   php ferramentas/criar-admin.php --usuario=flavio --nome="Flávio Spina" \
 *       --email=flavio@exemplo.com
 *
 * A senha é sempre pedida de forma interativa (não fica no histórico do shell).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Esta ferramenta só pode ser executada pela linha de comando.');
}

define('DIARIO_APP', true);

$raiz = dirname(__DIR__);
require $raiz . '/api/lib/Resposta.php';
require $raiz . '/api/lib/Bd.php';
require $raiz . '/api/lib/Auth.php';

$caminhoConfig = $raiz . '/api/config.php';
if (!is_file($caminhoConfig)) {
    fwrite(STDERR, "Erro: crie o api/config.php a partir do api/config.example.php.\n");
    exit(1);
}
$cfg = require $caminhoConfig;

/* --------------------------------------------------------------- entrada -- */

$opcoes = getopt('', ['usuario::', 'nome::', 'email::', 'senha::']);

function perguntar(string $rotulo, ?string $padrao = null): string
{
    $sufixo = $padrao !== null ? " [{$padrao}]" : '';
    fwrite(STDOUT, $rotulo . $sufixo . ': ');
    $linha = trim((string) fgets(STDIN));
    return $linha !== '' ? $linha : (string) $padrao;
}

/** Lê a senha sem exibir na tela (quando o terminal permitir). */
function perguntarSenha(string $rotulo): string
{
    fwrite(STDOUT, $rotulo . ': ');
    $ehTty = stream_isatty(STDIN);
    if ($ehTty && DIRECTORY_SEPARATOR !== '\\') {
        shell_exec('stty -echo 2>/dev/null');
    }
    $senha = rtrim((string) fgets(STDIN), "\r\n");
    if ($ehTty && DIRECTORY_SEPARATOR !== '\\') {
        shell_exec('stty echo 2>/dev/null');
        fwrite(STDOUT, "\n");
    }
    return $senha;
}

try {
    Bd::conectar($cfg['bd']);
} catch (Throwable $e) {
    fwrite(STDERR, "Não foi possível conectar ao banco: {$e->getMessage()}\n");
    exit(1);
}

echo "\n=== Diário de Bordo — criação de administrador ===\n\n";

$nome    = $opcoes['nome']    ?? perguntar('Nome completo');
$usuario = mb_strtolower($opcoes['usuario'] ?? perguntar('Nome de usuário (login)'));
$email   = mb_strtolower($opcoes['email']   ?? perguntar('E-mail'));

if (!preg_match('/^[a-z0-9._-]{3,60}$/', $usuario)) {
    fwrite(STDERR, "Usuário inválido: use de 3 a 60 caracteres (letras, números, . _ -).\n");
    exit(1);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "E-mail inválido.\n");
    exit(1);
}
if (trim($nome) === '') {
    fwrite(STDERR, "Informe o nome.\n");
    exit(1);
}

$senha = $opcoes['senha'] ?? '';
if ($senha === '') {
    $senha = perguntarSenha('Senha (mínimo 10 caracteres, com letras e números)');
    $confirmacao = perguntarSenha('Repita a senha');
    if ($senha !== $confirmacao) {
        fwrite(STDERR, "As senhas não coincidem.\n");
        exit(1);
    }
}

if (mb_strlen($senha) < 10
    || !preg_match('/[a-zA-ZÀ-ÿ]/u', $senha)
    || !preg_match('/\d/', $senha)) {
    fwrite(STDERR, "Senha fraca: use ao menos 10 caracteres, com letras e números.\n");
    exit(1);
}
if (str_contains(mb_strtolower($senha), $usuario)) {
    fwrite(STDERR, "A senha não pode conter o nome de usuário.\n");
    exit(1);
}

/* ---------------------------------------------------------------- gravação */

$existente = Bd::primeiro(
    'SELECT id, usuario FROM usuarios WHERE usuario = ? OR email = ? LIMIT 1',
    [$usuario, $email]
);

if ($existente) {
    echo "\nJá existe um usuário com este login/e-mail ({$existente['usuario']}).\n";
    $resposta = mb_strtolower(perguntar('Redefinir a senha e promover a admin? (s/N)', 'n'));
    if ($resposta !== 's') {
        echo "Nada foi alterado.\n";
        exit(0);
    }
    Bd::consultar(
        "UPDATE usuarios
            SET nome = ?, senha_hash = ?, perfil = 'admin', ativo = 1, deve_trocar_senha = 0
          WHERE id = ?",
        [$nome, Auth::gerarHash($senha), (int) $existente['id']]
    );
    echo "\n✔ Usuário atualizado e promovido a administrador.\n\n";
    exit(0);
}

Bd::consultar(
    "INSERT INTO usuarios (nome, usuario, email, senha_hash, perfil, ativo, deve_trocar_senha)
     VALUES (?, ?, ?, ?, 'admin', 1, 0)",
    [$nome, $usuario, $email, Auth::gerarHash($senha)]
);

$algoritmo = Auth::algoritmo() === PASSWORD_BCRYPT ? 'bcrypt (custo 12)' : 'Argon2id';

echo "\n✔ Administrador criado.\n";
echo "  Login .....: {$usuario}\n";
echo "  Hash ......: {$algoritmo}\n";
echo "  Acesse ....: login.html\n\n";
