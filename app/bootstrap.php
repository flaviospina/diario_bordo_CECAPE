<?php
declare(strict_types=1);

/**
 * Diário de Bordo CECAPE — bootstrap da aplicação.
 * Este arquivo só pode ser carregado pelo front controller (index.php).
 */

define('APP_RUNNING', true);
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', __DIR__);

require APP_PATH . '/Config/config.php';

// Autoloader PSR-4 simples para o namespace App\
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = APP_PATH . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

/** Escape de saída para HTML (sempre usar nas views). */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL interna para uma rota da aplicação. */
function url(string $route): string
{
    return 'index.php?r=' . rawurlencode($route);
}
