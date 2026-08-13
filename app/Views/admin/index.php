<?php
defined('APP_RUNNING') or exit;
use App\Core\View;
?>
  <!-- Login -->
  <div id="login-wrap" class="login-wrap" style="display:none">
    <div class="card login-card">
      <div class="logo">DB</div>
      <h2>Área do administrador</h2>
      <p>Somente o responsável pelo diário registra atividades.<br>Informe a senha para continuar.</p>
      <form id="login-form">
        <input type="password" id="login-pass" placeholder="Senha" autocomplete="current-password" autofocus>
        <p class="login-error" id="login-error"></p>
        <button class="btn primary" type="submit">Entrar</button>
      </form>
      <p class="login-back"><a class="link" href="<?= e(url('')) ?>">Voltar para o modo consulta</a></p>
    </div>
  </div>

  <!-- Painel -->
  <main class="container" id="admin-app" style="display:none">
    <div class="card form-card no-print">
      <h2>Propor nova atividade</h2>
      <p class="hint">Informe a atividade, o horário de início e a duração estimada — o sistema preenche automaticamente a previsão de início e término de cada fase.</p>
      <form id="activity-form">
        <div class="form-grid">
          <div class="field col-6"><label for="a-title">Título da atividade *</label>
            <input type="text" id="a-title" maxlength="200" placeholder="Ex.: Elaboração do relatório mensal de cursos" required></div>
          <div class="field col-6"><label for="a-category">Categoria</label>
            <input type="text" id="a-category" maxlength="100" placeholder="Ex.: Relatórios, Reuniões, Planejamento…"></div>
          <div class="field col-12"><label for="a-desc">Descrição</label>
            <textarea id="a-desc" rows="2" maxlength="2000" placeholder="Detalhes da atividade, entregas, observações…"></textarea></div>
          <div class="field col-4"><label for="a-date">Data *</label><input type="date" id="a-date" required></div>
          <div class="field col-4"><label for="a-start">Início *</label><input type="time" id="a-start" required></div>
          <div class="field col-4"><label for="a-duration">Duração estimada (minutos) *</label>
            <input type="number" id="a-duration" min="5" max="1440" step="5" value="120" required></div>
          <div class="col-12">
            <div class="phase-editor">
              <div class="field phase-editor-label"><label>Fases da atividade (nome e peso % da duração)</label></div>
              <div id="phase-editor-rows"></div>
              <button type="button" class="btn small ghost" id="btn-add-phase">+ Adicionar fase</button>
            </div>
          </div>
        </div>
        <div class="form-footer">
          <button class="btn primary" type="submit">Registrar atividade</button>
          <div id="preview" class="preview-note"></div>
        </div>
      </form>
    </div>

    <details class="card pass-card no-print">
      <summary>Trocar senha do administrador</summary>
      <form id="pass-form" class="form-grid">
        <div class="field col-4"><label for="p-current">Senha atual</label>
          <input type="password" id="p-current" autocomplete="current-password" required></div>
        <div class="field col-4"><label for="p-new">Nova senha (mín. 8 caracteres)</label>
          <input type="password" id="p-new" autocomplete="new-password" minlength="8" required></div>
        <div class="field col-4"><label for="p-confirm">Confirmar nova senha</label>
          <input type="password" id="p-confirm" autocomplete="new-password" minlength="8" required></div>
        <div class="col-12"><button class="btn primary" type="submit">Salvar nova senha</button></div>
      </form>
    </details>

<?= View::partial('partials/board') ?>
  </main>
