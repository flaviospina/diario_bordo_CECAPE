/* Diário de Bordo CECAPE — front-end (visualização + admin) */
/* global XLSX, jspdf */

let STATE = { activities: [], admin: false };

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

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

function isoDate(d) {
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function fmtDay(iso) {
  const d = new Date(iso + 'T12:00:00');
  return d.toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });
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

function fmtDuration(min) {
  if (min == null || isNaN(min) || min < 0) return '—';
  const h = Math.floor(min / 60), m = min % 60;
  if (h && m) return `${h}h ${pad(m)}min`;
  if (h) return `${h}h`;
  return `${m}min`;
}

const STATUS_LABEL = {
  prevista: 'Prevista',
  em_andamento: 'Em andamento',
  concluida: 'Concluída'
};

async function api(action, opts = {}) {
  const url = `index.php?r=api/${action}${opts.qs ? '&' + opts.qs : ''}`;
  const res = await fetch(url, opts.body ? {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrfToken()
    },
    body: JSON.stringify(opts.body)
  } : undefined);
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || 'Erro na requisição.');
  return data;
}

/* ---------------- Filtros ---------------- */

function currentFilters() {
  return {
    from: $('#f-from').value,
    to: $('#f-to').value,
    q: $('#f-q').value.trim()
  };
}

function setPeriod(kind) {
  const today = new Date();
  const from = new Date(today);
  if (kind === 'hoje') { /* from = today */ }
  else if (kind === 'semana') from.setDate(today.getDate() - today.getDay() + 1);
  else if (kind === 'mes') from.setDate(1);
  else { $('#f-from').value = ''; $('#f-to').value = ''; load(); return; }
  $('#f-from').value = isoDate(from);
  $('#f-to').value = isoDate(today);
  load();
}

/* ---------------- Carregamento e renderização ---------------- */

async function load() {
  const { from, to, q } = currentFilters();
  const qs = new URLSearchParams();
  if (from) qs.set('from', from);
  if (to) qs.set('to', to);
  if (q) qs.set('q', q);
  const data = await api('list', { qs: qs.toString() });
  STATE.activities = data.activities;
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
  const days = new Set(acts.map(a => a.date)).size;
  $('#stats').innerHTML = `
    <div class="card stat"><div class="label">Atividades</div><div class="value">${acts.length}</div></div>
    <div class="card stat"><div class="label">Concluídas</div><div class="value">${done} <small>/ ${acts.length}</small></div></div>
    <div class="card stat"><div class="label">Em andamento</div><div class="value">${inProgress}</div></div>
    <div class="card stat"><div class="label">Horas registradas</div><div class="value">${fmtDuration(totalMin)}</div></div>
    <div class="card stat"><div class="label">Dias de trabalho</div><div class="value">${days}</div></div>`;
}

function phaseRow(p, admin) {
  const started = !!p.real_start, done = !!p.real_end;
  const cls = done ? 'done' : (started ? 'started' : '');
  const realDur = minutesBetween(p.real_start, p.real_end);
  let actions = '';
  if (admin) {
    if (!started && !done) {
      actions = `<button class="btn small primary" data-phase="${p.id}" data-op="start">Iniciar</button>`;
    } else if (!done) {
      actions = `<button class="btn small success" data-phase="${p.id}" data-op="finish">Concluir</button>`;
    } else {
      actions = `<button class="btn small ghost" data-phase="${p.id}" data-op="undo" title="Limpar horários reais">Refazer</button>`;
    }
    actions += `<button class="btn small ghost" data-phase="${p.id}" data-op="edit" title="Editar horários manualmente">Editar</button>`;
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

function renderList() {
  const admin = STATE.admin;
  const wrap = $('#list');
  const acts = STATE.activities;
  if (!acts.length) {
    wrap.innerHTML = `<div class="card empty">Nenhuma atividade registrada no período selecionado.</div>`;
    return;
  }
  const byDay = {};
  acts.forEach(a => (byDay[a.date] ||= []).push(a));

  wrap.innerHTML = Object.entries(byDay).map(([day, list]) => {
    const dayMin = list.reduce((s, a) => s + (realDuration(a) || 0), 0);
    return `
    <section class="day-group">
      <div class="day-head">
        <h2>${fmtDay(day)}</h2>
        <span>${list.length} atividade${list.length > 1 ? 's' : ''}${dayMin ? ' · ' + fmtDuration(dayMin) + ' registradas' : ''}</span>
      </div>
      ${list.map(a => activityCard(a, admin)).join('')}
    </section>`;
  }).join('');

  if (admin) bindAdminListEvents();
}

function activityCard(a, admin) {
  const dur = realDuration(a);
  const prevDur = minutesBetween(a.prev_start, a.prev_end);
  return `
  <article class="card activity" data-id="${a.id}">
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
      ${admin ? `<div class="activity-actions">
        <button class="btn small ghost" data-act="${a.id}" data-op="edit-activity">Editar</button>
        <button class="btn small danger-ghost" data-act="${a.id}" data-op="delete">Excluir</button>
      </div>` : ''}
    </div>
    <div class="phases">${a.phases.map(p => phaseRow(p, admin)).join('')}</div>
  </article>`;
}

/* ---------------- Ações do admin na lista ---------------- */

function findPhase(id) {
  for (const a of STATE.activities) {
    const p = a.phases.find(p => p.id == id);
    if (p) return p;
  }
  return null;
}

function bindAdminListEvents() {
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
        if (!confirm('Limpar os horários reais desta fase?')) return;
        await api('phase', { body: { id, op } });
      } else {
        await api('phase', { body: { id, op } });
      }
      await load();
      toast('Fase atualizada.');
    } catch (e) { toast(e.message); }
  }));

  $$('#list [data-act]').forEach(btn => btn.addEventListener('click', async () => {
    const id = btn.dataset.act, op = btn.dataset.op;
    const a = STATE.activities.find(x => x.id == id);
    try {
      if (op === 'delete') {
        if (!confirm(`Excluir a atividade "${a.title}" e todas as suas fases?`)) return;
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
}

/* ---------------- Exportações ---------------- */

function exportRows() {
  const rows = [];
  STATE.activities.forEach(a => {
    a.phases.forEach(p => {
      rows.push({
        'Data': a.date.split('-').reverse().join('/'),
        'Atividade': a.title,
        'Categoria': a.category || '',
        'Status': STATUS_LABEL[a.status],
        'Fase': p.name,
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

function periodLabel() {
  const { from, to } = currentFilters();
  const f = d => d ? d.split('-').reverse().join('/') : '';
  if (from && to) return `${f(from)} a ${f(to)}`;
  if (from) return `a partir de ${f(from)}`;
  if (to) return `até ${f(to)}`;
  return 'todo o período';
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
    // Alternativa sem biblioteca: tabela HTML compatível com o Excel (.xls)
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

function exportPDF() {
  const rows = exportRows();
  if (!rows.length) return toast('Nada para exportar no período.');
  if (typeof jspdf === 'undefined' || !jspdf.jsPDF) {
    toast('Gerando PDF pela impressão do navegador…');
    window.print();
    return;
  }
  const doc = new jspdf.jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
  doc.setFontSize(14);
  doc.text('Diário de Bordo — CECAPE', 14, 14);
  doc.setFontSize(9);
  doc.setTextColor(120);
  doc.text(`Registro de atividades em home office · Período: ${periodLabel()} · Emitido em ${new Date().toLocaleString('pt-BR')}`, 14, 20);
  const cols = ['Data', 'Atividade', 'Fase', 'Início (Previsão)', 'Término (Previsão)', 'Início (Real)', 'Término (Real)', 'Duração real', 'Status'];
  doc.autoTable({
    startY: 25,
    head: [cols],
    body: rows.map(r => cols.map(c => r[c])),
    styles: { fontSize: 7.5, cellPadding: 1.6 },
    headStyles: { fillColor: [79, 70, 229] },
    alternateRowStyles: { fillColor: [247, 247, 250] }
  });
  doc.save(`diario_bordo_cecape_${isoDate(new Date())}.pdf`);
  toast('PDF exportado.');
}

function doPrint() {
  $('#print-period').textContent = `Período: ${periodLabel()} · Emitido em ${new Date().toLocaleString('pt-BR')}`;
  window.print();
}

/* ---------------- Inicialização ---------------- */

async function init() {
  const me = await api('me').catch(() => ({ admin: false }));
  STATE.admin = !!me.admin && document.body.dataset.page === 'admin';

  $('#btn-xls').addEventListener('click', exportXLS);
  $('#btn-pdf').addEventListener('click', exportPDF);
  $('#btn-print').addEventListener('click', doPrint);
  $('#f-from').addEventListener('change', load);
  $('#f-to').addEventListener('change', load);
  let t;
  $('#f-q').addEventListener('input', () => { clearTimeout(t); t = setTimeout(load, 300); });
  $$('[data-period]').forEach(b => b.addEventListener('click', () => setPeriod(b.dataset.period)));

  // Período inicial: mês atual
  const today = new Date();
  const first = new Date(today.getFullYear(), today.getMonth(), 1);
  $('#f-from').value = isoDate(first);
  $('#f-to').value = isoDate(today);

  await load();
}

document.addEventListener('DOMContentLoaded', init);
