<?php
declare(strict_types=1);

namespace App\Core;

defined('APP_RUNNING') or exit;

abstract class Controller
{
    protected function view(string $template, array $data = []): void
    {
        echo View::render($template, $data);
    }

    protected function json(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Corpo JSON da requisição (limitado a 64 KB). */
    protected function body(): array
    {
        $raw = file_get_contents('php://input', false, null, 0, 65536);
        $data = json_decode((string)$raw, true);
        return is_array($data) ? $data : [];
    }

    protected function requireLogin(): void
    {
        if (!Session::isLogged()) {
            $this->json(['error' => 'Faça login para continuar.'], 401);
        }
    }

    protected function requireAdmin(): void
    {
        $this->requireLogin();
        if (!Session::isAdmin()) {
            $this->json(['error' => 'Acesso restrito ao administrador.'], 403);
        }
    }

    /** Perfis que registram apontamentos (admin e professor). */
    protected function requireProfessorCapable(): void
    {
        $this->requireLogin();
        if (Session::role() === 'gestor') {
            $this->json(['error' => 'O perfil de gestão é somente de consulta.'], 403);
        }
    }

    protected function requireCsrf(): void
    {
        if (!Csrf::validate()) {
            $this->json(['error' => 'Sessão expirada. Recarregue a página e tente novamente.'], 419);
        }
    }
}
