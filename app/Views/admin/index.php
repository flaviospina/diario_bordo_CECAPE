<?php
defined('APP_RUNNING') or exit;
use App\Core\View;
?>
  <!-- Login -->
  <div id="login-wrap" class="login-center" style="display:none">
    <div class="login-card">
      <div class="login-logo-row">
        <?php foreach (LOGOS as $i => $logo): ?>
          <?php if ($i > 0): ?><span class="login-logo-divider"></span><?php endif; ?>
          <img src="<?= e(LOGO_BASE . '/' . $logo['file']) ?>" alt="<?= e($logo['alt']) ?>" class="login-logo">
        <?php endforeach; ?>
      </div>
      <h2 class="login-title">Área do administrador</h2>
      <p class="login-subtitle">Somente o responsável pelo diário registra atividades.<br>Informe a senha para continuar.</p>
      <form id="login-form">
        <div class="form-group">
          <label class="form-label" for="login-pass">Senha</label>
          <input type="password" id="login-pass" class="form-control" autocomplete="current-password" autofocus>
        </div>
        <p class="login-error" id="login-error"></p>
        <button class="btn-login" type="submit">Entrar</button>
      </form>
      <p class="login-back"><a class="link" href="<?= e(url('')) ?>">Voltar para o modo consulta</a></p>
    </div>
  </div>

  <!-- Painel -->
  <main class="main-content" id="admin-app" style="display:none">
    <div class="page-container">
      <div class="page-header no-print">
        <h1 class="page-title">Registro de atividades</h1>
        <p class="page-sub">Proponha a atividade e o sistema preenche a previsão de cada fase — <?= e(APP_OWNER) ?></p>
      </div>

      <div class="panel-card no-print">
        <div class="panel-title">Propor nova atividade</div>
        <p class="hint">Informe a atividade, o horário de início e a duração estimada — o sistema preenche automaticamente a previsão de início e término de cada fase.</p>
        <form id="activity-form">
          <div class="form-grid">
            <div class="form-group col-6">
              <label class="form-label" for="a-title">Título da atividade <span class="req">*</span></label>
              <input type="text" id="a-title" class="form-control" maxlength="200" placeholder="Ex.: Elaboração do relatório mensal de cursos" required>
            </div>
            <div class="form-group col-6">
              <label class="form-label" for="a-category">Categoria</label>
              <input type="text" id="a-category" class="form-control" maxlength="100" placeholder="Ex.: Relatórios, Reuniões, Planejamento…">
            </div>
            <div class="form-group col-12">
              <label class="form-label" for="a-desc">Descrição</label>
              <textarea id="a-desc" class="form-control textarea" rows="2" maxlength="2000" placeholder="Detalhes da atividade, entregas, observações…"></textarea>
            </div>
            <div class="form-group col-4">
              <label class="form-label" for="a-date">Data <span class="req">*</span></label>
              <input type="date" id="a-date" class="form-control" required>
            </div>
            <div class="form-group col-4">
              <label class="form-label" for="a-start">Início <span class="req">*</span></label>
              <input type="time" id="a-start" class="form-control" required>
            </div>
            <div class="form-group col-4">
              <label class="form-label" for="a-duration">Duração estimada (minutos) <span class="req">*</span></label>
              <input type="number" id="a-duration" class="form-control" min="5" max="1440" step="5" value="120" required>
            </div>
            <div class="col-12">
              <div class="phase-editor">
                <h3 class="phase-editor-label">Fases da atividade (nome e peso % da duração)</h3>
                <div id="phase-editor-rows"></div>
                <button type="button" class="btn-ghost" id="btn-add-phase">+ Adicionar fase</button>
              </div>
            </div>
          </div>
          <div class="form-actions">
            <button class="btn-action" type="submit">Registrar atividade</button>
            <div id="preview" class="preview-note"></div>
          </div>
        </form>
      </div>

      <details class="panel-card pass-card no-print">
        <summary>🔒 Trocar senha do administrador</summary>
        <form id="pass-form" class="form-grid">
          <div class="form-group col-4">
            <label class="form-label" for="p-current">Senha atual</label>
            <input type="password" id="p-current" class="form-control" autocomplete="current-password" required>
          </div>
          <div class="form-group col-4">
            <label class="form-label" for="p-new">Nova senha (mín. 8 caracteres)</label>
            <input type="password" id="p-new" class="form-control" autocomplete="new-password" minlength="8" required>
          </div>
          <div class="form-group col-4">
            <label class="form-label" for="p-confirm">Confirmar nova senha</label>
            <input type="password" id="p-confirm" class="form-control" autocomplete="new-password" minlength="8" required>
          </div>
          <div class="col-12"><button class="btn-primary" type="submit">Salvar nova senha</button></div>
        </form>
      </details>

<?= View::partial('partials/board') ?>
    </div>
  </main>
