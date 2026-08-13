<?php
/**
 * Diário de Bordo CECAPE — API
 *
 * Leitura (action=list) é pública: o diretor consulta sem senha.
 * Toda escrita exige sessão de administrador (login com senha).
 */
require_once __DIR__ . '/db.php';

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
header('Content-Type: application/json; charset=utf-8');

function is_admin(): bool
{
    return !empty($_SESSION['is_admin']);
}

function json_out($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function require_admin(): void
{
    if (!is_admin()) {
        json_out(['error' => 'Acesso restrito ao administrador.'], 401);
    }
}

/** Deriva o status de uma atividade a partir das fases. */
function activity_status(array $phases): string
{
    if (!$phases) {
        return 'prevista';
    }
    $allDone = true;
    $anyStarted = false;
    foreach ($phases as $p) {
        if (!empty($p['real_start'])) {
            $anyStarted = true;
        }
        if (empty($p['real_end'])) {
            $allDone = false;
        }
    }
    if ($allDone) {
        return 'concluida';
    }
    return $anyStarted ? 'em_andamento' : 'prevista';
}

function fetch_activities(?string $from, ?string $to, ?string $q): array
{
    $sql = 'SELECT * FROM activities WHERE 1=1';
    $params = [];
    if ($from) {
        $sql .= ' AND date >= :from';
        $params[':from'] = $from;
    }
    if ($to) {
        $sql .= ' AND date <= :to';
        $params[':to'] = $to;
    }
    if ($q) {
        $sql .= ' AND (title LIKE :q OR description LIKE :q OR category LIKE :q)';
        $params[':q'] = '%' . $q . '%';
    }
    $sql .= ' ORDER BY date DESC, prev_start ASC, id ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $activities = $stmt->fetchAll();

    $phStmt = db()->prepare('SELECT * FROM phases WHERE activity_id = :id ORDER BY ord, id');
    foreach ($activities as &$a) {
        $phStmt->execute([':id' => $a['id']]);
        $a['phases'] = $phStmt->fetchAll();
        $a['status'] = activity_status($a['phases']);
        $starts = array_filter(array_column($a['phases'], 'real_start'));
        $ends = array_column($a['phases'], 'real_end');
        $a['real_start'] = $starts ? min($starts) : null;
        $a['real_end'] = ($ends && !in_array(null, $ends, true) && !in_array('', $ends, true)) ? max($ends) : null;
    }
    return $activities;
}

/**
 * Gera a previsão de cada fase distribuindo a duração total pelos pesos,
 * em sequência a partir do horário de início informado.
 */
function build_phase_previews(string $date, string $startTime, int $durationMin, array $phases): array
{
    $totalWeight = 0;
    foreach ($phases as $p) {
        $totalWeight += max(0, (float)($p['weight'] ?? 0));
    }
    if ($totalWeight <= 0) {
        $totalWeight = count($phases);
        foreach ($phases as &$p) {
            $p['weight'] = 1;
        }
        unset($p);
    }

    $cursor = new DateTime("$date $startTime");
    $out = [];
    $n = count($phases);
    $used = 0;
    foreach ($phases as $i => $p) {
        // Última fase recebe o restante para fechar exatamente a duração total
        $mins = ($i === $n - 1)
            ? $durationMin - $used
            : (int)round($durationMin * ((float)$p['weight'] / $totalWeight));
        $mins = max(1, $mins);
        $used += $mins;
        $start = clone $cursor;
        $cursor->modify("+{$mins} minutes");
        $out[] = [
            'name' => trim((string)$p['name']),
            'ord' => $i,
            'prev_start' => $start->format('Y-m-d H:i'),
            'prev_end' => $cursor->format('Y-m-d H:i'),
        ];
    }
    return $out;
}

$action = $_GET['action'] ?? '';

switch ($action) {

    case 'me':
        json_out(['admin' => is_admin()]);

    case 'login':
        $data = body();
        $pass = (string)($data['password'] ?? '');
        if ($pass !== '' && password_verify($pass, ADMIN_PASSWORD_HASH)) {
            session_regenerate_id(true);
            $_SESSION['is_admin'] = true;
            json_out(['ok' => true]);
        }
        json_out(['error' => 'Senha incorreta.'], 401);

    case 'logout':
        session_destroy();
        json_out(['ok' => true]);

    case 'list':
        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;
        $q = trim((string)($_GET['q'] ?? '')) ?: null;
        json_out([
            'app' => APP_NAME,
            'owner' => APP_OWNER,
            'activities' => fetch_activities($from, $to, $q),
        ]);

    case 'default_phases':
        json_out(['phases' => DEFAULT_PHASES]);

    case 'create':
        require_admin();
        $data = body();
        $title = trim((string)($data['title'] ?? ''));
        $date = (string)($data['date'] ?? '');
        $startTime = (string)($data['start_time'] ?? '');
        $duration = (int)($data['duration'] ?? 0);
        $phasesIn = $data['phases'] ?? DEFAULT_PHASES;

        if ($title === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            || !preg_match('/^\d{2}:\d{2}$/', $startTime) || $duration < 1) {
            json_out(['error' => 'Preencha título, data, horário de início e duração.'], 422);
        }
        $phasesIn = array_values(array_filter($phasesIn, fn($p) => trim((string)($p['name'] ?? '')) !== ''));
        if (!$phasesIn) {
            $phasesIn = DEFAULT_PHASES;
        }

        $previews = build_phase_previews($date, $startTime, $duration, $phasesIn);

        $pdo = db();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO activities (title, description, category, date, prev_start, prev_end, created_at)
                               VALUES (:t, :d, :c, :dt, :ps, :pe, :ca)');
        $stmt->execute([
            ':ca' => date('Y-m-d H:i:s'),
            ':t' => $title,
            ':d' => trim((string)($data['description'] ?? '')),
            ':c' => trim((string)($data['category'] ?? '')),
            ':dt' => $date,
            ':ps' => $previews[0]['prev_start'],
            ':pe' => $previews[count($previews) - 1]['prev_end'],
        ]);
        $activityId = (int)$pdo->lastInsertId();
        $ph = $pdo->prepare('INSERT INTO phases (activity_id, name, ord, prev_start, prev_end)
                             VALUES (:a, :n, :o, :ps, :pe)');
        foreach ($previews as $p) {
            $ph->execute([':a' => $activityId, ':n' => $p['name'], ':o' => $p['ord'],
                          ':ps' => $p['prev_start'], ':pe' => $p['prev_end']]);
        }
        $pdo->commit();
        json_out(['ok' => true, 'id' => $activityId]);

    case 'update_activity':
        require_admin();
        $data = body();
        $id = (int)($data['id'] ?? 0);
        $stmt = db()->prepare('UPDATE activities SET title = :t, description = :d, category = :c WHERE id = :id');
        $stmt->execute([
            ':t' => trim((string)($data['title'] ?? '')),
            ':d' => trim((string)($data['description'] ?? '')),
            ':c' => trim((string)($data['category'] ?? '')),
            ':id' => $id,
        ]);
        json_out(['ok' => true]);

    case 'delete':
        require_admin();
        $id = (int)(body()['id'] ?? 0);
        db()->prepare('DELETE FROM activities WHERE id = :id')->execute([':id' => $id]);
        json_out(['ok' => true]);

    case 'phase':
        require_admin();
        $data = body();
        $id = (int)($data['id'] ?? 0);
        $op = (string)($data['op'] ?? '');
        $stmt = db()->prepare('SELECT * FROM phases WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $phase = $stmt->fetch();
        if (!$phase) {
            json_out(['error' => 'Fase não encontrada.'], 404);
        }
        $now = date('Y-m-d H:i');
        $set = [];
        switch ($op) {
            case 'start':
                $set = ['real_start' => $now];
                break;
            case 'finish':
                $set = ['real_end' => $now];
                if (empty($phase['real_start'])) {
                    $set['real_start'] = $phase['prev_start'] ?: $now;
                }
                break;
            case 'undo':
                $set = ['real_start' => null, 'real_end' => null];
                break;
            case 'set_times':
                foreach (['real_start', 'real_end'] as $f) {
                    if (array_key_exists($f, $data)) {
                        $v = trim((string)($data[$f] ?? ''));
                        $set[$f] = $v === '' ? null : str_replace('T', ' ', $v);
                    }
                }
                break;
            default:
                json_out(['error' => 'Operação inválida.'], 422);
        }
        $cols = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($set)));
        $params = [':id' => $id];
        foreach ($set as $k => $v) {
            $params[":$k"] = $v;
        }
        db()->prepare("UPDATE phases SET $cols WHERE id = :id")->execute($params);
        json_out(['ok' => true]);

    default:
        json_out(['error' => 'Ação desconhecida.'], 404);
}
