<?php
declare(strict_types=1);

/**
 * Diário de Bordo CECAPE — front controller (único ponto de entrada).
 * Produção: https://cecapescs.com.br/diariobordo
 */

require __DIR__ . '/app/bootstrap.php';

use App\Controllers\AdminController;
use App\Controllers\ApiController;
use App\Controllers\DiarioController;
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

// Páginas
$router->add('GET', '', DiarioController::class, 'index');
$router->add('GET', 'admin', AdminController::class, 'index');

// API — leitura pública
$router->add('GET', 'api/list', ApiController::class, 'list');
$router->add('GET', 'api/me', ApiController::class, 'me');

// API — autenticação
$router->add('POST', 'api/login', ApiController::class, 'login');
$router->add('POST', 'api/logout', ApiController::class, 'logout');
$router->add('POST', 'api/password', ApiController::class, 'changePassword');

// API — escrita (somente administrador)
$router->add('GET', 'api/default-phases', ApiController::class, 'defaultPhases');
$router->add('POST', 'api/create', ApiController::class, 'create');
$router->add('POST', 'api/update-activity', ApiController::class, 'updateActivity');
$router->add('POST', 'api/delete', ApiController::class, 'delete');
$router->add('POST', 'api/phase', ApiController::class, 'phase');

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
