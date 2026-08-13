<?php require_once __DIR__ . '/config.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex">
  <title><?= APP_NAME ?></title>
  <link rel="stylesheet" href="assets/style.css">
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='22' fill='%234f46e5'/><text x='50' y='68' font-size='52' text-anchor='middle' fill='white' font-family='sans-serif' font-weight='bold'>D</text></svg>">
  <script defer src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.2/dist/jspdf.plugin.autotable.min.js"></script>
  <script>const IS_ADMIN_PAGE = false;</script>
  <script defer src="assets/app.js"></script>
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
      <span class="badge-mode viewer">Modo consulta</span>
      <a class="btn ghost" href="admin.php">Área do administrador</a>
    </div>
  </header>

  <main class="container">
    <div class="print-header">
      <h1>Diário de Bordo — CECAPE</h1>
      <p>Registro de atividades em home office · <?= APP_OWNER ?></p>
      <p id="print-period"></p>
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
