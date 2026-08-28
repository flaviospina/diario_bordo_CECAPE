<?php
declare(strict_types=1);

namespace App\Controllers;

defined('APP_RUNNING') or exit;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Models\LoginAttempt;
use App\Models\User;

/** Painel único: tela de login e, após autenticar, a área do perfil. */
final class PanelController extends Controller
{
    public function index(): void
    {
        $user = Session::isLogged() ? User::find((int)Session::userId()) : null;
        if (!$user || !(int)$user['active']) {
            if (Session::isLogged()) {
                Session::logout();
            }
            $this->view('panel/login', ['title' => APP_NAME, 'page' => 'login']);
            return;
        }
        $this->view('panel/index', [
            'title' => APP_NAME,
            'page' => 'panel',
            'user' => $user,
        ]);
    }

    /** Tutorial do sistema (requer login). */
    public function tutorial(): void
    {
        $user = Session::isLogged() ? User::find((int)Session::userId()) : null;
        if (!$user || !(int)$user['active']) {
            $this->redirectHome();
        }
        $this->view('panel/tutorial', [
            'title' => 'Ajuda — ' . APP_NAME,
            'page' => 'tutorial',
            'user' => $user,
        ]);
    }

    /** Login por formulário HTML com redirect do servidor (funciona sem JS). */
    public function login(): void
    {
        if (Session::isLogged()) {
            $this->redirectHome();
        }
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $error = 'Usuário ou senha incorretos.';
        if (!Csrf::validate()) {
            $error = 'Sessão expirada. Tente novamente.';
        } elseif (LoginAttempt::isLocked($ip)) {
            $error = 'Muitas tentativas de login. Aguarde ' . LOGIN_WINDOW_MINUTES . ' minutos.';
        } else {
            $username = strtolower(trim((string)($_POST['username'] ?? '')));
            $pass = (string)($_POST['password'] ?? '');
            $user = $username !== '' ? User::findByUsername($username) : null;
            if ($user && $pass !== '' && password_verify($pass, $user['password_hash'])) {
                LoginAttempt::clear($ip);
                Session::loginUser((int)$user['id'], (string)$user['role']);
                if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                    User::update((int)$user['id'], ['password_hash' => password_hash($pass, PASSWORD_DEFAULT)]);
                }
                $this->redirectHome();
            }
            LoginAttempt::recordFailure($ip);
        }
        $this->view('panel/login', ['title' => APP_NAME, 'page' => 'login', 'error' => $error]);
    }

    public function logout(): void
    {
        if (Csrf::validate()) {
            Session::logout();
        }
        $this->redirectHome();
    }

    private function redirectHome(): never
    {
        header('Location: index.php');
        exit;
    }
}
