<?php
declare(strict_types=1);

namespace App\Controllers;

defined('APP_RUNNING') or exit;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Models\Activity;
use App\Models\LoginAttempt;
use App\Models\Phase;
use App\Models\Setting;

/**
 * API JSON da aplicação.
 * Leitura (list, me) é pública; toda escrita exige sessão de administrador
 * e token CSRF válido no cabeçalho X-CSRF-Token.
 */
final class ApiController extends Controller
{
    /* ---------------- Leitura pública ---------------- */

    public function me(): void
    {
        $this->json(['admin' => Session::isAdmin()]);
    }

    public function list(): void
    {
        $from = $this->dateParam('from');
        $to = $this->dateParam('to');
        $q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100) ?: null;
        $this->json([
            'app' => APP_NAME,
            'owner' => APP_OWNER,
            'activities' => Activity::allWithPhases($from, $to, $q),
        ]);
    }

    /* ---------------- Autenticação ---------------- */

    public function login(): void
    {
        $this->requireCsrf();
        $ip = $this->clientIp();
        if (LoginAttempt::isLocked($ip)) {
            $this->json(['error' => 'Muitas tentativas de login. Aguarde ' . LOGIN_WINDOW_MINUTES . ' minutos.'], 429);
        }
        $pass = (string)($this->body()['password'] ?? '');
        $hash = Setting::get('admin_password_hash') ?? DEFAULT_ADMIN_PASSWORD_HASH;
        if ($pass === '' || !password_verify($pass, $hash)) {
            LoginAttempt::recordFailure($ip);
            $this->json(['error' => 'Senha incorreta.'], 401);
        }
        LoginAttempt::clear($ip);
        Session::loginAdmin();
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            Setting::set('admin_password_hash', password_hash($pass, PASSWORD_DEFAULT));
        }
        $this->json(['ok' => true]);
    }

    public function logout(): void
    {
        $this->requireCsrf();
        Session::logout();
        $this->json(['ok' => true]);
    }

    public function changePassword(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $data = $this->body();
        $current = (string)($data['current'] ?? '');
        $new = (string)($data['new'] ?? '');
        $hash = Setting::get('admin_password_hash') ?? DEFAULT_ADMIN_PASSWORD_HASH;
        if (!password_verify($current, $hash)) {
            $this->json(['error' => 'A senha atual está incorreta.'], 422);
        }
        if (mb_strlen($new) < 8) {
            $this->json(['error' => 'A nova senha deve ter pelo menos 8 caracteres.'], 422);
        }
        Setting::set('admin_password_hash', password_hash($new, PASSWORD_DEFAULT));
        $this->json(['ok' => true]);
    }

    /* ---------------- Escrita (admin) ---------------- */

    public function defaultPhases(): void
    {
        $this->requireAdmin();
        $this->json(['phases' => DEFAULT_PHASES]);
    }

    public function create(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $data = $this->body();

        $title = mb_substr(trim((string)($data['title'] ?? '')), 0, MAX_TITLE_LEN);
        $description = mb_substr(trim((string)($data['description'] ?? '')), 0, MAX_DESCRIPTION_LEN);
        $category = mb_substr(trim((string)($data['category'] ?? '')), 0, MAX_CATEGORY_LEN);
        $date = (string)($data['date'] ?? '');
        $startTime = (string)($data['start_time'] ?? '');
        $duration = (int)($data['duration'] ?? 0);

        if ($title === '' || !$this->isValidDate($date) || !$this->isValidTime($startTime)
            || $duration < 1 || $duration > MAX_DURATION_MINUTES) {
            $this->json(['error' => 'Preencha título, data, horário de início e duração (até 24h).'], 422);
        }

        $phasesIn = is_array($data['phases'] ?? null) ? $data['phases'] : DEFAULT_PHASES;
        $phasesIn = array_values(array_filter(array_map(function ($p) {
            return [
                'name' => mb_substr(trim((string)($p['name'] ?? '')), 0, MAX_TITLE_LEN),
                'weight' => (float)($p['weight'] ?? 0),
            ];
        }, array_slice($phasesIn, 0, MAX_PHASES)), fn($p) => $p['name'] !== ''));
        if (!$phasesIn) {
            $phasesIn = DEFAULT_PHASES;
        }

        $previews = Activity::buildPhasePreviews($date, $startTime, $duration, $phasesIn);
        $id = Activity::create(
            ['title' => $title, 'description' => $description, 'category' => $category, 'date' => $date],
            $previews
        );
        $this->json(['ok' => true, 'id' => $id]);
    }

    public function updateActivity(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $data = $this->body();
        $id = (int)($data['id'] ?? 0);
        $title = mb_substr(trim((string)($data['title'] ?? '')), 0, MAX_TITLE_LEN);
        if ($id < 1 || $title === '') {
            $this->json(['error' => 'Dados inválidos.'], 422);
        }
        Activity::updateMeta(
            $id,
            $title,
            mb_substr(trim((string)($data['description'] ?? '')), 0, MAX_DESCRIPTION_LEN),
            mb_substr(trim((string)($data['category'] ?? '')), 0, MAX_CATEGORY_LEN)
        );
        $this->json(['ok' => true]);
    }

    public function delete(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $id = (int)($this->body()['id'] ?? 0);
        if ($id < 1) {
            $this->json(['error' => 'Dados inválidos.'], 422);
        }
        Activity::delete($id);
        $this->json(['ok' => true]);
    }

    public function phase(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $data = $this->body();
        $id = (int)($data['id'] ?? 0);
        $op = (string)($data['op'] ?? '');
        $phase = $id > 0 ? Phase::find($id) : null;
        if (!$phase) {
            $this->json(['error' => 'Fase não encontrada.'], 404);
        }

        $now = date('Y-m-d H:i');
        switch ($op) {
            case 'start':
                Phase::setTimes($id, ['real_start' => $now]);
                break;
            case 'finish':
                $set = ['real_end' => $now];
                if (empty($phase['real_start'])) {
                    $set['real_start'] = $phase['prev_start'] ?: $now;
                }
                Phase::setTimes($id, $set);
                break;
            case 'undo':
                Phase::setTimes($id, ['real_start' => null, 'real_end' => null]);
                break;
            case 'set_times':
                $set = [];
                foreach (['real_start', 'real_end'] as $f) {
                    if (array_key_exists($f, $data)) {
                        $v = str_replace('T', ' ', trim((string)($data[$f] ?? '')));
                        if ($v !== '' && !$this->isValidDateTime($v)) {
                            $this->json(['error' => 'Horário inválido: use o formato AAAA-MM-DD HH:MM.'], 422);
                        }
                        $set[$f] = $v === '' ? null : $v;
                    }
                }
                Phase::setTimes($id, $set);
                break;
            default:
                $this->json(['error' => 'Operação inválida.'], 422);
        }
        $this->json(['ok' => true]);
    }

    /* ---------------- Validação ---------------- */

    private function dateParam(string $key): ?string
    {
        $v = (string)($_GET[$key] ?? '');
        return $this->isValidDate($v) ? $v : null;
    }

    private function isValidDate(string $v): bool
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) {
            return false;
        }
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
    }

    private function isValidTime(string $v): bool
    {
        return (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v);
    }

    private function isValidDateTime(string $v): bool
    {
        $parts = explode(' ', $v);
        return count($parts) === 2 && $this->isValidDate($parts[0]) && $this->isValidTime($parts[1]);
    }

    private function clientIp(): string
    {
        return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }
}
