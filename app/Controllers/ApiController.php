<?php
declare(strict_types=1);

namespace App\Controllers;

defined('APP_RUNNING') or exit;

use App\Core\Controller;
use App\Core\Session;
use App\Models\Activity;
use App\Models\LoginAttempt;
use App\Models\Pausa;
use App\Models\Phase;
use App\Models\User;

/**
 * API JSON da aplicação.
 *
 * Todo acesso exige login (tela única para admin, gestão e professores).
 * Perfis:
 *  - admin: gerencia contas, registra os próprios apontamentos e consulta tudo;
 *  - gestor: consulta o diário de qualquer professor e gera relatórios;
 *  - professor: registra os próprios apontamentos e gera o próprio relatório.
 * Escritas exigem token CSRF no cabeçalho X-CSRF-Token.
 */
final class ApiController extends Controller
{
    /* ---------------- Sessão ---------------- */

    public function me(): void
    {
        if (!Session::isLogged()) {
            $this->json(['user' => null]);
        }
        $u = User::find((int)Session::userId());
        $this->json(['user' => $u ? User::publicView($u) : null]);
    }

    public function login(): void
    {
        $this->requireCsrf();
        $ip = $this->clientIp();
        if (LoginAttempt::isLocked($ip)) {
            $this->json(['error' => 'Muitas tentativas de login. Aguarde ' . LOGIN_WINDOW_MINUTES . ' minutos.'], 429);
        }
        $data = $this->body();
        $username = strtolower(trim((string)($data['username'] ?? '')));
        $pass = (string)($data['password'] ?? '');
        $user = $username !== '' ? User::findByUsername($username) : null;
        if (!$user || $pass === '' || !password_verify($pass, $user['password_hash'])) {
            LoginAttempt::recordFailure($ip);
            $this->json(['error' => 'Usuário ou senha incorretos.'], 401);
        }
        LoginAttempt::clear($ip);
        Session::loginUser((int)$user['id'], (string)$user['role']);
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            User::update((int)$user['id'], ['password_hash' => password_hash($pass, PASSWORD_DEFAULT)]);
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
        $this->requireLogin();
        $this->requireCsrf();
        $data = $this->body();
        $me = User::find((int)Session::userId());
        $current = (string)($data['current'] ?? '');
        $new = (string)($data['new'] ?? '');
        if (!$me || !password_verify($current, $me['password_hash'])) {
            $this->json(['error' => 'A senha atual está incorreta.'], 422);
        }
        if (mb_strlen($new) < 8) {
            $this->json(['error' => 'A nova senha deve ter pelo menos 8 caracteres.'], 422);
        }
        User::update((int)$me['id'], ['password_hash' => password_hash($new, PASSWORD_DEFAULT)]);
        $this->json(['ok' => true]);
    }

    /* ---------------- Consulta ---------------- */

    /** Professores disponíveis para consulta (admin/gestão veem todos). */
    public function professors(): void
    {
        $this->requireLogin();
        if (Session::role() === 'professor') {
            $me = User::find((int)Session::userId());
            $this->json(['professors' => $me ? [User::publicView($me)] : []]);
        }
        $this->json(['professors' => array_map([User::class, 'publicView'], User::professorCapable())]);
    }

    public function list(): void
    {
        $this->requireLogin();
        $target = $this->resolveTargetUser();
        if (!$target) {
            $this->json(['professor' => null, 'activities' => [], 'breaks' => []]);
        }
        $from = $this->dateParam('from');
        $to = $this->dateParam('to');
        $q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100) ?: null;
        $this->json([
            'professor' => User::publicView($target),
            'activities' => Activity::allWithPhases((int)$target['id'], $from, $to, $q),
            'breaks' => Pausa::forUser((int)$target['id'], $from, $to),
        ]);
    }

    /** Modelo de fases do usuário logado (pré-preenche o formulário). */
    public function defaultPhases(): void
    {
        $this->requireProfessorCapable();
        $me = User::find((int)Session::userId());
        $this->json(['phases' => $me ? User::phases($me) : DEFAULT_PHASES]);
    }

    /* ---------------- Apontamentos (dono) ---------------- */

    public function create(): void
    {
        $this->requireProfessorCapable();
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

        $me = User::find((int)Session::userId());
        $phasesIn = $this->sanitizePhases($data['phases'] ?? null) ?: User::phases($me);

        // A previsão respeita os descansos já registrados para o dia
        $breaks = Pausa::forUser((int)Session::userId(), $date, $date);
        $previews = Activity::buildPhasePreviews($date, $startTime, $duration, $phasesIn, $breaks);
        $id = Activity::create(
            (int)Session::userId(),
            ['title' => $title, 'description' => $description, 'category' => $category, 'date' => $date],
            $previews
        );
        $this->json(['ok' => true, 'id' => $id]);
    }

    public function updateActivity(): void
    {
        $this->requireProfessorCapable();
        $this->requireCsrf();
        $data = $this->body();
        $activity = $this->ownActivityOr404((int)($data['id'] ?? 0));
        $title = mb_substr(trim((string)($data['title'] ?? '')), 0, MAX_TITLE_LEN);
        if ($title === '') {
            $this->json(['error' => 'Informe o título.'], 422);
        }
        Activity::updateMeta(
            (int)$activity['id'],
            $title,
            mb_substr(trim((string)($data['description'] ?? '')), 0, MAX_DESCRIPTION_LEN),
            mb_substr(trim((string)($data['category'] ?? '')), 0, MAX_CATEGORY_LEN)
        );
        $this->json(['ok' => true]);
    }

    public function delete(): void
    {
        $this->requireProfessorCapable();
        $this->requireCsrf();
        $activity = $this->ownActivityOr404((int)($this->body()['id'] ?? 0));
        Activity::delete((int)$activity['id']);
        $this->json(['ok' => true]);
    }

    public function phase(): void
    {
        $this->requireProfessorCapable();
        $this->requireCsrf();
        $data = $this->body();
        $id = (int)($data['id'] ?? 0);
        $op = (string)($data['op'] ?? '');
        $phase = $id > 0 ? Phase::find($id) : null;
        if ($phase) {
            $this->ownActivityOr404((int)$phase['activity_id']);
        }
        if (!$phase) {
            $this->json(['error' => 'Fase não encontrada.'], 404);
        }

        $now = date('Y-m-d H:i');
        // Durante o descanso não é possível registrar nada
        if (in_array($op, ['start', 'finish'], true)) {
            $this->rejectIfInBreak($now, 'Você está em horário de descanso');
        }
        switch ($op) {
            case 'start':
                Phase::setTimes($id, ['real_start' => $now]);
                break;
            case 'finish':
                $set = ['real_end' => $now];
                if (empty($phase['real_start'])) {
                    $set['real_start'] = $phase['prev_start'] ?: $now;
                }
                $this->assertTimeOrder($phase, $set);
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
                        if ($v !== '') {
                            $this->rejectIfInBreak($v, 'O horário informado cai no descanso');
                        }
                        $set[$f] = $v === '' ? null : $v;
                    }
                }
                $this->assertTimeOrder($phase, $set);
                Phase::setTimes($id, $set);
                break;
            default:
                $this->json(['error' => 'Operação inválida.'], 422);
        }
        $this->json(['ok' => true]);
    }

    /* ---------------- Descansos (almoço/janta) ---------------- */

    public function breakCreate(): void
    {
        $this->requireProfessorCapable();
        $this->requireCsrf();
        $data = $this->body();
        $date = (string)($data['date'] ?? '');
        $type = (string)($data['type'] ?? '');
        $start = (string)($data['start'] ?? '');
        $end = (string)($data['end'] ?? '');
        if (!$this->isValidDate($date) || !isset(BREAK_TYPES[$type])
            || !$this->isValidTime($start) || !$this->isValidTime($end) || $end <= $start) {
            $this->json(['error' => 'Informe data, tipo e um intervalo válido (término após o início).'], 422);
        }
        // Descansos do mesmo dia não podem se sobrepor
        foreach (Pausa::forUser((int)Session::userId(), $date, $date) as $b) {
            if ($start < $b['end_time'] && $end > $b['start_time']) {
                $label = BREAK_TYPES[$b['type']] ?? 'Descanso';
                $this->json(['error' => "Conflito com um descanso já registrado ($label {$b['start_time']}–{$b['end_time']})."], 422);
            }
        }
        $id = Pausa::create((int)Session::userId(), $date, $type, $start, $end);
        $this->json(['ok' => true, 'id' => $id]);
    }

    public function breakDelete(): void
    {
        $this->requireProfessorCapable();
        $this->requireCsrf();
        $id = (int)($this->body()['id'] ?? 0);
        $pausa = $id > 0 ? Pausa::find($id) : null;
        if (!$pausa || (int)$pausa['user_id'] !== (int)Session::userId()) {
            $this->json(['error' => 'Descanso não encontrado.'], 404);
        }
        Pausa::delete($id);
        $this->json(['ok' => true]);
    }

    /* ---------------- Contas (somente admin) ---------------- */

    public function users(): void
    {
        $this->requireAdmin();
        $this->json(['users' => array_map([User::class, 'publicView'], User::all())]);
    }

    public function userCreate(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $data = $this->body();
        $username = strtolower(trim((string)($data['username'] ?? '')));
        $name = mb_substr(trim((string)($data['name'] ?? '')), 0, MAX_TITLE_LEN);
        $rm = mb_substr(trim((string)($data['rm'] ?? '')), 0, 30);
        $password = (string)($data['password'] ?? '');

        if ($name === '' || !preg_match('/^[a-z0-9._-]{3,30}$/', $username)) {
            $this->json(['error' => 'Informe o nome e um usuário válido (3–30 letras minúsculas, números, ponto, hífen).'], 422);
        }
        if (mb_strlen($password) < 8) {
            $this->json(['error' => 'A senha inicial deve ter pelo menos 8 caracteres.'], 422);
        }
        if (User::findByUsername($username) || $this->usernameExists($username)) {
            $this->json(['error' => 'Este nome de usuário já está em uso.'], 422);
        }
        $phases = $this->sanitizePhases($data['phases'] ?? null) ?: DEFAULT_PHASES;

        $id = User::create($username, $name, $rm, 'professor', $phases, password_hash($password, PASSWORD_DEFAULT));
        $this->json(['ok' => true, 'id' => $id]);
    }

    public function userUpdate(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $data = $this->body();
        $id = (int)($data['id'] ?? 0);
        $user = $id > 0 ? User::find($id) : null;
        if (!$user) {
            $this->json(['error' => 'Conta não encontrada.'], 404);
        }

        $fields = [];
        if (array_key_exists('name', $data)) {
            $name = mb_substr(trim((string)$data['name']), 0, MAX_TITLE_LEN);
            if ($name === '') {
                $this->json(['error' => 'O nome não pode ficar vazio.'], 422);
            }
            $fields['name'] = $name;
        }
        if (array_key_exists('rm', $data)) {
            $fields['rm'] = mb_substr(trim((string)$data['rm']), 0, 30);
        }
        if (array_key_exists('phases', $data) && $user['role'] !== 'gestor') {
            $phases = $this->sanitizePhases($data['phases']);
            if ($phases) {
                $fields['phases_json'] = json_encode($phases, JSON_UNESCAPED_UNICODE);
            }
        }
        if (!empty($data['password'])) {
            if (mb_strlen((string)$data['password']) < 8) {
                $this->json(['error' => 'A nova senha deve ter pelo menos 8 caracteres.'], 422);
            }
            $fields['password_hash'] = password_hash((string)$data['password'], PASSWORD_DEFAULT);
        }
        if (array_key_exists('active', $data)) {
            if ((int)$user['id'] === (int)Session::userId() && !(int)$data['active']) {
                $this->json(['error' => 'Você não pode desativar a própria conta.'], 422);
            }
            $fields['active'] = (int)(bool)$data['active'];
        }
        User::update($id, $fields);
        $this->json(['ok' => true]);
    }

    /* ---------------- Apoio ---------------- */

    /** Professor cujo diário será consultado (respeitando o perfil). */
    private function resolveTargetUser(): ?array
    {
        $requested = (int)($_GET['user_id'] ?? 0);
        $me = User::find((int)Session::userId());
        if (!$me) {
            return null;
        }
        if (Session::role() === 'professor' || $requested === 0 || $requested === (int)$me['id']) {
            if ($me['role'] === 'gestor') {
                // Gestão sem professor selecionado: usa o primeiro disponível
                $capable = User::professorCapable();
                return $capable[0] ?? null;
            }
            return $me;
        }
        $target = User::find($requested);
        return ($target && $target['role'] !== 'gestor') ? $target : null;
    }

    /** O término real deve ser depois do início real. */
    private function assertTimeOrder(array $phase, array $set): void
    {
        $eff = array_merge($phase, $set);
        if (!empty($eff['real_start']) && !empty($eff['real_end']) && $eff['real_end'] <= $eff['real_start']) {
            $this->json(['error' => 'O término real deve ser depois do início real da etapa.'], 422);
        }
    }

    /** Recusa a operação se o horário cair dentro de um descanso do usuário. */
    private function rejectIfInBreak(string $datetime, string $prefix): void
    {
        $date = substr($datetime, 0, 10);
        foreach (Pausa::forUser((int)Session::userId(), $date, $date) as $b) {
            $s = "$date {$b['start_time']}";
            $e = "$date {$b['end_time']}";
            if ($datetime >= $s && $datetime < $e) {
                $label = BREAK_TYPES[$b['type']] ?? 'Descanso';
                $this->json(['error' => "$prefix ($label {$b['start_time']}–{$b['end_time']}). Nenhum registro é permitido neste intervalo."], 422);
            }
        }
    }

    private function ownActivityOr404(int $id): array
    {
        $activity = $id > 0 ? Activity::find($id) : null;
        if (!$activity || (int)$activity['user_id'] !== (int)Session::userId()) {
            $this->json(['error' => 'Atividade não encontrada.'], 404);
        }
        return $activity;
    }

    /** Normaliza a lista de fases enviada pelo front-end. */
    private function sanitizePhases(mixed $phasesIn): array
    {
        if (!is_array($phasesIn)) {
            return [];
        }
        return array_values(array_filter(array_map(function ($p) {
            return [
                'name' => mb_substr(trim((string)($p['name'] ?? '')), 0, MAX_TITLE_LEN),
                'weight' => (float)($p['weight'] ?? 0),
            ];
        }, array_slice($phasesIn, 0, MAX_PHASES)), fn($p) => $p['name'] !== ''));
    }

    private function usernameExists(string $username): bool
    {
        foreach (User::all() as $u) {
            if ($u['username'] === $username) {
                return true;
            }
        }
        return false;
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
