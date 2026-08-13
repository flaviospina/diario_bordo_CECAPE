<?php
declare(strict_types=1);

namespace App\Controllers;

defined('APP_RUNNING') or exit;

use App\Core\Controller;

/** Modo administrador — registro das atividades (somente o responsável). */
final class AdminController extends Controller
{
    public function index(): void
    {
        $this->view('admin/index', [
            'title' => 'Administração — ' . APP_NAME,
            'page' => 'admin',
        ]);
    }
}
