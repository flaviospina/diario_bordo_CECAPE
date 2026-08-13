<?php
defined('APP_RUNNING') or exit;
/** Painel compartilhado: cabeçalho de impressão, resumo, filtros/exportação e lista. */
?>
    <div class="print-header">
      <h1>Diário de Bordo — CECAPE</h1>
      <p>Registro de atividades em home office · <?= e(APP_OWNER) ?></p>
      <p id="print-period"></p>
    </div>

    <div id="stats" class="stats"></div>

    <div class="card filters">
      <div class="field"><label for="f-from">De</label><input type="date" id="f-from"></div>
      <div class="field"><label for="f-to">Até</label><input type="date" id="f-to"></div>
      <div class="field grow"><label for="f-q">Buscar</label><input type="search" id="f-q" placeholder="Título, descrição ou categoria…"></div>
      <div class="field"><label>Período rápido</label>
        <div class="quick-periods">
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
