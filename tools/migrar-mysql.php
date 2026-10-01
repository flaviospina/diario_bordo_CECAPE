<?php
/**
 * Diário de Bordo CECAPE — diagnóstico e migração SQLite → MySQL (sem terminal).
 *
 * Quando usar: o sistema está gravando no SQLite (data/*.sqlite) e você quer
 * que ele passe a usar o MySQL do phpMyAdmin, levando os dados junto.
 *
 * Como usar (tudo pelo Gerenciador de Arquivos do cPanel):
 *  1. Garanta que app/Config/config.local.php existe com DB_MYSQL_HOST, NAME,
 *     USER e PASS preenchidos (copie de config.local.php.example e edite).
 *  2. Dentro da pasta "data" do sistema, crie um arquivo vazio chamado
 *        liberar-migracao.txt
 *     (prova de que você tem acesso ao servidor — sem ele o script não roda).
 *  3. Abra https://cecapescs.com.br/diariobordo/tools/migrar-mysql.php
 *     Leia o diagnóstico (SQLite × MySQL: contas, apontamentos e datas mais
 *     recentes) e clique no link de migração indicado. Nada é alterado até
 *     você clicar.
 *  4. Ao terminar, o script apaga o marcador e a si mesmo. O arquivo .sqlite
 *     é renomeado para .importado-<data> e fica como backup.
 */
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

$root = is_file(__DIR__ . '/app/bootstrap.php') ? __DIR__ : dirname(__DIR__);
if (!is_file($root . '/app/bootstrap.php')) {
    http_response_code(500);
    exit('Não encontrei app/bootstrap.php. Este arquivo deve ficar em diariobordo/tools/ (ou na raiz do sistema).');
}
$marker = $root . '/data/liberar-migracao.txt';
$self = htmlspecialchars((string)($_SERVER['PHP_SELF'] ?? ''), ENT_QUOTES);

echo '<!doctype html><meta charset="utf-8"><title>Migração SQLite → MySQL</title>'
   . '<style>body{font:15px/1.5 system-ui,sans-serif;max-width:860px;margin:30px auto;padding:0 16px;color:#1a1a1a}'
   . 'table{border-collapse:collapse;margin:12px 0}td,th{border:1px solid #cbd5e1;padding:6px 12px;text-align:left}'
   . 'th{background:#eef2f7}.ok{color:#15803d}.err{color:#b91c1c}.warn{color:#b45309}code{background:#f1f5f9;padding:1px 5px}'
   . '.btn{display:inline-block;margin:8px 8px 8px 0;padding:10px 16px;background:#1d4ed8;color:#fff;border-radius:6px;text-decoration:none}'
   . '.btn.danger{background:#b91c1c}</style><h1>Migração SQLite → MySQL</h1>';

if (!is_file($marker)) {
    http_response_code(403);
    exit('<p class="err"><b>Bloqueado.</b> Para liberar, crie um arquivo vazio chamado <code>liberar-migracao.txt</code> dentro da pasta '
        . '<code>' . htmlspecialchars($root, ENT_QUOTES) . '/data/</code> e recarregue esta página.</p>'
        . '<p>Esse passo garante que só quem tem acesso ao servidor consegue migrar o banco.</p>');
}

require $root . '/app/bootstrap.php';

use App\Core\Database;

/* ---------- 1. config.local.php ---------- */
$cfg = $root . '/app/Config/config.local.php';
echo '<h2>1. Configuração</h2>';
if (!is_file($cfg)) {
    exit('<p class="err"><b>app/Config/config.local.php não existe</b> — por isso o sistema está usando o SQLite.</p>'
        . '<p>No Gerenciador de Arquivos: entre em <code>app/Config/</code>, copie <code>config.local.php.example</code> para '
        . '<code>config.local.php</code>, abra com <b>Editar</b> e preencha (sem as barras <code>//</code> no início):</p>'
        . '<pre>define(\'DB_MYSQL_HOST\', \'localhost\');
define(\'DB_MYSQL_NAME\', \'nome_do_banco\');   // o mesmo que aparece no phpMyAdmin
define(\'DB_MYSQL_USER\', \'usuario_do_banco\');
define(\'DB_MYSQL_PASS\', \'senha_do_banco\');</pre>'
        . '<p>Os três dados vêm de <b>cPanel → MySQL® Databases</b> (o usuário precisa ter TODOS os privilégios no banco). Depois recarregue esta página.</p>');
}
if (!Database::isMysql()) {
    exit('<p class="err"><b>config.local.php existe, mas não define DB_MYSQL_HOST, DB_MYSQL_NAME e DB_MYSQL_USER</b> — confira se as linhas estão sem <code>//</code> no início e com os nomes exatos.</p>');
}
echo '<p class="ok">config.local.php encontrado: MySQL <code>' . htmlspecialchars(DB_MYSQL_NAME, ENT_QUOTES) . '</code> em <code>' . htmlspecialchars(DB_MYSQL_HOST, ENT_QUOTES) . '</code>, usuário <code>' . htmlspecialchars(DB_MYSQL_USER, ENT_QUOTES) . '</code>.</p>';

/* ---------- 2. SQLite ---------- */
$tables = ['users' => 'contas', 'activities' => 'atividades', 'phases' => 'etapas', 'phase_pauses' => 'pausas',
           'breaks' => 'descansos', 'work_schedules' => 'jornadas', 'hour_bank' => 'banco de horas',
           'medical_leaves' => 'saúde', 'time_clock' => 'pontos'];
$recent = ['activities' => 'date', 'time_clock' => 'date', 'medical_leaves' => 'date'];

function counts(PDO $pdo, array $tables, array $recent, bool $mysql): array
{
    $out = [];
    foreach ($tables as $t => $label) {
        try {
            $q = $mysql ? "`$t`" : $t;
            $n = (int)$pdo->query("SELECT COUNT(*) FROM $q")->fetchColumn();
            $last = isset($recent[$t]) ? (string)($pdo->query("SELECT MAX({$recent[$t]}) FROM $q")->fetchColumn() ?: '') : '';
            $out[$t] = [$n, $last];
        } catch (Throwable) {
            $out[$t] = [null, ''];
        }
    }
    return $out;
}

echo '<h2>2. Dados</h2>';
$dir = $root . '/data';
$sqliteFile = null;
if (is_file("$dir/dbname.php")) {
    $name = (string)require "$dir/dbname.php";
    if ($name !== '' && is_file("$dir/$name")) {
        $sqliteFile = "$dir/$name";
    }
}
if ($sqliteFile === null) {
    foreach (glob("$dir/*.sqlite") ?: [] as $f) { $sqliteFile = $f; break; }
}
$sq = null;
if ($sqliteFile) {
    $sq = counts(new PDO('sqlite:' . $sqliteFile, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]), $tables, $recent, false);
}

try {
    $my = new PDO('mysql:host=' . DB_MYSQL_HOST . ';dbname=' . DB_MYSQL_NAME . ';charset=utf8mb4', DB_MYSQL_USER,
                  defined('DB_MYSQL_PASS') ? DB_MYSQL_PASS : '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Throwable $e) {
    exit('<p class="err"><b>Não conectou ao MySQL:</b> ' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>'
        . '<p>Confira em cPanel → MySQL® Databases: nome do banco e do usuário (com o prefixo da conta), senha, e se o usuário está adicionado ao banco com todos os privilégios. Corrija o config.local.php e recarregue.</p>');
}
$myCounts = counts($my, $tables, $recent, true);
$mysqlHasUsers = ($myCounts['users'][0] ?? 0) > 0;

echo '<table><tr><th>Tabela</th><th>SQLite (data/)</th><th>MySQL (phpMyAdmin)</th></tr>';
foreach ($tables as $t => $label) {
    $s = $sq[$t] ?? [null, ''];
    $m = $myCounts[$t];
    $fmt = fn($c) => $c[0] === null ? '<span class="warn">—</span>' : $c[0] . ($c[1] !== '' ? " <small>(último: {$c[1]})</small>" : '');
    echo "<tr><td>$label <small>($t)</small></td><td>{$fmt($s)}</td><td>{$fmt($m)}</td></tr>";
}
echo '</table>';
echo $sqliteFile ? '<p>Arquivo SQLite: <code>' . htmlspecialchars(basename($sqliteFile), ENT_QUOTES) . '</code></p>'
                 : '<p class="warn">Nenhum arquivo .sqlite em data/ — não há o que migrar (o sistema já pode estar no MySQL).</p>';

/* ---------- 3. Ação ---------- */
echo '<h2>3. Migração</h2>';
$acao = (string)($_GET['acao'] ?? '');
if ($acao === 'migrar' && $sqliteFile) {
    $substituir = (string)($_GET['substituir'] ?? '') === '1';
    if ($mysqlHasUsers && !$substituir) {
        exit('<p class="err">O MySQL já tem dados. Para substituí-los pelos do SQLite, use o link "Substituir" abaixo — ou cancele.</p>'
            . "<a class=\"btn danger\" href=\"$self?acao=migrar&substituir=1\">Substituir o MySQL pelos dados do SQLite</a>");
    }
    try {
        if ($mysqlHasUsers) {
            // Esvazia as tabelas existentes para o import automático rodar
            $existing = $my->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()")->fetchAll(PDO::FETCH_COLUMN);
            $my->exec('SET FOREIGN_KEY_CHECKS = 0');
            foreach (array_keys($tables + ['login_attempts' => '']) as $t) {
                if (in_array($t, $existing, true)) {
                    $my->exec("DELETE FROM `$t`");
                }
            }
            $my->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
        $my = null;
        // A conexão do sistema cria as tabelas e, com o MySQL vazio, importa o SQLite
        $pdo = Database::pdo();
        $after = counts($pdo, $tables, $recent, true);
        $ok = ($after['users'][0] ?? 0) > 0;
        echo $ok ? '<p class="ok"><b>Migração concluída.</b> O sistema agora usa o MySQL.</p>'
                 : '<p class="err">A importação não aconteceu — veja o log de erros do PHP no cPanel.</p>';
        echo '<table><tr><th>Tabela</th><th>MySQL agora</th></tr>';
        foreach ($tables as $t => $label) {
            echo "<tr><td>$label</td><td>" . ($after[$t][0] ?? '—') . '</td></tr>';
        }
        echo '</table>';
        $bk = glob("$dir/*.importado-*") ?: [];
        if ($bk) {
            echo '<p>Backup do SQLite: <code>' . htmlspecialchars(basename(end($bk)), ENT_QUOTES) . '</code> (pode ser baixado e guardado; o sistema não o usa mais).</p>';
        }
        echo '<p><a class="btn" href="' . htmlspecialchars(dirname($self) === '/' ? '/' : dirname(dirname($self)) . '/', ENT_QUOTES) . '">Abrir o sistema</a> — o rodapé, para o administrador, deve mostrar <b>Banco: MySQL</b>.</p>';
    } catch (Throwable $e) {
        echo '<p class="err"><b>Erro na migração:</b> ' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>';
    } finally {
        @unlink($marker);
        echo @unlink(__FILE__) ? '<p>O marcador e este script foram apagados do servidor.</p>'
                               : '<p class="warn">Não consegui apagar este script — exclua tools/migrar-mysql.php manualmente agora.</p>';
    }
    exit;
}

if (!$sqliteFile) {
    @unlink($marker);
    exit('<p>Nada a migrar. Marcador removido.</p>');
}
if (!$mysqlHasUsers) {
    echo '<p>O MySQL está <b>vazio</b>: a migração copia tudo do SQLite para ele e renomeia o .sqlite como backup.</p>'
       . "<a class=\"btn\" href=\"$self?acao=migrar\">Migrar SQLite → MySQL</a>";
} else {
    $sLast = $sq['activities'][1] ?? '';
    $mLast = $myCounts['activities'][1] ?? '';
    echo '<p class="warn">O MySQL <b>já tem dados</b> (provavelmente de uma migração anterior). Compare as colunas acima — em especial a data do <i>último</i> registro: '
       . 'SQLite = <b>' . htmlspecialchars($sLast ?: '—', ENT_QUOTES) . '</b>, MySQL = <b>' . htmlspecialchars($mLast ?: '—', ENT_QUOTES) . '</b>.</p>'
       . '<p>Se o SQLite é o mais recente (o sistema vinha gravando nele), <b>substitua</b>: o conteúdo atual do MySQL é apagado e trocado pelo do SQLite. '
       . 'Se preferir manter o MySQL como está, apenas remova o arquivo <code>liberar-migracao.txt</code> e nada muda — mas aí o sistema continua no SQLite até você renomear o .sqlite.</p>'
       . "<a class=\"btn danger\" href=\"$self?acao=migrar&substituir=1\">Substituir o MySQL pelos dados do SQLite</a>";
}
