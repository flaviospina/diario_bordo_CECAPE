/* Diário de Bordo CECAPE — painel do administrador */
/* global api, $, $$, toast, load, esc, pad, isoDate */

let DEFAULT_PHASES = [];

function showLogin(show) {
  $('#login-wrap').style.display = show ? '' : 'none';
  $('#admin-app').style.display = show ? 'none' : '';
}

async function doLogin(ev) {
  ev.preventDefault();
  $('#login-error').textContent = '';
  try {
    await api('login', { body: { password: $('#login-pass').value } });
    location.reload();
  } catch (e) {
    $('#login-error').textContent = e.message;
  }
}

async function doLogout() {
  await api('logout', { body: {} });
  location.reload();
}

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

/* ---------- Editor de fases + previsão ---------- */

function phaseEditorRow(name = '', weight = '') {
  const row = document.createElement('div');
  row.className = 'row';
  row.innerHTML = `
    <input type="text" placeholder="Nome da fase" value="${esc(name)}" class="form-control form-sm ph-name">
    <input type="number" min="1" max="100" placeholder="%" value="${esc(weight)}" class="form-control form-sm ph-weight" title="Peso da fase (% da duração)">
    <button type="button" class="btn-icon ph-del" title="Remover fase">✕</button>`;
  row.querySelector('.ph-del').addEventListener('click', () => { row.remove(); renderPreview(); });
  row.querySelectorAll('input').forEach(i => i.addEventListener('input', renderPreview));
  return row;
}

function editorPhases() {
  return $$('#phase-editor-rows .row').map(r => ({
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
  const date = $('#a-date').value;
  const time = $('#a-start').value;
  const duration = parseInt($('#a-duration').value, 10);
  const phases = editorPhases();
  const box = $('#preview');
  if (!date || !time || !duration || !phases.length) {
    box.innerHTML = '<span class="preview-note">Informe data, início e duração para ver a previsão das fases.</span>';
    return;
  }
  const hhmm = d => `${pad(d.getHours())}:${pad(d.getMinutes())}`;
  const items = computePreview(date, time, duration, phases);
  box.innerHTML = '<span class="preview-note">Previsão gerada automaticamente: </span>' +
    items.map(p => `<b>${esc(p.name)}</b> ${hhmm(p.start)}–${hhmm(p.end)}`).join(' · ');
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
    phases: editorPhases()
  };
  if (!body.title || !body.date || !body.start_time || body.duration < 1) {
    return toast('Preencha título, data, horário de início e duração.');
  }
  try {
    await api('create', { body });
    toast('Atividade registrada com as fases previstas.');
    $('#a-title').value = '';
    $('#a-desc').value = '';
    resetPhaseEditor();
    renderPreview();
    await load();
  } catch (e) { toast(e.message); }
}

function resetPhaseEditor() {
  const editor = $('#phase-editor-rows');
  editor.innerHTML = '';
  DEFAULT_PHASES.forEach(p => editor.appendChild(phaseEditorRow(p.name, p.weight)));
}

/* ---------- Inicialização do admin ---------- */

async function initAdmin() {
  const me = await api('me').catch(() => ({ admin: false }));
  showLogin(!me.admin);
  $('#login-form')?.addEventListener('submit', doLogin);
  if (!me.admin) return;

  $('#btn-logout').style.display = '';
  $('#btn-logout').addEventListener('click', doLogout);
  $('#pass-form').addEventListener('submit', changePassword);

  DEFAULT_PHASES = (await api('default-phases')).phases;
  resetPhaseEditor();

  const now = new Date();
  $('#a-date').value = isoDate(now);
  $('#a-start').value = `${pad(now.getHours())}:${pad(now.getMinutes())}`;

  $('#btn-add-phase').addEventListener('click', () => {
    $('#phase-editor-rows').appendChild(phaseEditorRow());
  });
  ['#a-date', '#a-start', '#a-duration'].forEach(sel =>
    $(sel).addEventListener('input', renderPreview));
  $('#activity-form').addEventListener('submit', submitActivity);
  renderPreview();
}

document.addEventListener('DOMContentLoaded', initAdmin);
