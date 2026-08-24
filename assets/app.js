/* Diário de Bordo CECAPE — front-end do painel único */
/* global XLSX, jspdf */

// Fonte Inter carregada sem bloquear a página (fallback: fonte do sistema)
(function () {
  const l = document.createElement('link');
  l.rel = 'stylesheet';
  l.href = 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap';
  document.head.appendChild(l);
})();

let STATE = {
  role: null, userId: 0, userName: '', userRm: '',
  director: '', directorRole: '',
  professors: [], profId: 0, professor: null,
  activities: [], breaks: [],
  phasesTemplate: [], reportData: null, reportType: 'simplificado'
};

const STATUS_LABEL = { prevista: 'Prevista', em_andamento: 'Em andamento', concluida: 'Concluída' };
const BREAK_LABEL = { almoco: 'Almoço', janta: 'Janta' };

/* ---------------- Utilidades ---------------- */

const $ = (sel, el = document) => el.querySelector(sel);
const $$ = (sel, el = document) => [...el.querySelectorAll(sel)];

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  }[c]));
}

function toast(msg) {
  const el = $('#toast');
  el.textContent = msg;
  el.classList.add('show');
  clearTimeout(el._t);
  el._t = setTimeout(() => el.classList.remove('show'), 2600);
}

function pad(n) { return String(n).padStart(2, '0'); }
function isoDate(d) { return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`; }

function fmtDay(iso) {
  const d = new Date(iso + 'T12:00:00');
  return d.toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });
}

function fmtDayShort(iso) {
  const d = new Date(iso + 'T12:00:00');
  const semana = d.toLocaleDateString('pt-BR', { weekday: 'short' }).replace('.', '');
  return `${iso.split('-').reverse().join('/')} (${semana})`;
}

function fmtTime(dt) {
  if (!dt) return '—';
  return dt.slice(11, 16) || '—';
}

function fmtDateTimeShort(dt) {
  if (!dt) return '—';
  const [d, t] = dt.split(' ');
  const [y, m, day] = d.split('-');
  return `${day}/${m}/${y} ${t.slice(0, 5)}`;
}

function minutesBetween(a, b) {
  if (!a || !b) return null;
  return Math.round((new Date(b.replace(' ', 'T')) - new Date(a.replace(' ', 'T'))) / 60000);
}

function minutesHM(a, b) { // "HH:MM" → minutos
  if (!a || !b) return 0;
  const [h1, m1] = a.split(':').map(Number);
  const [h2, m2] = b.split(':').map(Number);
  return Math.max(0, (h2 * 60 + m2) - (h1 * 60 + m1));
}

function fmtDuration(min) {
  if (min == null || isNaN(min) || min < 0) return '—';
  const h = Math.floor(min / 60), m = min % 60;
  if (h && m) return `${h}h ${pad(m)}min`;
  if (h) return `${h}h`;
  return `${m}min`;
}

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

async function api(action, opts = {}) {
  const url = `index.php?r=api/${action}${opts.qs ? '&' + opts.qs : ''}`;
  const res = await fetch(url, opts.body ? {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
    body: JSON.stringify(opts.body)
  } : undefined);
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || 'Erro na requisição.');
  return data;
}

function canEdit() {
  return STATE.role !== 'gestor' && STATE.profId === STATE.userId;
}

/* ---------------- Abas ---------------- */

function initTabs() {
  $$('#tabs .tab-btn').forEach(btn => btn.addEventListener('click', () => {
    $$('#tabs .tab-btn').forEach(b => b.classList.toggle('active', b === btn));
    $$('.tab-panel').forEach(p => { p.hidden = p.id !== 'tab-' + btn.dataset.tab; });
  }));
}

/* ---------------- Filtros e período ---------------- */

function currentFilters() {
  return { from: $('#f-from').value, to: $('#f-to').value, q: $('#f-q').value.trim() };
}

function markPeriod(kind) {
  $$('[data-period]').forEach(b => b.classList.toggle('active', b.dataset.period === kind));
}

function setPeriod(kind) {
  const today = new Date();
  let from = new Date(today);
  let to = new Date(today);
  if (kind === 'hoje') {
    /* from = to = hoje */
  } else if (kind === 'semana') {
    const dow = (today.getDay() + 6) % 7; // segunda-feira = 0
    from.setDate(today.getDate() - dow);
    to = new Date(from);
    to.setDate(from.getDate() + 6);
  } else if (kind === 'mes') {
    from = new Date(today.getFullYear(), today.getMonth(), 1);
    to = new Date(today.getFullYear(), today.getMonth() + 1, 0);
  } else { // tudo
    $('#f-from').value = '';
    $('#f-to').value = '';
    markPeriod(kind);
    load();
    return;
  }
  $('#f-from').value = isoDate(from);
  $('#f-to').value = isoDate(to);
  markPeriod(kind);
  load();
}

/* ---------------- Carregamento do diário ---------------- */

async function load() {
  const { from, to, q } = currentFilters();
  const qs = new URLSearchParams();
  if (STATE.profId) qs.set('user_id', STATE.profId);
  if (from) qs.set('from', from);
  if (to) qs.set('to', to);
  if (q) qs.set('q', q);
  const data = await api('list', { qs: qs.toString() });
  STATE.professor = data.professor;
  STATE.activities = data.activities;
  STATE.breaks = data.breaks;
  renderStats();
  renderList();
}

function realDuration(a) {
  let total = 0, has = false;
  for (const p of a.phases) {
    const m = minutesBetween(p.real_start, p.real_end);
    if (m != null) { total += m; has = true; }
  }
  return has ? total : null;
}

function renderStats() {
  const acts = STATE.activities;
  const done = acts.filter(a => a.status === 'concluida').length;
  const inProgress = acts.filter(a => a.status === 'em_andamento').length;
  let totalMin = 0;
  acts.forEach(a => { totalMin += realDuration(a) || 0; });
  const restMin = STATE.breaks.reduce((s, b) => s + minutesHM(b.start_time, b.end_time), 0);
  const days = new Set(acts.map(a => a.date)).size;
  $('#stats').innerHTML = `
    <div class="kpi-card kpi-orange"><div class="kpi-value">${acts.length}</div><div class="kpi-label">Atividades</div></div>
    <div class="kpi-card kpi-green"><div class="kpi-value">${done}<small> / ${acts.length}</small></div><div class="kpi-label">Concluídas</div></div>
    <div class="kpi-card kpi-yellow"><div class="kpi-value">${inProgress}</div><div class="kpi-label">Em andamento</div></div>
    <div class="kpi-card kpi-cyan"><div class="kpi-value">${fmtDuration(totalMin)}</div><div class="kpi-label">Horas registradas</div></div>
    <div class="kpi-card kpi-pink"><div class="kpi-value">${fmtDuration(restMin)}</div><div class="kpi-label">Descanso</div></div>
    <div class="kpi-card kpi-purple"><div class="kpi-value">${days}</div><div class="kpi-label">Dias de trabalho</div></div>`;
}

function phaseRowHtml(p, editable) {
  const started = !!p.real_start, done = !!p.real_end;
  const cls = done ? 'done' : (started ? 'started' : '');
  const realDur = minutesBetween(p.real_start, p.real_end);
  let actions = '';
  if (editable) {
    if (!started && !done) {
      actions = `<button class="btn-sm btn-info" data-phase="${p.id}" data-op="start">Iniciar</button>`;
    } else if (!done) {
      actions = `<button class="btn-sm btn-ok" data-phase="${p.id}" data-op="finish">Concluir</button>`;
    } else {
      actions = `<button class="btn-sm btn-muted" data-phase="${p.id}" data-op="undo" title="Limpar horários reais">Refazer</button>`;
    }
    actions += `<button class="btn-sm btn-muted" data-phase="${p.id}" data-op="edit" title="Editar horários manualmente">Editar</button>`;
  }
  return `
    <div class="phase ${cls}">
      <span class="dot"></span>
      <span class="name">${esc(p.name)}</span>
      <span class="time-block"><span class="tag prev">Previsão</span><b>${fmtTime(p.prev_start)} – ${fmtTime(p.prev_end)}</b></span>
      <span class="time-block"><span class="tag real">Real</span><b>${fmtTime(p.real_start)} – ${fmtTime(p.real_end)}</b>${realDur != null ? ` <span>(${fmtDuration(realDur)})</span>` : ''}</span>
      <span class="phase-actions">${actions}</span>
    </div>`;
}

function activityCard(a, editable) {
  const dur = realDuration(a);
  const prevDur = minutesBetween(a.prev_start, a.prev_end);
  return `
  <article class="activity st-${a.status}" data-id="${a.id}">
    <div class="activity-head">
      <div class="info">
        <h3 class="activity-title">${esc(a.title)}</h3>
        <div class="activity-meta">
          <span class="chip ${a.status}">${STATUS_LABEL[a.status]}</span>
          ${a.category ? `<span class="chip categoria">${esc(a.category)}</span>` : ''}
          <span>Previsto: <b>${fmtTime(a.prev_start)} – ${fmtTime(a.prev_end)}</b>${prevDur ? ` (${fmtDuration(prevDur)})` : ''}</span>
          ${a.real_start ? `<span>Real: <b>${fmtTime(a.real_start)} – ${a.real_end ? fmtTime(a.real_end) : 'em curso'}</b>${dur != null ? ` (${fmtDuration(dur)})` : ''}</span>` : ''}
        </div>
        ${a.description ? `<p class="activity-desc">${esc(a.description)}</p>` : ''}
      </div>
      ${editable ? `<div class="activity-actions">
        <button class="btn-sm btn-info" data-act="${a.id}" data-op="edit-activity">Editar</button>
        <button class="btn-sm btn-danger" data-act="${a.id}" data-op="delete">Excluir</button>
      </div>` : ''}
    </div>
    <div class="phases">${a.phases.map(p => phaseRowHtml(p, editable)).join('')}</div>
  </article>`;
}

function breakChip(b, editable) {
  return `<span class="break-chip">☕ ${BREAK_LABEL[b.type] || b.type} ${esc(b.start_time)}–${esc(b.end_time)}${editable ? ` <button class="break-del" data-break="${b.id}" title="Remover descanso">✕</button>` : ''}</span>`;
}

function renderList() {
  const editable = canEdit();
  const wrap = $('#list');
  const acts = STATE.activities;
  const days = [...new Set([...acts.map(a => a.date), ...STATE.breaks.map(b => b.date)])].sort().reverse();
  if (!days.length) {
    wrap.innerHTML = `<div class="empty">Nenhuma atividade registrada no período selecionado.</div>`;
    return;
  }
  wrap.innerHTML = days.map(day => {
    const list = acts.filter(a => a.date === day);
    const dayBreaks = STATE.breaks.filter(b => b.date === day);
    const dayMin = list.reduce((s, a) => s + (realDuration(a) || 0), 0);
    return `
    <section class="day-group">
      <div class="day-head">
        <h2>${fmtDay(day)}</h2>
        <span>${list.length} atividade${list.length !== 1 ? 's' : ''}${dayMin ? ' · ' + fmtDuration(dayMin) + ' registradas' : ''}</span>
        ${dayBreaks.map(b => breakChip(b, editable)).join('')}
      </div>
      ${list.map(a => activityCard(a, editable)).join('') || ''}
    </section>`;
  }).join('');

  if (editable) bindListEvents();
}

/* ---------------- Ações no diário (dono) ---------------- */

function findPhase(id) {
  for (const a of STATE.activities) {
    const p = a.phases.find(p => p.id == id);
    if (p) return p;
  }
  return null;
}

function bindListEvents() {
  $$('#list [data-phase]').forEach(btn => btn.addEventListener('click', async () => {
    const id = btn.dataset.phase, op = btn.dataset.op;
    try {
      if (op === 'edit') {
        const p = findPhase(id);
        const rs = prompt('Início real (AAAA-MM-DD HH:MM) — vazio para limpar:', p?.real_start || '');
        if (rs === null) return;
        const re = prompt('Término real (AAAA-MM-DD HH:MM) — vazio para limpar:', p?.real_end || '');
        if (re === null) return;
        await api('phase', { body: { id, op: 'set_times', real_start: rs, real_end: re } });
      } else if (op === 'undo') {
        if (!confirm('Limpar os horários reais desta etapa?')) return;
        await api('phase', { body: { id, op } });
      } else {
        await api('phase', { body: { id, op } });
      }
      await load();
      toast('Etapa atualizada.');
    } catch (e) { toast(e.message); }
  }));

  $$('#list [data-act]').forEach(btn => btn.addEventListener('click', async () => {
    const id = btn.dataset.act, op = btn.dataset.op;
    const a = STATE.activities.find(x => x.id == id);
    try {
      if (op === 'delete') {
        if (!confirm(`Excluir a atividade "${a.title}" e todas as suas etapas?`)) return;
        await api('delete', { body: { id } });
        toast('Atividade excluída.');
      } else if (op === 'edit-activity') {
        const title = prompt('Título:', a.title);
        if (title === null) return;
        const category = prompt('Categoria:', a.category || '');
        if (category === null) return;
        const description = prompt('Descrição:', a.description || '');
        if (description === null) return;
        await api('update-activity', { body: { id, title, category, description } });
        toast('Atividade atualizada.');
      }
      await load();
    } catch (e) { toast(e.message); }
  }));

  $$('#list [data-break]').forEach(btn => btn.addEventListener('click', async () => {
    if (!confirm('Remover este descanso?')) return;
    try {
      await api('break-delete', { body: { id: btn.dataset.break } });
      await load();
      toast('Descanso removido.');
    } catch (e) { toast(e.message); }
  }));
}

/* ---------------- Exportação XLS (diário) ---------------- */

function exportRows() {
  const rows = [];
  const prof = STATE.professor || {};
  STATE.activities.forEach(a => {
    a.phases.forEach(p => {
      rows.push({
        'Professor': prof.name || '',
        'RM': prof.rm || '',
        'Data': a.date.split('-').reverse().join('/'),
        'Atividade': a.title,
        'Categoria': a.category || '',
        'Status': STATUS_LABEL[a.status],
        'Etapa': p.name,
        'Início (Previsão)': fmtDateTimeShort(p.prev_start),
        'Término (Previsão)': fmtDateTimeShort(p.prev_end),
        'Início (Real)': fmtDateTimeShort(p.real_start),
        'Término (Real)': fmtDateTimeShort(p.real_end),
        'Duração real': fmtDuration(minutesBetween(p.real_start, p.real_end)),
        'Descrição': a.description || ''
      });
    });
  });
  return rows;
}

function exportXLS() {
  const rows = exportRows();
  if (!rows.length) return toast('Nada para exportar no período.');
  const name = `diario_bordo_cecape_${isoDate(new Date())}`;
  if (typeof XLSX !== 'undefined') {
    const ws = XLSX.utils.json_to_sheet(rows);
    ws['!cols'] = Object.keys(rows[0]).map(k => ({
      wch: Math.min(45, Math.max(k.length, ...rows.map(r => String(r[k]).length)) + 2)
    }));
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Diário de Bordo');
    XLSX.writeFile(wb, `${name}.xlsx`);
  } else {
    const cols = Object.keys(rows[0]);
    const html = `<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"></head><body>
      <table border="1"><tr>${cols.map(c => `<th>${esc(c)}</th>`).join('')}</tr>
      ${rows.map(r => `<tr>${cols.map(c => `<td>${esc(r[c])}</td>`).join('')}</tr>`).join('')}</table></body></html>`;
    const blob = new Blob(['﻿' + html], { type: 'application/vnd.ms-excel' });
    const aEl = document.createElement('a');
    aEl.href = URL.createObjectURL(blob);
    aEl.download = `${name}.xls`;
    aEl.click();
    URL.revokeObjectURL(aEl.href);
  }
  toast('Planilha exportada.');
}

/* ---------------- Formulário de atividade ---------------- */

function phaseEditorRow(container, name = '', weight = '') {
  const row = document.createElement('div');
  row.className = 'row';
  row.innerHTML = `
    <input type="text" placeholder="Nome da etapa" value="${esc(name)}" class="form-control form-sm ph-name">
    <input type="number" min="1" max="100" placeholder="%" value="${esc(weight)}" class="form-control form-sm ph-weight" title="Peso da etapa (% da duração)">
    <button type="button" class="btn-icon ph-del" title="Remover etapa">✕</button>`;
  row.querySelector('.ph-del').addEventListener('click', () => { row.remove(); renderPreview(); });
  row.querySelectorAll('input').forEach(i => i.addEventListener('input', renderPreview));
  container.appendChild(row);
  return row;
}

function editorPhases(containerSel) {
  return $$(containerSel + ' .row').map(r => ({
    name: r.querySelector('.ph-name').value.trim(),
    weight: parseFloat(r.querySelector('.ph-weight').value) || 0
  })).filter(p => p.name);
}

/* Espelha o cálculo do servidor: distribui a duração pelos pesos, em sequência. */
function computePreview(date, startTime, duration, phases) {
  let total = phases.reduce((s, p) => s + Math.max(0, p.weight), 0);
  if (total <= 0) { phases = phases.map(p => ({ ...p, weight: 1 })); total = phases.length; }
  let cursor = new Date(`${date}T${startTime}`);
  let used = 0;
  return phases.map((p, i) => {
    let mins = (i === phases.length - 1)
      ? duration - used
      : Math.round(duration * (p.weight / total));
    mins = Math.max(1, mins);
    used += mins;
    const start = new Date(cursor);
    cursor = new Date(cursor.getTime() + mins * 60000);
    return { name: p.name, start, end: new Date(cursor), mins };
  });
}

function renderPreview() {
  const box = $('#preview');
  if (!box) return;
  const date = $('#a-date').value;
  const time = $('#a-start').value;
  const duration = parseInt($('#a-duration').value, 10);
  const phases = editorPhases('#phase-editor-rows');
  if (!date || !time || !duration || !phases.length) {
    box.innerHTML = '<span>Informe data, início e duração para ver a previsão das etapas.</span>';
    return;
  }
  const hhmm = d => `${pad(d.getHours())}:${pad(d.getMinutes())}`;
  const items = computePreview(date, time, duration, phases);
  box.innerHTML = '<span>Previsão gerada automaticamente: </span>' +
    items.map(p => `<b>${esc(p.name)}</b> ${hhmm(p.start)}–${hhmm(p.end)}`).join(' · ');
}

function resetPhaseEditor() {
  const editor = $('#phase-editor-rows');
  editor.innerHTML = '';
  STATE.phasesTemplate.forEach(p => phaseEditorRow(editor, p.name, p.weight));
}

async function submitActivity(ev) {
  ev.preventDefault();
  const body = {
    title: $('#a-title').value.trim(),
    description: $('#a-desc').value.trim(),
    category: $('#a-category').value.trim(),
    date: $('#a-date').value,
    start_time: $('#a-start').value,
    duration: parseInt($('#a-duration').value, 10) || 0,
    phases: editorPhases('#phase-editor-rows')
  };
  if (!body.title || !body.date || !body.start_time || body.duration < 1) {
    return toast('Preencha título, data, horário de início e duração.');
  }
  try {
    await api('create', { body });
    toast('Atividade registrada com as etapas previstas.');
    $('#a-title').value = '';
    $('#a-desc').value = '';
    resetPhaseEditor();
    renderPreview();
    await load();
  } catch (e) { toast(e.message); }
}

async function submitBreak(ev) {
  ev.preventDefault();
  const body = {
    date: $('#b-date').value,
    type: $('#b-type').value,
    start: $('#b-start').value,
    end: $('#b-end').value
  };
  if (!body.date || !body.start || !body.end) return toast('Preencha data, início e término do descanso.');
  try {
    await api('break-create', { body });
    toast('Descanso registrado.');
    $('#b-start').value = '';
    $('#b-end').value = '';
    await load();
  } catch (e) { toast(e.message); }
}

/* ---------------- Senha ---------------- */

async function changePassword(ev) {
  ev.preventDefault();
  const current = $('#p-current').value;
  const nova = $('#p-new').value;
  const confirm_ = $('#p-confirm').value;
  if (nova !== confirm_) return toast('A confirmação não confere com a nova senha.');
  if (nova.length < 8) return toast('A nova senha deve ter pelo menos 8 caracteres.');
  try {
    await api('password', { body: { current, new: nova } });
    $('#p-current').value = $('#p-new').value = $('#p-confirm').value = '';
    toast('Senha alterada com sucesso.');
  } catch (e) { toast(e.message); }
}

/* ---------------- Contas (admin) ---------------- */

function userPhaseRow(name = '', weight = '') {
  const container = $('#u-phase-rows');
  const row = document.createElement('div');
  row.className = 'row';
  row.innerHTML = `
    <input type="text" placeholder="Nome da etapa" value="${esc(name)}" class="form-control form-sm ph-name">
    <input type="number" min="1" max="100" placeholder="%" value="${esc(weight)}" class="form-control form-sm ph-weight">
    <button type="button" class="btn-icon ph-del" title="Remover etapa">✕</button>`;
  row.querySelector('.ph-del').addEventListener('click', () => row.remove());
  container.appendChild(row);
}

function resetUserForm() {
  $('#u-id').value = '';
  $('#u-name').value = '';
  $('#u-rm').value = '';
  $('#u-username').value = '';
  $('#u-username').disabled = false;
  $('#u-password').value = '';
  $('#u-password').required = true;
  $('#u-password').placeholder = 'mín. 8 caracteres';
  $('#u-form-title').textContent = 'Nova conta de professor';
  $('#u-submit').textContent = 'Criar conta';
  $('#u-cancel').style.display = 'none';
  $('#u-phases-wrap').style.display = '';
  $('#u-phase-rows').innerHTML = '';
  defaultPhasesJs().forEach(p => userPhaseRow(p.name, p.weight));
}

function defaultPhasesJs() {
  try { return JSON.parse(document.body.dataset.defaultPhases || '[]'); }
  catch { return []; }
}

function fillUserForm(u) {
  $('#u-id').value = u.id;
  $('#u-name').value = u.name;
  $('#u-rm').value = u.rm || '';
  $('#u-username').value = u.username;
  $('#u-username').disabled = true;
  $('#u-password').value = '';
  $('#u-password').required = false;
  $('#u-password').placeholder = 'deixe vazio para manter';
  $('#u-form-title').textContent = `Editar conta — ${u.name}`;
  $('#u-submit').textContent = 'Salvar alterações';
  $('#u-cancel').style.display = '';
  const hasPhases = u.role !== 'gestor';
  $('#u-phases-wrap').style.display = hasPhases ? '' : 'none';
  if (hasPhases) {
    $('#u-phase-rows').innerHTML = '';
    (u.phases || defaultPhasesJs()).forEach(p => userPhaseRow(p.name, p.weight));
  }
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function loadUsers() {
  const data = await api('users');
  STATE.users = data.users;
  const tbody = $('#users-table tbody');
  const roleBadge = { admin: 'badge-orange', gestor: 'badge-cyan', professor: 'badge-purple' };
  const roleLabel = { admin: 'Administrador', gestor: 'Gestão', professor: 'Professor' };
  tbody.innerHTML = data.users.map(u => `
    <tr class="${u.active ? '' : 'row-inactive'}">
      <td>${esc(u.name)}</td>
      <td class="cell-code">${esc(u.username)}</td>
      <td>${esc(u.rm || '—')}</td>
      <td><span class="badge ${roleBadge[u.role]}">${roleLabel[u.role]}</span></td>
      <td>${u.phases ? u.phases.length + ' etapa' + (u.phases.length !== 1 ? 's' : '') : '—'}</td>
      <td>${u.active ? '<span class="badge badge-green">Ativa</span>' : '<span class="badge badge-red">Desativada</span>'}</td>
      <td class="action-cell">
        <button class="btn-sm btn-info" data-user="${u.id}" data-op="edit">Editar</button>
        ${u.id !== STATE.userId ? `<button class="btn-sm ${u.active ? 'btn-danger' : 'btn-ok'}" data-user="${u.id}" data-op="toggle">${u.active ? 'Desativar' : 'Reativar'}</button>` : ''}
      </td>
    </tr>`).join('');

  $$('#users-table [data-user]').forEach(btn => btn.addEventListener('click', async () => {
    const u = STATE.users.find(x => x.id == btn.dataset.user);
    if (btn.dataset.op === 'edit') {
      fillUserForm(u);
    } else if (btn.dataset.op === 'toggle') {
      const acao = u.active ? 'Desativar' : 'Reativar';
      if (!confirm(`${acao} a conta de ${u.name}?`)) return;
      try {
        await api('user-update', { body: { id: u.id, active: u.active ? 0 : 1 } });
        toast(`Conta ${u.active ? 'desativada' : 'reativada'}.`);
        await loadUsers();
        await loadProfessors();
      } catch (e) { toast(e.message); }
    }
  }));
}

async function submitUser(ev) {
  ev.preventDefault();
  const id = $('#u-id').value;
  const body = {
    name: $('#u-name').value.trim(),
    rm: $('#u-rm').value.trim(),
    phases: editorPhases('#u-phase-rows')
  };
  const password = $('#u-password').value;
  try {
    if (id) {
      if (password) body.password = password;
      body.id = parseInt(id, 10);
      await api('user-update', { body });
      toast('Conta atualizada.');
    } else {
      body.username = $('#u-username').value.trim().toLowerCase();
      body.password = password;
      if (!body.name || !body.username || password.length < 8) {
        return toast('Preencha nome, usuário e uma senha inicial com pelo menos 8 caracteres.');
      }
      await api('user-create', { body });
      toast('Conta de professor criada.');
    }
    resetUserForm();
    await loadUsers();
    await loadProfessors();
  } catch (e) { toast(e.message); }
}

/* ---------------- Professores (seletores) ---------------- */

async function loadProfessors() {
  const data = await api('professors');
  STATE.professors = data.professors;
  const options = STATE.professors.map(p =>
    `<option value="${p.id}">${esc(p.name)}${p.rm ? ' · RM ' + esc(p.rm) : ''}</option>`).join('');
  const fSel = $('#f-prof');
  const rSel = $('#r-prof');
  if (fSel) {
    fSel.innerHTML = options;
    fSel.value = String(STATE.profId || STATE.professors[0]?.id || '');
  }
  if (rSel) {
    rSel.innerHTML = options;
    rSel.value = String(STATE.profId || STATE.professors[0]?.id || '');
  }
}

/* ---------------- Relatórios ---------------- */

function reportProfessorId() {
  const sel = $('#r-prof');
  return sel ? parseInt(sel.value, 10) : STATE.userId;
}

function reportPeriodLabel() {
  const f = $('#r-from').value, t = $('#r-to').value;
  const fmt = d => d ? d.split('-').reverse().join('/') : '';
  if (f && t) return `${fmt(f)} a ${fmt(t)}`;
  if (f) return `a partir de ${fmt(f)}`;
  if (t) return `até ${fmt(t)}`;
  return 'todo o período';
}

/**
 * Início/término do trabalho apontado no dia.
 * Com qualquer registro real no dia, os limites usam somente horários reais;
 * sem nenhum real, usa os previstos. O marcador * sinaliza etapas sem
 * registro real (dia previsto ou incompleto).
 */
function dayBounds(list) {
  const rs = [], re = [], ps = [], pe = [];
  let missingReal = false;
  list.forEach(a => a.phases.forEach(p => {
    if (p.real_start) rs.push(p.real_start);
    if (p.real_end) re.push(p.real_end);
    if (!p.real_start || !p.real_end) missingReal = true;
    if (p.prev_start) ps.push(p.prev_start);
    if (p.prev_end) pe.push(p.prev_end);
  }));
  const useReal = rs.length > 0;
  const starts = useReal ? rs : ps;
  const ends = useReal ? (re.length ? re : rs) : pe;
  return {
    inicio: starts.length ? starts.reduce((m, v) => v < m ? v : m) : null,
    fim: ends.length ? ends.reduce((m, v) => v > m ? v : m) : null,
    allReal: useReal && !missingReal
  };
}

function simplifiedRows(data) {
  const days = [...new Set([...data.activities.map(a => a.date), ...data.breaks.map(b => b.date)])].sort();
  return days.map(day => {
    const list = data.activities.filter(a => a.date === day);
    const dayBreaks = data.breaks.filter(b => b.date === day);
    const { inicio, fim, allReal } = dayBounds(list);
    const restMin = dayBreaks.reduce((s, b) => s + minutesHM(b.start_time, b.end_time), 0);
    const bruto = minutesBetween(inicio, fim);
    const liquido = bruto != null ? Math.max(0, bruto - restMin) : null;
    return {
      day,
      inicio: inicio ? fmtTime(inicio) : '—',
      fim: fim ? fmtTime(fim) : '—',
      descanso: dayBreaks.map(b => `${BREAK_LABEL[b.type] || b.type} ${b.start_time}–${b.end_time}`).join('; ') || '—',
      restMin,
      liquido,
      marcador: allReal ? '' : ' *'
    };
  });
}

function signBlockHtml(prof) {
  return `
  <div class="sign-row">
    <div class="sign">
      <span class="sign-line"></span>
      <b>${esc(STATE.director)}</b>
      <small>${esc(STATE.directorRole)}</small>
    </div>
    <div class="sign">
      <span class="sign-line"></span>
      <b>${esc(prof.name)}</b>
      <small>Professor(a)${prof.rm ? ' · RM ' + esc(prof.rm) : ''}</small>
    </div>
  </div>`;
}

function reportHeaderHtml(tipo, prof) {
  return `
  <div class="paper-head">
    <h1>Relatório ${tipo} de Atividades — Home Office</h1>
    <p class="paper-org">Diário de Bordo · CECAPE</p>
    <div class="paper-meta">
      <span><b>Professor(a):</b> ${esc(prof.name)}</span>
      <span><b>RM:</b> ${esc(prof.rm || '—')}</span>
      <span><b>Período:</b> ${reportPeriodLabel()}</span>
    </div>
  </div>`;
}

function buildSimplifiedHtml(data) {
  const rows = simplifiedRows(data);
  const prof = data.professor;
  const totalMin = rows.reduce((s, r) => s + (r.liquido || 0), 0);
  const temPrevisto = rows.some(r => r.marcador);
  return `
  <div class="paper" id="paper">
    ${reportHeaderHtml('Simplificado', prof)}
    <table class="paper-table">
      <thead><tr><th>Data</th><th>Início</th><th>Término</th><th>Descanso</th><th>Horas trabalhadas</th></tr></thead>
      <tbody>
        ${rows.map(r => `<tr>
          <td>${fmtDayShort(r.day)}</td>
          <td>${r.inicio}${r.marcador}</td>
          <td>${r.fim}${r.marcador}</td>
          <td>${esc(r.descanso)}</td>
          <td>${r.liquido != null ? fmtDuration(r.liquido) : '—'}</td>
        </tr>`).join('')}
      </tbody>
      <tfoot><tr><td colspan="4"><b>Total (${rows.length} dia${rows.length !== 1 ? 's' : ''})</b></td><td><b>${fmtDuration(totalMin)}</b></td></tr></tfoot>
    </table>
    ${temPrevisto ? '<p class="paper-note">* dia com etapas ainda sem registro de horário real (horários previstos ou parciais).</p>' : ''}
    <p class="paper-issued">Documento emitido em ${new Date().toLocaleString('pt-BR')} pelo Diário de Bordo CECAPE.</p>
    ${signBlockHtml(prof)}
  </div>`;
}

function buildDetailedHtml(data) {
  const prof = data.professor;
  const days = [...new Set([...data.activities.map(a => a.date), ...data.breaks.map(b => b.date)])].sort();
  let totalMin = 0;
  data.activities.forEach(a => { totalMin += realDuration(a) || 0; });
  const body = days.map(day => {
    const list = data.activities.filter(a => a.date === day);
    const dayBreaks = data.breaks.filter(b => b.date === day);
    return `
    <div class="p-day">
      <h2>${fmtDay(day)}${dayBreaks.length ? ' — ' + dayBreaks.map(b => `${BREAK_LABEL[b.type]} ${b.start_time}–${b.end_time}`).join(' · ') : ''}</h2>
      ${list.map(a => `
        <div class="p-act">
          <h3>${esc(a.title)} <small>[${STATUS_LABEL[a.status]}${a.category ? ' · ' + esc(a.category) : ''}]</small></h3>
          ${a.description ? `<p class="p-desc">${esc(a.description)}</p>` : ''}
          <table class="paper-table p-phases">
            <thead><tr><th>Etapa</th><th>Início (Prev.)</th><th>Término (Prev.)</th><th>Início (Real)</th><th>Término (Real)</th><th>Duração real</th></tr></thead>
            <tbody>${a.phases.map(p => `<tr>
              <td>${esc(p.name)}</td>
              <td>${fmtTime(p.prev_start)}</td><td>${fmtTime(p.prev_end)}</td>
              <td>${fmtTime(p.real_start)}</td><td>${fmtTime(p.real_end)}</td>
              <td>${fmtDuration(minutesBetween(p.real_start, p.real_end))}</td>
            </tr>`).join('')}</tbody>
          </table>
        </div>`).join('') || '<p class="p-desc">Sem atividades neste dia (apenas descanso registrado).</p>'}
    </div>`;
  }).join('');
  return `
  <div class="paper" id="paper">
    ${reportHeaderHtml('Detalhado', prof)}
    ${body}
    <p class="paper-total"><b>Total de horas registradas no período:</b> ${fmtDuration(totalMin)}</p>
    <p class="paper-issued">Documento emitido em ${new Date().toLocaleString('pt-BR')} pelo Diário de Bordo CECAPE.</p>
    ${signBlockHtml(prof)}
  </div>`;
}

async function gerarRelatorio() {
  const profId = reportProfessorId();
  const from = $('#r-from').value, to = $('#r-to').value;
  const tipo = document.querySelector('input[name="r-type"]:checked')?.value || 'simplificado';
  const qs = new URLSearchParams();
  if (profId) qs.set('user_id', profId);
  if (from) qs.set('from', from);
  if (to) qs.set('to', to);
  try {
    const data = await api('list', { qs: qs.toString() });
    if (!data.activities.length && !data.breaks.length) {
      $('#report-area').innerHTML = '<div class="empty">Nenhum apontamento no período selecionado.</div>';
      $('#btn-rel-print').disabled = true;
      $('#btn-rel-pdf').disabled = true;
      return;
    }
    STATE.reportData = data;
    STATE.reportType = tipo;
    $('#report-area').innerHTML = tipo === 'simplificado' ? buildSimplifiedHtml(data) : buildDetailedHtml(data);
    $('#btn-rel-print').disabled = false;
    $('#btn-rel-pdf').disabled = false;
    toast('Relatório gerado — confira a prévia abaixo.');
  } catch (e) { toast(e.message); }
}

function printReport() {
  const paper = $('#paper');
  if (!paper) return toast('Gere o relatório primeiro.');
  $('#print-root').innerHTML = paper.outerHTML;
  document.body.classList.add('print-report');
  window.print();
  setTimeout(() => {
    document.body.classList.remove('print-report');
    $('#print-root').innerHTML = '';
  }, 600);
}

function pdfSignatures(doc, pageWidth, y) {
  const half = pageWidth / 2;
  const w = Math.min(150, half - 30);
  const x1 = half / 2 - w / 2, x2 = half + half / 2 - w / 2;
  doc.setDrawColor(60);
  doc.line(x1, y, x1 + w, y);
  doc.line(x2, y, x2 + w, y);
  doc.setFontSize(9);
  doc.setTextColor(20);
  doc.text(STATE.director, half / 2, y + 5, { align: 'center' });
  const prof = STATE.reportData.professor;
  doc.text(prof.name, half + half / 2, y + 5, { align: 'center' });
  doc.setFontSize(7.5);
  doc.setTextColor(110);
  doc.text(STATE.directorRole, half / 2, y + 9.5, { align: 'center' });
  doc.text('Professor(a)' + (prof.rm ? ' · RM ' + prof.rm : ''), half + half / 2, y + 9.5, { align: 'center' });
}

function exportReportPDF() {
  const data = STATE.reportData;
  if (!data) return toast('Gere o relatório primeiro.');
  if (typeof jspdf === 'undefined' || !jspdf.jsPDF) {
    toast('Biblioteca de PDF indisponível — use Imprimir e salve como PDF.');
    return printReport();
  }
  const prof = data.professor;
  const simplificado = STATE.reportType === 'simplificado';
  const doc = new jspdf.jsPDF({ orientation: simplificado ? 'portrait' : 'landscape', unit: 'mm', format: 'a4' });
  const pageWidth = doc.internal.pageSize.getWidth();

  doc.setFontSize(13);
  doc.setTextColor(20);
  doc.text(`Relatório ${simplificado ? 'Simplificado' : 'Detalhado'} de Atividades — Home Office`, 14, 15);
  doc.setFontSize(9);
  doc.setTextColor(110);
  doc.text(`Diário de Bordo · CECAPE`, 14, 21);
  doc.text(`Professor(a): ${prof.name}   ·   RM: ${prof.rm || '—'}   ·   Período: ${reportPeriodLabel()}   ·   Emitido em ${new Date().toLocaleString('pt-BR')}`, 14, 26);

  if (simplificado) {
    const rows = simplifiedRows(data);
    const totalMin = rows.reduce((s, r) => s + (r.liquido || 0), 0);
    doc.autoTable({
      startY: 31,
      head: [['Data', 'Início', 'Término', 'Descanso', 'Horas trabalhadas']],
      body: rows.map(r => [fmtDayShort(r.day), r.inicio + r.marcador, r.fim + r.marcador, r.descanso, r.liquido != null ? fmtDuration(r.liquido) : '—']),
      foot: [[`Total (${rows.length} dia${rows.length !== 1 ? 's' : ''})`, '', '', '', fmtDuration(totalMin)]],
      styles: { fontSize: 9, cellPadding: 2.2 },
      headStyles: { fillColor: [15, 32, 68], textColor: [255, 255, 255] },
      footStyles: { fillColor: [230, 236, 245], textColor: [20, 20, 20], fontStyle: 'bold' },
      alternateRowStyles: { fillColor: [244, 248, 252] }
    });
  } else {
    const body = [];
    data.activities.forEach(a => a.phases.forEach(p => body.push([
      a.date.split('-').reverse().join('/'), a.title, p.name,
      fmtTime(p.prev_start) + '–' + fmtTime(p.prev_end),
      fmtTime(p.real_start) + '–' + fmtTime(p.real_end),
      fmtDuration(minutesBetween(p.real_start, p.real_end)),
      STATUS_LABEL[a.status]
    ])));
    doc.autoTable({
      startY: 31,
      head: [['Data', 'Atividade', 'Etapa', 'Previsão', 'Real', 'Duração real', 'Status']],
      body,
      styles: { fontSize: 7.5, cellPadding: 1.8 },
      headStyles: { fillColor: [15, 32, 68], textColor: [255, 255, 255] },
      alternateRowStyles: { fillColor: [244, 248, 252] }
    });
  }

  let y = doc.lastAutoTable.finalY + 28;
  const pageHeight = doc.internal.pageSize.getHeight();
  if (y > pageHeight - 25) { doc.addPage(); y = 40; }
  pdfSignatures(doc, pageWidth, y);
  doc.save(`relatorio_${STATE.reportType}_${(prof.name || 'professor').toLowerCase().replace(/[^a-z0-9]+/g, '_')}_${isoDate(new Date())}.pdf`);
  toast('PDF exportado.');
}

/* ---------------- Inicialização do painel ---------------- */

async function initPanel() {
  const b = document.body.dataset;
  STATE.role = b.role;
  STATE.userId = parseInt(b.userId, 10);
  STATE.userName = b.userName;
  STATE.userRm = b.userRm;
  STATE.director = b.director;
  STATE.directorRole = b.directorRole;
  STATE.profId = STATE.role === 'gestor' ? 0 : STATE.userId;

  initTabs();
  $('#pass-form').addEventListener('submit', changePassword);
  $('#btn-xls').addEventListener('click', exportXLS);

  // Filtros do diário
  $('#f-from').addEventListener('change', () => { markPeriod(''); load(); });
  $('#f-to').addEventListener('change', () => { markPeriod(''); load(); });
  let t;
  $('#f-q').addEventListener('input', () => { clearTimeout(t); t = setTimeout(load, 300); });
  $$('[data-period]').forEach(btn => btn.addEventListener('click', () => setPeriod(btn.dataset.period)));
  $('#f-prof')?.addEventListener('change', () => {
    STATE.profId = parseInt($('#f-prof').value, 10);
    load();
  });

  // Registro (admin e professores)
  if (STATE.role !== 'gestor') {
    const now = new Date();
    STATE.phasesTemplate = (await api('default-phases')).phases;
    resetPhaseEditor();
    $('#a-date').value = isoDate(now);
    $('#a-start').value = `${pad(now.getHours())}:${pad(now.getMinutes())}`;
    $('#b-date').value = isoDate(now);
    $('#btn-add-phase').addEventListener('click', () => phaseEditorRow($('#phase-editor-rows')));
    ['#a-date', '#a-start', '#a-duration'].forEach(sel => $(sel).addEventListener('input', renderPreview));
    $('#activity-form').addEventListener('submit', submitActivity);
    $('#break-form').addEventListener('submit', submitBreak);
    renderPreview();
  }

  // Relatórios
  const today = new Date();
  $('#r-from').value = isoDate(new Date(today.getFullYear(), today.getMonth(), 1));
  $('#r-to').value = isoDate(new Date(today.getFullYear(), today.getMonth() + 1, 0));
  $('#btn-gerar').addEventListener('click', gerarRelatorio);
  $('#btn-rel-print').addEventListener('click', printReport);
  $('#btn-rel-pdf').addEventListener('click', exportReportPDF);

  // Contas (admin)
  if (STATE.role === 'admin') {
    resetUserForm();
    $('#btn-u-add-phase').addEventListener('click', () => userPhaseRow());
    $('#user-form').addEventListener('submit', submitUser);
    $('#u-cancel').addEventListener('click', resetUserForm);
    await loadUsers();
  }

  await loadProfessors();
  if (STATE.role === 'gestor') {
    STATE.profId = STATE.professors[0]?.id || 0;
    if ($('#f-prof')) $('#f-prof').value = String(STATE.profId);
  }
  setPeriod('mes');
}

/* Script carregado com defer: o DOM já está pronto quando executa.
   Inicializa direto, sem esperar DOMContentLoaded (que aguardaria o CDN). */
if (document.body.dataset.page === 'panel') {
  initPanel();
}
