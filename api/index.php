<?php
/**
 * Ponto de entrada único da API.
 *
 * Toda requisição passa por aqui: nenhum arquivo de lib/ ou rotas/ pode ser
 * acessado diretamente (constante de guarda + .htaccess). Isso garante que
 * cabeçalhos de segurança, sessão, CSRF e verificação de perfil sejam sempre
 * aplicados, sem depender de o desenvolvedor lembrar em cada arquivo.
 *
 * Chamada:  api/index.php?r=recurso/acao
 */

declare(strict_types=1);

define('DIARIO_APP', true);

// Erros nunca vão para a resposta: são registrados no log do servidor.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$raiz = __DIR__;

require $raiz . '/lib/Resposta.php';

/* ------------------------------------------------------------- configuração */

$caminhoConfig = $raiz . '/config.php';
if (!is_file($caminhoConfig)) {
    Resposta::erro(
        'API não configurada: copie api/config.example.php para api/config.php.',
        500
    );
}
$cfg = require $caminhoConfig;

if (!empty($cfg['log_erros'])) {
    ini_set('error_log', (string) $cfg['log_erros']);
}
date_default_timezone_set($cfg['fuso'] ?? 'America/Sao_Paulo');

// Limites de corpo da requisição, lidos pelas rotas.
define('DIARIO_MAX_CORPO', (int) ($cfg['seguranca']['tamanho_max_corpo'] ?? 1048576));
define('DIARIO_MAX_IMPORTACAO', (int) ($cfg['seguranca']['tamanho_max_importacao'] ?? 8388608));

require $raiz . '/lib/Bd.php';
require $raiz . '/lib/Seguranca.php';
require $raiz . '/lib/Auditoria.php';
require $raiz . '/lib/Validador.php';
require $raiz . '/lib/Auth.php';
require $raiz . '/rotas/auth.php';
require $raiz . '/rotas/atividades.php';
require $raiz . '/rotas/usuarios.php';
require $raiz . '/rotas/configuracoes.php';

/* ------------------------------------------------------------------- rotas */

/** rota => [método HTTP, callable] */
$mapa = [
    'auth/sessao'          => ['GET',  [RotaAuth::class, 'sessao']],
    'auth/login'           => ['POST', [RotaAuth::class, 'login']],
    'auth/logout'          => ['POST', [RotaAuth::class, 'logout']],
    'auth/senha'           => ['POST', [RotaAuth::class, 'senha']],

    'atividades'           => ['GET',  [RotaAtividades::class, 'listar']],
    'atividades/obter'     => ['GET',  [RotaAtividades::class, 'obter']],
    'atividades/criar'     => ['POST', [RotaAtividades::class, 'criar']],
    'atividades/atualizar' => ['POST', [RotaAtividades::class, 'atualizar']],
    'atividades/excluir'   => ['POST', [RotaAtividades::class, 'excluir']],

    'usuarios'             => ['GET',  [RotaUsuarios::class, 'listar']],
    'usuarios/criar'       => ['POST', [RotaUsuarios::class, 'criar']],
    'usuarios/atualizar'   => ['POST', [RotaUsuarios::class, 'atualizar']],
    'usuarios/senha'       => ['POST', [RotaUsuarios::class, 'redefinirSenha']],
    'usuarios/excluir'     => ['POST', [RotaUsuarios::class, 'excluir']],
    'auditoria'            => ['GET',  [RotaUsuarios::class, 'auditoria']],

    'config'               => ['GET',  [RotaConfiguracoes::class, 'obter']],
    'config/salvar'        => ['POST', [RotaConfiguracoes::class, 'salvar']],
    'config/importar'      => ['POST', [RotaConfiguracoes::class, 'importar']],
];

try {
    Seguranca::iniciar($cfg);
    Bd::conectar($cfg['bd']);

    $metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($metodo === 'OPTIONS') {
        // Sem CORS liberado por padrão: front e API na mesma origem.
        Resposta::erro('Método não permitido.', 405);
    }
    if (!in_array($metodo, ['GET', 'POST'], true)) {
        Resposta::erro('Método não permitido.', 405);
    }

    $rota = (string) ($_GET['r'] ?? '');
    if (!preg_match('#^[a-z]+(/[a-z]+)?$#', $rota) || !isset($mapa[$rota])) {
        Resposta::erro('Rota não encontrada.', 404);
    }

    [$metodoEsperado, $acao] = $mapa[$rota];
    if ($metodo !== $metodoEsperado) {
        Resposta::erro('Método não permitido para esta rota.', 405);
    }

    // Enquanto a troca de senha obrigatória estiver pendente, o resto fica travado.
    $usuario = Auth::atual();
    if ($usuario !== null) {
        Auth::bloquearSeSenhaPendente($usuario, $rota);
    }

    $acao();
} catch (ErroApi $e) {
    Resposta::erro($e->getMessage(), $e->status(), $e->campos());
} catch (PDOException $e) {
    // A mensagem original pode conter trechos de SQL: fica só no log.
    error_log('[diario-bordo] erro de banco: ' . $e->getMessage());
    Resposta::erro(
        !empty($cfg['depuracao'])
            ? 'Erro de banco: ' . $e->getMessage()
            : 'Não foi possível concluir a operação. Tente novamente.',
        500
    );
} catch (Throwable $e) {
    error_log('[diario-bordo] erro inesperado: ' . $e->getMessage() . ' em '
        . $e->getFile() . ':' . $e->getLine());
    Resposta::erro(
        !empty($cfg['depuracao'])
            ? 'Erro: ' . $e->getMessage()
            : 'Erro inesperado. Tente novamente.',
        500
    );
}
