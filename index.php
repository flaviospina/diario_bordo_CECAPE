<?php
declare(strict_types=1);

/**
 * Diário de Bordo CECAPE — front controller (único ponto de entrada).
 * Produção: https://cecapescs.com.br/diariobordo
 */

require __DIR__ . '/app/bootstrap.php';

use App\Controllers\ApiController;
use App\Controllers\PanelController;
use App\Core\Router;
use App\Core\Session;

// Erros nunca são exibidos ao visitante (vão para o log do servidor)
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Cabeçalhos de segurança
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header('X-Robots-Tag: noindex');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; "
    . "script-src 'self' https://cdn.jsdelivr.net; "
    . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
    . "font-src 'self' https://fonts.gstatic.com; "
    . "img-src 'self' data: https://cecapescs.com.br; "
    . "connect-src 'self'; "
    . "base-uri 'self'; frame-ancestors 'self'; form-action 'self'; object-src 'none'");

Session::start();

$router = new Router();

// Painel único (login + área por perfil)
$router->add('GET', '', PanelController::class, 'index');
$router->add('GET', 'admin', PanelController::class, 'index'); // endereço antigo
$router->add('GET', 'ajuda', PanelController::class, 'tutorial');
$router->add('POST', 'login', PanelController::class, 'login');
$router->add('POST', 'logout', PanelController::class, 'logout');

// API — sessão
$router->add('GET', 'api/me', ApiController::class, 'me');
$router->add('POST', 'api/login', ApiController::class, 'login');
$router->add('POST', 'api/logout', ApiController::class, 'logout');
$router->add('POST', 'api/password', ApiController::class, 'changePassword');

// API — consulta (autenticada)
$router->add('GET', 'api/list', ApiController::class, 'list');
$router->add('GET', 'api/professors', ApiController::class, 'professors');
$router->add('GET', 'api/default-phases', ApiController::class, 'defaultPhases');

// API — apontamentos (admin e professores, somente os próprios)
$router->add('POST', 'api/create', ApiController::class, 'create');
$router->add('POST', 'api/update-activity', ApiController::class, 'updateActivity');
$router->add('POST', 'api/delete', ApiController::class, 'delete');
$router->add('POST', 'api/phase', ApiController::class, 'phase');
$router->add('POST', 'api/break-create', ApiController::class, 'breakCreate');
$router->add('POST', 'api/break-delete', ApiController::class, 'breakDelete');

// API — jornada de trabalho e banco de horas
$router->add('GET', 'api/schedule', ApiController::class, 'schedule');
$router->add('POST', 'api/schedule-save', ApiController::class, 'scheduleSave');
$router->add('GET', 'api/hour-bank', ApiController::class, 'hourBank');
$router->add('POST', 'api/overtime-register', ApiController::class, 'overtimeRegister');
$router->add('POST', 'api/hour-bank-delete', ApiController::class, 'hourBankDelete');

// API — contas (somente administrador)
$router->add('GET', 'api/users', ApiController::class, 'users');
$router->add('POST', 'api/user-create', ApiController::class, 'userCreate');
$router->add('POST', 'api/user-update', ApiController::class, 'userUpdate');

$route = Router::currentRoute();
try {
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $route);
} catch (Throwable $e) {
    error_log('[diario_bordo] ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if (str_starts_with($route, 'api/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Erro interno. Tente novamente.'], JSON_UNESCAPED_UNICODE);
    } else {
        echo '<h1>Erro interno</h1><p>Tente novamente em instantes.</p>';
    }
}
