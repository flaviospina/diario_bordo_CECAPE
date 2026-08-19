<?php
defined('APP_RUNNING') or exit;
/** @var string $title  Título da página */
/** @var string $page   'diario' ou 'admin' */
/** @var string $content HTML da view */
use App\Core\Csrf;

// Versiona os assets pela data de modificação: substituir o arquivo no
// servidor invalida automaticamente o cache dos navegadores.
$assetVersion = fn(string $file): string => (string)@filemtime(BASE_PATH . '/' . $file);
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
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap">
  <link rel="stylesheet" href="assets/style.css?v=<?= e($assetVersion('assets/style.css')) ?>">
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='22' fill='%230f2044'/><text x='50' y='68' font-size='50' text-anchor='middle' fill='%2322d3ee' font-family='sans-serif' font-weight='bold'>DB</text></svg>">
  <script defer src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.2/dist/jspdf.plugin.autotable.min.js"></script>
  <script defer src="assets/app.js?v=<?= e($assetVersion('assets/app.js')) ?>"></script>
  <?php if ($page === 'admin'): ?><script defer src="assets/admin.js?v=<?= e($assetVersion('assets/admin.js')) ?>"></script><?php endif; ?>
</head>
<body data-page="<?= e($page) ?>">
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
      <?php if ($page === 'admin'): ?>
        <span class="badge badge-orange">Administrador</span>
        <a class="btn btn-config" href="<?= e(url('')) ?>">Modo consulta</a>
        <button class="btn btn-exit" id="btn-logout" style="display:none">Sair</button>
      <?php else: ?>
        <span class="badge badge-cyan">Modo consulta</span>
        <a class="btn btn-config" href="<?= e(url('admin')) ?>">Área do administrador</a>
      <?php endif; ?>
    </div>
  </nav>

<?= $content ?>

  <footer class="site-footer no-print">
    <div class="footer-inner">
      <span>Diário de Bordo · CECAPE</span>
      <span class="sep">|</span>
      <span><?= e(APP_OWNER) ?></span>
      <span class="sep">|</span>
      <span>Registro de atividades em home office</span>
    </div>
  </footer>

  <div id="toast" class="toast"></div>
</body>
</html>
