<?php
defined('APP_RUNNING') or exit;
/** Painel compartilhado: cabeçalho de impressão, resumo, filtros/exportação e lista. */
?>
    <div class="print-header">
      <h1>Diário de Bordo — CECAPE</h1>
      <p>Registro de atividades em home office · <?= e(APP_OWNER) ?></p>
      <p id="print-period"></p>
    </div>

    <div id="stats" class="kpi-row"></div>

    <div class="filter-bar no-print">
      <div class="filter-row">
        <div class="filter-group">
          <label for="f-from">De</label>
          <input type="date" id="f-from" class="form-control form-sm">
        </div>
        <div class="filter-group">
          <label for="f-to">Até</label>
          <input type="date" id="f-to" class="form-control form-sm">
        </div>
        <div class="filter-group filter-grow">
          <label for="f-q">Buscar</label>
          <input type="search" id="f-q" class="form-control form-sm" placeholder="Título, descrição ou categoria…">
        </div>
        <div class="filter-group">
          <label>Período rápido</label>
          <div class="quick-periods">
            <button class="btn-chip" data-period="hoje">Hoje</button>
            <button class="btn-chip" data-period="semana">Semana</button>
            <button class="btn-chip" data-period="mes">Mês</button>
            <button class="btn-chip" data-period="tudo">Tudo</button>
          </div>
        </div>
        <div class="export-group">
          <button class="btn-outline" id="btn-xls">⬇ XLS</button>
          <button class="btn-outline" id="btn-pdf">⬇ PDF</button>
          <button class="btn-ghost" id="btn-print">🖨 Imprimir</button>
        </div>
      </div>
    </div>

    <div id="list"></div>
