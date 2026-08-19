<?php
defined('APP_RUNNING') or exit;
use App\Core\View;
?>
  <main class="main-content">
    <div class="page-container">
      <div class="page-header no-print">
        <h1 class="page-title">Diário de Bordo</h1>
        <p class="page-sub">Atividades executadas em home office — <?= e(APP_OWNER) ?> · acompanhamento e exportação</p>
      </div>

<?= View::partial('partials/board') ?>
    </div>
  </main>
