<?php require_once __DIR__ . '/config.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex">
  <title>Administração — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="assets/style.css">
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='22' fill='%234f46e5'/><text x='50' y='68' font-size='52' text-anchor='middle' fill='white' font-family='sans-serif' font-weight='bold'>D</text></svg>">
  <script defer src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.2/dist/jspdf.plugin.autotable.min.js"></script>
  <script>const IS_ADMIN_PAGE = true;</script>
  <script defer src="assets/app.js"></script>
  <script defer src="assets/admin.js"></script>
</head>
<body>
  <header class="topbar">
    <div class="topbar-inner">
      <div class="logo">DB</div>
      <div class="brand">
        <h1>Diário de Bordo · CECAPE</h1>
        <p>Registro de atividades em home office — <?= APP_OWNER ?></p>
      </div>
      <div class="spacer"></div>
      <span class="badge-mode">Administrador</span>
      <a class="btn ghost" href="index.php">Modo consulta</a>
      <button class="btn ghost" id="btn-logout" style="display:none">Sair</button>
    </div>
  </header>

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
      <p style="margin-top:14px"><a class="link" href="index.php">Voltar para o modo consulta</a></p>
    </div>
  </div>

  <!-- Painel -->
  <main class="container" id="admin-app" style="display:none">
    <div class="print-header">
      <h1>Diário de Bordo — CECAPE</h1>
      <p>Registro de atividades em home office · <?= APP_OWNER ?></p>
      <p id="print-period"></p>
    </div>

    <div class="card form-card no-print">
      <h2>Propor nova atividade</h2>
      <p class="hint">Informe a atividade, o horário de início e a duração estimada — o sistema preenche automaticamente a previsão de início e término de cada fase.</p>
      <form id="activity-form">
        <div class="form-grid">
          <div class="field col-6"><label>Título da atividade *</label>
            <input type="text" id="a-title" placeholder="Ex.: Elaboração do relatório mensal de cursos" required></div>
          <div class="field col-6"><label>Categoria</label>
            <input type="text" id="a-category" placeholder="Ex.: Relatórios, Reuniões, Planejamento…"></div>
          <div class="field col-12"><label>Descrição</label>
            <textarea id="a-desc" rows="2" placeholder="Detalhes da atividade, entregas, observações…"></textarea></div>
          <div class="field col-4"><label>Data *</label><input type="date" id="a-date" required></div>
          <div class="field col-4"><label>Início *</label><input type="time" id="a-start" required></div>
          <div class="field col-4"><label>Duração estimada (minutos) *</label>
            <input type="number" id="a-duration" min="5" step="5" value="120" required></div>
          <div class="col-12">
            <div class="phase-editor">
              <div class="field" style="margin-bottom:10px"><label>Fases da atividade (nome e peso % da duração)</label></div>
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

    <div id="stats" class="stats"></div>

    <div class="card filters">
      <div class="field"><label>De</label><input type="date" id="f-from"></div>
      <div class="field"><label>Até</label><input type="date" id="f-to"></div>
      <div class="field grow"><label>Buscar</label><input type="search" id="f-q" placeholder="Título, descrição ou categoria…"></div>
      <div class="field"><label>Período rápido</label>
        <div style="display:flex;gap:6px">
          <button class="btn small" data-period="hoje">Hoje</button>
          <button class="btn small" data-period="semana">Semana</button>
          <button class="btn small" data-period="mes">Mês</button>
          <button class="btn small" data-period="tudo">Tudo</button>
        </div>
      </div>
      <div class="export-group">
        <button class="btn" id="btn-xls">⬇ XLS</button>
        <button class="btn" id="btn-pdf">⬇ PDF</button>
        <button class="btn" id="btn-print">🖨 Imprimir</button>
      </div>
    </div>

    <div id="list"></div>
  </main>

  <div id="toast" class="toast"></div>
</body>
</html>
