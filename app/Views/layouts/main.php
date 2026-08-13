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
  <link rel="stylesheet" href="assets/style.css?v=<?= e($assetVersion('assets/style.css')) ?>">
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='22' fill='%234f46e5'/><text x='50' y='68' font-size='52' text-anchor='middle' fill='white' font-family='sans-serif' font-weight='bold'>D</text></svg>">
  <script defer src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.2/dist/jspdf.plugin.autotable.min.js"></script>
  <script defer src="assets/app.js?v=<?= e($assetVersion('assets/app.js')) ?>"></script>
  <?php if ($page === 'admin'): ?><script defer src="assets/admin.js?v=<?= e($assetVersion('assets/admin.js')) ?>"></script><?php endif; ?>
</head>
<body data-page="<?= e($page) ?>">
  <header class="topbar">
    <div class="topbar-inner">
      <div class="logo">DB</div>
      <div class="brand">
        <h1>Diário de Bordo · CECAPE</h1>
        <p>Registro de atividades em home office — <?= e(APP_OWNER) ?></p>
      </div>
      <div class="spacer"></div>
      <?php if ($page === 'admin'): ?>
        <span class="badge-mode">Administrador</span>
        <a class="btn ghost" href="<?= e(url('')) ?>">Modo consulta</a>
        <button class="btn ghost" id="btn-logout" style="display:none">Sair</button>
      <?php else: ?>
        <span class="badge-mode viewer">Modo consulta</span>
        <a class="btn ghost" href="<?= e(url('admin')) ?>">Área do administrador</a>
      <?php endif; ?>
    </div>
  </header>

<?= $content ?>

  <div id="toast" class="toast"></div>
</body>
</html>
