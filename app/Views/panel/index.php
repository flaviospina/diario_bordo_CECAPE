<?php
defined('APP_RUNNING') or exit;
/** @var array $user Usuário logado */
$role = $user['role'];
$capable = $role !== 'gestor';   // registra apontamentos
$admin = $role === 'admin';
?>
  <main class="main-content">
    <div class="page-container">
      <div class="page-header no-print">
        <h1 class="page-title">Diário de Bordo</h1>
        <p class="page-sub">
          <?php if ($admin): ?>Administração, apontamentos e acompanhamento — registre suas atividades ou gerencie as contas da equipe.
          <?php elseif ($role === 'gestor'): ?>Acompanhamento dos professores em home office — consulte o diário e gere relatórios.
          <?php else: ?>Registre suas atividades, descansos e gere seu relatório de trabalho.<?php endif; ?>
        </p>
      </div>

      <div class="tab-bar no-print" id="tabs">
        <?php if ($capable): ?><button class="tab-btn" data-tab="registrar">✏️ Registrar</button><?php endif; ?>
        <button class="tab-btn active" data-tab="diario">📒 Diário</button>
        <?php if ($capable): ?><button class="tab-btn" data-tab="jornada">🕐 Jornada</button><?php endif; ?>
        <button class="tab-btn" data-tab="relatorios">🖨 Relatórios</button>
        <?php if ($admin): ?><button class="tab-btn" data-tab="contas">👥 Contas</button><?php endif; ?>
      </div>

      <?php if ($capable): ?><div id="jornada-flag" class="jornada-flag no-print" hidden></div><?php endif; ?>

      <?php if ($capable): ?>
      <!-- ══════════ REGISTRAR ══════════ -->
      <section id="tab-registrar" class="tab-panel" hidden>
        <div class="panel-card">
          <div class="panel-title">Propor nova atividade</div>
          <p class="hint">Informe a atividade, o horário de início e a duração estimada — o sistema preenche automaticamente a previsão de início e término de cada etapa.</p>
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
                  <h3 class="phase-editor-label">Etapas da atividade (nome e peso % da duração)</h3>
                  <div id="phase-editor-rows"></div>
                  <button type="button" class="btn-ghost" id="btn-add-phase">+ Adicionar etapa</button>
                </div>
              </div>
            </div>
            <div class="form-actions">
              <button class="btn-action" type="submit">Registrar atividade</button>
              <div id="preview" class="preview-note"></div>
            </div>
          </form>
        </div>

        <div class="panel-card">
          <div class="panel-title">Registrar descanso</div>
          <p class="hint">Aponte o almoço e/ou a janta do dia de trabalho. O descanso aparece no diário, bloqueia registros no período e é descontado das horas.</p>
          <form id="break-form">
            <div class="form-grid">
              <div class="form-group col-4">
                <label class="form-label" for="b-date">Data <span class="req">*</span></label>
                <input type="date" id="b-date" class="form-control" required>
              </div>
              <div class="form-group col-2">
                <label class="form-label" for="b-type">Tipo <span class="req">*</span></label>
                <select id="b-type" class="form-control form-select">
                  <option value="almoco">Almoço</option>
                  <option value="janta">Janta</option>
                </select>
              </div>
              <div class="form-group col-2">
                <label class="form-label" for="b-start">Início <span class="req">*</span></label>
                <input type="time" id="b-start" class="form-control" required>
              </div>
              <div class="form-group col-2">
                <label class="form-label" for="b-end">Término <span class="req">*</span></label>
                <input type="time" id="b-end" class="form-control" required>
              </div>
              <div class="form-group col-2 form-group-btn">
                <button class="btn-primary" type="submit">Adicionar</button>
              </div>
            </div>
          </form>
        </div>

        <div class="panel-card health-card">
          <div class="panel-title">🏥 Saúde — saída médica ou afastamento</div>
          <p class="hint">Registros de saúde ficam separados dos descansos. Na <b>saída médica</b>, o retorno é opcional — sem retorno, contam as horas restantes da sua jornada. No <b>afastamento</b> (1 dia ou mais), os dias inteiros ficam bloqueados. Anexe o atestado para ficar registrado no sistema.</p>
          <form id="health-form">
            <div class="form-grid">
              <div class="form-group col-3">
                <label class="form-label" for="h-type">Tipo <span class="req">*</span></label>
                <select id="h-type" class="form-control form-select">
                  <option value="saida">Saída médica</option>
                  <option value="afastamento">Afastamento médico</option>
                </select>
              </div>
              <div class="form-group col-3">
                <label class="form-label" for="h-date" id="h-date-label">Data <span class="req">*</span></label>
                <input type="date" id="h-date" class="form-control" required>
              </div>
              <div class="form-group col-3 h-saida">
                <label class="form-label" for="h-start">Horário de saída <span class="req">*</span></label>
                <input type="time" id="h-start" class="form-control">
              </div>
              <div class="form-group col-3 h-saida">
                <label class="form-label" for="h-end">Retorno (opcional)</label>
                <input type="time" id="h-end" class="form-control">
              </div>
              <div class="form-group col-3 h-afast" style="display:none">
                <label class="form-label" for="h-end-date">Até (data final)</label>
                <input type="date" id="h-end-date" class="form-control">
              </div>
              <div class="form-group col-6">
                <label class="form-label" for="h-note">Observação</label>
                <input type="text" id="h-note" class="form-control" maxlength="300" placeholder="Ex.: consulta cardiologista">
              </div>
              <div class="form-group col-6">
                <label class="form-label" for="h-file">Atestado médico (PDF/JPG/PNG, até 5 MB)</label>
                <input type="file" id="h-file" class="form-control form-file" accept=".pdf,.jpg,.jpeg,.png">
              </div>
            </div>
            <div class="form-actions">
              <button class="btn-action" type="submit">Registrar saúde</button>
            </div>
          </form>
        </div>
      </section>
      <?php endif; ?>

      <!-- ══════════ DIÁRIO ══════════ -->
      <section id="tab-diario" class="tab-panel">
        <div class="filter-bar no-print">
          <div class="filter-row">
            <?php if ($role !== 'professor'): ?>
            <div class="filter-group">
              <label for="f-prof">Professor(a)</label>
              <select id="f-prof" class="form-control form-select form-sm"></select>
            </div>
            <?php endif; ?>
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
            </div>
          </div>
        </div>

        <div id="stats" class="kpi-row"></div>
        <div id="list"></div>

        <details class="panel-card pass-card no-print">
          <summary>🔒 Trocar minha senha</summary>
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
      </section>

      <?php if ($capable): ?>
      <!-- ══════════ JORNADA ══════════ -->
      <section id="tab-jornada" class="tab-panel" hidden>
        <div class="panel-card">
          <div class="panel-title">Jornada de trabalho semanal</div>
          <p class="hint">Informe a entrada e a saída de cada dia da semana em que trabalha (ex.: 07:00 às 14:40). O diário mostra a jornada do dia, avisa quando o fim do expediente se aproxima e, se você passar do horário, oferece o registro das horas excedentes no banco de horas.</p>
          <form id="jornada-form">
            <div id="jornada-rows"></div>
            <div class="form-actions">
              <button class="btn-primary" type="submit">Salvar jornada</button>
            </div>
          </form>
        </div>

        <div class="panel-card">
          <div class="panel-header-row">
            <div class="panel-title">Banco de horas</div>
            <span class="badge badge-cyan" id="hb-total">—</span>
          </div>
          <p class="hint">Créditos registrados quando você trabalha além do fim da jornada (um registro por dia; registrar de novo no mesmo dia atualiza o valor).</p>
          <div class="table-wrap">
            <table class="data-table" id="hb-table">
              <thead><tr><th>Data</th><th>Horas</th><th>Observação</th><th>Ações</th></tr></thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </section>
      <?php endif; ?>

      <!-- ══════════ RELATÓRIOS ══════════ -->
      <section id="tab-relatorios" class="tab-panel" hidden>
        <div class="panel-card no-print">
          <div class="panel-title">Gerar relatório</div>
          <p class="hint">O relatório simplificado mostra apenas o início e o término do trabalho de cada dia, com os descansos; o detalhado inclui todas as atividades e etapas. Ambos saem com os campos de assinatura da direção e do professor.</p>
          <div class="form-grid">
            <?php if ($role !== 'professor'): ?>
            <div class="form-group col-4">
              <label class="form-label" for="r-prof">Professor(a)</label>
              <select id="r-prof" class="form-control form-select"></select>
            </div>
            <?php endif; ?>
            <div class="form-group col-4">
              <label class="form-label">Tipo de relatório</label>
              <div class="radio-row">
                <label class="radio-opt"><input type="radio" name="r-type" value="simplificado" checked> Simplificado</label>
                <label class="radio-opt"><input type="radio" name="r-type" value="detalhado"> Detalhado</label>
              </div>
            </div>
            <div class="form-group col-2">
              <label class="form-label" for="r-from">De</label>
              <input type="date" id="r-from" class="form-control">
            </div>
            <div class="form-group col-2">
              <label class="form-label" for="r-to">Até</label>
              <input type="date" id="r-to" class="form-control">
            </div>
          </div>
          <div class="form-actions">
            <button class="btn-action" id="btn-gerar">Gerar relatório</button>
            <button class="btn-outline" id="btn-rel-print" disabled>🖨 Imprimir</button>
            <button class="btn-outline" id="btn-rel-pdf" disabled>⬇ PDF</button>
          </div>
        </div>
        <div id="report-area"></div>
      </section>

      <?php if ($admin): ?>
      <!-- ══════════ CONTAS ══════════ -->
      <section id="tab-contas" class="tab-panel" hidden>
        <div class="panel-card">
          <div class="panel-title" id="u-form-title">Nova conta de professor</div>
          <p class="hint">Somente o administrador cria contas. Defina o nome, o RM, o usuário de acesso e as etapas de trabalho conforme a função do professor — elas preenchem automaticamente o formulário de atividades dele.</p>
          <form id="user-form">
            <input type="hidden" id="u-id" value="">
            <div class="form-grid">
              <div class="form-group col-6">
                <label class="form-label" for="u-name">Nome completo <span class="req">*</span></label>
                <input type="text" id="u-name" class="form-control" maxlength="200" required>
              </div>
              <div class="form-group col-6">
                <label class="form-label" for="u-rm">RM (matrícula)</label>
                <input type="text" id="u-rm" class="form-control" maxlength="30" placeholder="Ex.: 12345">
              </div>
              <div class="form-group col-6">
                <label class="form-label" for="u-username">Usuário de acesso <span class="req">*</span></label>
                <input type="text" id="u-username" class="form-control" maxlength="30" autocapitalize="none" placeholder="ex.: joao.silva">
              </div>
              <div class="form-group col-6">
                <label class="form-label" for="u-password">Senha inicial <span class="req">*</span></label>
                <input type="text" id="u-password" class="form-control" minlength="8" placeholder="mín. 8 caracteres">
              </div>
              <div class="col-12" id="u-phases-wrap">
                <div class="phase-editor">
                  <h3 class="phase-editor-label">Etapas de trabalho deste professor (quantidade, nomes e pesos %)</h3>
                  <div id="u-phase-rows"></div>
                  <button type="button" class="btn-ghost" id="btn-u-add-phase">+ Adicionar etapa</button>
                </div>
              </div>
            </div>
            <div class="form-actions">
              <button class="btn-primary" type="submit" id="u-submit">Criar conta</button>
              <button class="btn-ghost" type="button" id="u-cancel" style="display:none">Cancelar edição</button>
            </div>
          </form>
        </div>

        <div class="panel-card">
          <div class="panel-title">Contas cadastradas</div>
          <div class="table-wrap">
            <table class="data-table" id="users-table">
              <thead>
                <tr><th>Nome</th><th>Usuário</th><th>RM</th><th>Perfil</th><th>Etapas</th><th>Status</th><th>Ações</th></tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </section>
      <?php endif; ?>
    </div>
  </main>
