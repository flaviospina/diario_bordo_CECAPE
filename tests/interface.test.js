/*
 * Teste ponta a ponta da interface (Playwright + Chromium).
 *
 *   npx http-server -p 8123 -s .        # servidor estático na raiz do projeto
 *   node tests/interface.test.js        # em outro terminal
 *
 * Requer o Playwright instalado (npm i -D playwright, ou instalação global).
 * As telas capturadas ficam em tests/.saida/.
 */
const fs = require('fs');
const path = require('path');

let chromium;
try {
  ({ chromium } = require('playwright'));
} catch (e) {
  console.error('Playwright não encontrado. Instale com: npm i -D playwright');
  process.exit(2);
}

const OUT = path.join(__dirname, '.saida');
const BASE = process.env.BASE_URL || 'http://localhost:8123';
fs.mkdirSync(OUT, { recursive: true });

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await ctx.newPage();

  const erros = [];
  page.on('console', m => { if (m.type() === 'error') erros.push('console: ' + m.text()); });
  page.on('pageerror', err => erros.push('pageerror: ' + err.message));

  await page.goto(BASE + '/index.html', { waitUntil: 'networkidle' });
  await page.waitForTimeout(400);

  const passos = [];
  const check = (nome, cond, extra) => {
    passos.push((cond ? '✓ ' : '✗ ') + nome + (cond ? '' : '  → ' + extra));
  };

  // ---------- painel inicial (modo usuário)
  const kpis = await page.$$eval('.kpi', els => els.map(e => e.querySelector('.kpi-valor').textContent));
  check('painel exibe 5 KPIs', kpis.length === 5, JSON.stringify(kpis));
  check('dados de exemplo carregados', kpis[0] === '4', kpis[0]);
  check('abas de admin ocultas para usuário',
    !(await page.isVisible('.aba[data-view="nova"]')));
  await page.screenshot({ path: OUT + '/01-painel-usuario.png', fullPage: true });

  // ---------- registros + exportações no modo usuário
  await page.click('.aba[data-view="registros"]');
  await page.waitForTimeout(250);
  const linhas = await page.$$eval('#corpoRegistros tr', els => els.length);
  check('tabela de registros lista 4 atividades', linhas === 4, String(linhas));

  // filtro por busca
  await page.fill('#fBusca', 'manutencao');
  await page.waitForTimeout(400);
  const filtradas = await page.$$eval('#corpoRegistros tr', els => els.length);
  check('busca sem acento encontra "Manutenção"', filtradas === 1, String(filtradas));
  await page.fill('#fBusca', '');
  await page.waitForTimeout(350);
  await page.screenshot({ path: OUT + '/02-registros.png', fullPage: true });

  // download XLS
  const dlXls = page.waitForEvent('download', { timeout: 8000 });
  await page.click('#btnXLS');
  const xls = await dlXls;
  const caminhoXls = OUT + '/relatorio.xls';
  await xls.saveAs(caminhoXls);
  const conteudoXls = fs.readFileSync(caminhoXls, 'utf8');
  check('XLS gerado com nome correto', /^diario-de-bordo_\d{8}-\d{4}\.xls$/.test(xls.suggestedFilename()),
    xls.suggestedFilename());
  check('XLS contém as 3 planilhas',
    conteudoXls.includes('ss:Name="Atividades"') && conteudoXls.includes('ss:Name="Fases"') &&
    conteudoXls.includes('ss:Name="Informa'), 'planilhas ausentes');
  check('XLS traz linhas de fases', (conteudoXls.match(/<Row>/g) || []).length > 12,
    String((conteudoXls.match(/<Row>/g) || []).length));

  // download PDF
  const dlPdf = page.waitForEvent('download', { timeout: 8000 });
  await page.click('#btnPDF');
  const pdf = await dlPdf;
  const caminhoPdf = OUT + '/relatorio.pdf';
  await pdf.saveAs(caminhoPdf);
  const bufPdf = fs.readFileSync(caminhoPdf);
  check('PDF começa com %PDF-1.4', bufPdf.slice(0, 8).toString() === '%PDF-1.4');
  check('PDF termina com %%EOF', bufPdf.slice(-6).toString().trim() === '%%EOF');
  check('PDF tem tamanho plausível', bufPdf.length > 3000, String(bufPdf.length));

  // ---------- entrar como admin
  await page.click('#btnPerfil');
  await page.waitForTimeout(250);
  await page.fill('#campoPin', '9999');
  await page.click('#btnConfirmarPin');
  await page.waitForTimeout(200);
  check('PIN incorreto é rejeitado', await page.isVisible('#campoPin'));
  await page.fill('#campoPin', '1234');
  await page.click('#btnConfirmarPin');
  await page.waitForTimeout(300);
  check('PIN correto entra no modo admin',
    (await page.textContent('#seloPerfilTxt')) === 'Administrador');
  check('abas de admin visíveis', await page.isVisible('.aba[data-view="nova"]'));

  // ---------- criar atividade
  await page.click('.aba[data-view="nova"]');
  await page.waitForTimeout(300);
  await page.fill('#aTitulo', 'Oficina de robótica educacional');
  await page.fill('#aResponsavel', 'Prof. Flávio Spina');
  await page.fill('#aCategoria', 'Ensino');
  await page.fill('#aLocal', 'Laboratório 3');
  await page.fill('#aDescricao', 'Montagem de kits e programação em blocos com a turma do 2º módulo.');
  await page.fill('#aInicio', '2025-05-05T10:00');
  await page.selectOption('#aModelo', 'aula');
  await page.fill('#aHoras', '6');
  await page.waitForTimeout(500);

  const previsao = await page.$$eval('#corpoPrevisao tr', els =>
    els.map(tr => Array.from(tr.querySelectorAll('td')).map(td => td.textContent.trim())));
  check('previsão gerou 4 fases', previsao.length === 4, JSON.stringify(previsao));
  check('1ª fase inicia seg 05/05/2025 10:00',
    previsao[0] && previsao[0][2].includes('05/05/2025 10:00'), JSON.stringify(previsao[0]));
  // 10:00→12:00 (2h) + 13:00→17:00 (4h) = 6h úteis descontando o almoço
  check('última fase termina 05/05/2025 17:00 (6h úteis c/ almoço)',
    previsao[3] && previsao[3][3].includes('05/05/2025 17:00'), JSON.stringify(previsao[3]));
  // a fase que atravessa o almoço deve ter 60 min a mais de relógio do que de trabalho
  const cruzando = await page.evaluate(() => {
    const p = Schedule.planejar({
      inicio: '2025-05-05T10:00', duracaoMin: 360,
      fases: Schedule.modelo('aula').fases.map(f => ({ nome: f.nome, peso: f.peso })),
      distribuir: 'peso', jornada: Store.config().jornada
    });
    return p.fases.map(f => ({
      relogio: Util.diffMin(f.inicioPrevisto, f.fimPrevisto), trabalho: f.duracaoMin
    }));
  });
  check('fase que cruza o almoço ganha 60 min de relógio',
    cruzando.some(f => f.relogio - f.trabalho === 60) &&
    cruzando.filter(f => f.relogio !== f.trabalho).length === 1, JSON.stringify(cruzando));
  const resumo = await page.textContent('#resumoPrevisao');
  check('resumo mostra duração total 6h', resumo.includes('6h'), resumo);
  await page.screenshot({ path: OUT + '/03-nova-atividade.png', fullPage: true });

  // adicionar e mover fase
  await page.click('#btnAddFase');
  await page.waitForTimeout(250);
  check('fase adicionada', (await page.$$('#editorFases .linha-fase')).length === 5);
  await page.click('#editorFases .linha-fase:last-child button[data-mover="-1"]');
  await page.waitForTimeout(250);
  const nomes = await page.$$eval('#editorFases input[data-campo="nome"]', els => els.map(e => e.value));
  check('fase movida para cima', nomes[3] === 'Nova fase', JSON.stringify(nomes));
  await page.click('#editorFases .linha-fase:nth-child(4) button[data-remover]');
  await page.waitForTimeout(250);
  check('fase removida', (await page.$$('#editorFases .linha-fase')).length === 4);

  await page.click('#btnSalvarAtividade');
  await page.waitForTimeout(600);
  check('após salvar abre o detalhe da atividade', await page.isVisible('#modalFundo'));
  const tituloModal = await page.textContent('#modalTitulo');
  check('modal mostra o título salvo', tituloModal.includes('robótica'), tituloModal);
  await page.screenshot({ path: OUT + '/04-detalhe-admin.png', fullPage: true });

  // ---------- registrar execução das fases
  await page.click('#modalCorpo button[data-acao="iniciar"]');
  await page.waitForTimeout(400);
  check('fase iniciada gera situação "Em andamento"',
    (await page.textContent('#modalCorpo')).includes('Em andamento'));
  await page.click('#modalCorpo button[data-acao="concluir"]');
  await page.waitForTimeout(400);
  const corpoModal = await page.textContent('#modalCorpo');
  check('fase concluída registrada', corpoModal.includes('Concluída'));
  const sub = await page.textContent('#modalSub');
  const pct = +(sub.match(/(\d+)% concluído/) || [])[1];
  check('progresso da atividade avançou', pct > 0, sub);

  // replanejar restante
  page.once('dialog', d => d.accept());
  await page.click('#modalRodape button[data-modal-acao="replanejar"]');
  await page.waitForTimeout(500);
  check('replanejamento mantém o modal aberto', await page.isVisible('#modalFundo'));

  await page.click('#modalRodape [data-fechar]');
  await page.waitForTimeout(250);

  // ---------- persistência
  await page.reload({ waitUntil: 'networkidle' });
  await page.waitForTimeout(500);
  await page.click('.aba[data-view="registros"]');
  await page.waitForTimeout(300);
  const linhasDepois = await page.$$eval('#corpoRegistros tr', els => els.length);
  check('atividade persistiu após recarregar', linhasDepois === 5, String(linhasDepois));
  check('perfil volta para visualização após recarregar',
    (await page.textContent('#seloPerfilTxt')) === 'Visualização');

  // ---------- tema escuro
  await page.click('#btnTema');
  await page.waitForTimeout(300);
  check('tema escuro aplicado',
    (await page.getAttribute('html', 'data-tema')) === 'escuro');
  await page.screenshot({ path: OUT + '/05-tema-escuro.png', fullPage: true });
  await page.click('#btnTema');
  await page.waitForTimeout(200);

  // ---------- documento de impressão
  const html = await page.evaluate(() =>
    Export.paraHTMLImpressao(Store.listar(), { organizacao: 'CECAPE', subtitulo: 'Teste', filtros: 'Todos' }));
  fs.writeFileSync(OUT + '/impressao.html', html);
  check('HTML de impressão contém as atividades',
    html.includes('robótica') && html.includes('<table>'), 'conteúdo inesperado');

  const pagina2 = await ctx.newPage();
  await pagina2.setContent(html);
  await pagina2.screenshot({ path: OUT + '/06-impressao.png', fullPage: true });
  await pagina2.pdf({ path: OUT + '/impressao-navegador.pdf', format: 'A4', landscape: true });

  // ---------- responsivo
  await page.setViewportSize({ width: 420, height: 900 });
  await page.click('.aba[data-view="painel"]');
  await page.waitForTimeout(400);
  await page.screenshot({ path: OUT + '/07-mobile.png', fullPage: true });

  console.log(passos.join('\n'));
  console.log('\nErros de console/página: ' + (erros.length ? '\n  ' + erros.join('\n  ') : 'nenhum'));
  const falhas = passos.filter(p => p.startsWith('✗')).length;
  console.log(falhas ? '\n✗ ' + falhas + ' verificação(ões) falharam' : '\n✓ todas as verificações passaram');

  await browser.close();
  process.exit(falhas || erros.length ? 1 : 0);
})();
