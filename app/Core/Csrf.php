<?php
declare(strict_types=1);

namespace App\Core;

defined('APP_RUNNING') or exit;

final class Csrf
{
    /** Retorna o token CSRF da sessão, criando-o se necessário. */
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /** Valida o token enviado no cabeçalho X-CSRF-Token (ou campo _csrf). */
    public static function validate(): bool
    {
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
        $known = $_SESSION['csrf_token'] ?? '';
        return $known !== '' && is_string($sent) && hash_equals($known, $sent);
    }
}
