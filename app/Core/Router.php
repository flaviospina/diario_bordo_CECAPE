<?php
declare(strict_types=1);

namespace App\Core;

defined('APP_RUNNING') or exit;

final class Router
{
    /** @var array<string, array{0: class-string, 1: string}> */
    private array $routes = [];

    public function add(string $method, string $route, string $controller, string $action): void
    {
        $this->routes[strtoupper($method) . ' ' . trim($route, '/')] = [$controller, $action];
    }

    /** Resolve a rota atual: ?r=... ou caminho amigável via mod_rewrite. */
    public static function currentRoute(): string
    {
        $route = $_GET['r'] ?? null;
        if ($route === null) {
            $uri = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
            $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
            if ($base !== '' && str_starts_with($uri, $base)) {
                $uri = substr($uri, strlen($base));
            }
            $route = ltrim($uri, '/');
            if ($route === 'index.php') {
                $route = '';
            }
        }
        return trim((string)$route, '/');
    }

    public function dispatch(string $method, string $route): void
    {
        $key = strtoupper($method) . ' ' . trim($route, '/');
        $handler = $this->routes[$key] ?? null;
        if ($handler === null) {
            http_response_code(404);
            if (str_starts_with($route, 'api/')) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Rota não encontrada.'], JSON_UNESCAPED_UNICODE);
            } else {
                echo '<h1>404 — Página não encontrada</h1>';
            }
            return;
        }
        [$class, $action] = $handler;
        (new $class())->$action();
    }
}
