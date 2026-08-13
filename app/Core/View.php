<?php
declare(strict_types=1);

namespace App\Core;

defined('APP_RUNNING') or exit;

final class View
{
    /**
     * Renderiza uma view dentro do layout principal.
     * As chaves de $data viram variáveis locais no template e no layout.
     */
    public static function render(string $template, array $data = [], string $layout = 'layouts/main'): string
    {
        $content = self::partial($template, $data);
        return self::partial($layout, $data + ['content' => $content]);
    }

    /** Renderiza um template isolado (sem layout). */
    public static function partial(string $template, array $data = []): string
    {
        $file = APP_PATH . '/Views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View não encontrada: $template");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return (string)ob_get_clean();
    }
}
