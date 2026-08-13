<?php
declare(strict_types=1);

namespace App\Controllers;

defined('APP_RUNNING') or exit;

use App\Core\Controller;

/** Modo consulta — visualização pública (diretor). */
final class DiarioController extends Controller
{
    public function index(): void
    {
        $this->view('diario/index', [
            'title' => APP_NAME,
            'page' => 'diario',
        ]);
    }
}
