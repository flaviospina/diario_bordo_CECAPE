<?php
defined('APP_RUNNING') or exit;
/** @var string $title  Título da página */
/** @var string $page   'login' ou 'panel' */
/** @var string $content HTML da view */
/** @var array|null $user Usuário logado (apenas na página panel) */
use App\Core\Csrf;

// Versiona os assets pela data de modificação: substituir o arquivo no
// servidor invalida automaticamente o cache dos navegadores.
$assetVersion = fn(string $file): string => (string)@filemtime(BASE_PATH . '/' . $file);
$user = $user ?? null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex">
  <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
  <title><?= e($title) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <!-- A fonte Inter é carregada de forma assíncrona pelo app.js:
       um stylesheet externo lento/inacessível não pode travar o painel -->
  <link rel="stylesheet" href="assets/style.css?v=<?= e($assetVersion('assets/style.css')) ?>">
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='22' fill='%230f2044'/><text x='50' y='68' font-size='50' text-anchor='middle' fill='%2322d3ee' font-family='sans-serif' font-weight='bold'>DB</text></svg>">
  <!-- app.js primeiro: o painel inicializa mesmo se o CDN estiver lento/fora -->
  <script defer src="assets/app.js?v=<?= e($assetVersion('assets/app.js')) ?>"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.2/dist/jspdf.plugin.autotable.min.js"></script>
</head>
<body data-page="<?= e($page) ?>"<?php if ($user): ?>
      data-role="<?= e($user['role']) ?>"
      data-user-id="<?= e((string)$user['id']) ?>"
      data-user-name="<?= e($user['name']) ?>"
      data-user-rm="<?= e($user['rm']) ?>"
      data-director="<?= e(DIRECTOR_NAME) ?>"
      data-director-role="<?= e(DIRECTOR_ROLE) ?>"
      data-default-phases="<?= e(json_encode(DEFAULT_PHASES, JSON_UNESCAPED_UNICODE)) ?>"<?php endif; ?>>
  <nav class="navbar no-print">
    <div class="navbar-brand">
      <div class="brand-logos">
        <?php foreach (LOGOS as $i => $logo): ?>
          <?php if ($i > 0): ?><span class="brand-divider"></span><?php endif; ?>
          <img src="<?= e(LOGO_BASE . '/' . $logo['file']) ?>" alt="<?= e($logo['alt']) ?>" class="brand-img">
        <?php endforeach; ?>
      </div>
      <span class="brand-title">Diário de Bordo</span>
    </div>

    <div class="navbar-actions">
      <?php if ($user): ?>
        <?php $badge = ['admin' => 'badge-orange', 'gestor' => 'badge-cyan', 'professor' => 'badge-purple'][$user['role']] ?? 'badge-cyan'; ?>
        <span class="user-badge"><?= e($user['name']) ?></span>
        <span class="badge <?= e($badge) ?>"><?= e(ROLE_LABELS[$user['role']] ?? $user['role']) ?></span>
        <form method="post" action="<?= e(url('logout')) ?>" class="logout-form">
          <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
          <button class="btn btn-exit" type="submit">Sair</button>
        </form>
      <?php endif; ?>
    </div>
  </nav>

<?= $content ?>

  <footer class="site-footer no-print">
    <div class="footer-inner">
      <span>Diário de Bordo · CECAPE</span>
      <span class="sep">|</span>
      <span>Registro de atividades em home office</span>
    </div>
  </footer>

  <div id="print-root"></div>
  <div id="toast" class="toast"></div>
</body>
</html>
