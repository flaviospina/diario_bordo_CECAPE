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

  <!-- Informações do sistema -->
  <meta name="description" content="<?= e(APP_DESCRIPTION) ?>">
  <meta name="application-name" content="<?= e(APP_NAME) ?>">
  <meta name="author" content="CECAPE — São Caetano do Sul">
  <meta name="theme-color" content="#0b1628">

  <!-- Open Graph: prévia do link no WhatsApp, Facebook, Instagram e Telegram -->
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="<?= e(APP_NAME) ?>">
  <meta property="og:title" content="<?= e(APP_NAME) ?>">
  <meta property="og:description" content="<?= e(APP_DESCRIPTION) ?>">
  <meta property="og:url" content="<?= e(APP_URL) ?>/">
  <meta property="og:image" content="<?= e(APP_URL) ?>/assets/og-image.jpg">
  <meta property="og:image:secure_url" content="<?= e(APP_URL) ?>/assets/og-image.jpg">
  <meta property="og:image:type" content="image/jpeg">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:image:alt" content="Diário de Bordo CECAPE — registro de atividades em home office">
  <meta property="og:locale" content="pt_BR">

  <!-- Twitter/X Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= e(APP_NAME) ?>">
  <meta name="twitter:description" content="<?= e(APP_DESCRIPTION) ?>">
  <meta name="twitter:image" content="<?= e(APP_URL) ?>/assets/og-image.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <!-- A fonte Inter é carregada de forma assíncrona pelo app.js:
       um stylesheet externo lento/inacessível não pode travar o painel -->
  <link rel="stylesheet" href="assets/style.css?v=<?= e($assetVersion('assets/style.css')) ?>">
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='22' fill='%230f2044'/><text x='50' y='68' font-size='50' text-anchor='middle' fill='%2322d3ee' font-family='sans-serif' font-weight='bold'>DB</text></svg>">
  <link rel="apple-touch-icon" sizes="180x180" href="assets/apple-touch-icon.png">
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
      <?php if ($user && $user['role'] === 'admin'): ?>
        <span class="sep">|</span>
        <span>Banco: <?= \App\Core\Database::isMysql() ? 'MySQL' : 'SQLite' ?></span>
      <?php endif; ?>
    </div>
  </footer>

  <div id="print-root"></div>
  <div id="toast" class="toast"></div>
</body>
</html>
