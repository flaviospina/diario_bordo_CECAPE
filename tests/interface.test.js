/*
 * Teste ponta a ponta da interface (Playwright + Chromium) contra a API real.
 *
 *   php -S localhost:8124 -t .          # servidor PHP na raiz do projeto
 *   node tests/interface.test.js
 *
 * Requer o banco criado, api/config.php preenchido e um admin cadastrado.
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
const BASE = process.env.BASE_URL || 'http://localhost:8124';
const ADMIN = { usuario: process.env.ADMIN_USER || 'flavio', senha: process.env.ADMIN_PASS || 'DiarioCecape2026' };
const LEITOR = { usuario: 'leitor.e2e', senha: 'LeitorCecape2026', nome: 'Leitor E2E', email: 'leitor.e2e@exemplo.com' };

fs.mkdirSync(OUT, { recursive: true });

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await ctx.newPage();

  // Só contam erros de JavaScript. Respostas 401/403/422 são provocadas de
  // propósito por esta suíte e o navegador as registra no console.
  const erros = [];
  page.on('console', m => {
    if (m.type() === 'error' && !/Failed to load resource/.test(m.text())) {
      erros.push('console: ' + m.text());
    }
  });
  page.on('pageerror', err => erros.push('pageerror: ' + err.message));

  const passos = [];
  const check = (nome, cond, extra) => {
    passos.push((cond ? '✓ ' : '✗ ') + nome + (cond ? '' : '  → ' + JSON.stringify(extra)));
  };

  const entrar = async (usuario, senha) => {
    await page.goto(BASE + '/login.html', { waitUntil: 'networkidle' });
    await page.waitForTimeout(500);
    // Já havia sessão aberta: o login redireciona para a aplicação, então sai antes.
    if (page.url().includes('index.html')) {
      await page.click('#btnSair');
      await page.waitForTimeout(900);
      await page.goto(BASE + '/login.html', { waitUntil: 'networkidle' });
      await page.waitForTimeout(400);
    }
    await page.fill('#usuario', usuario);
    await page.fill('#senha', senha);
    await page.click('#btnEntrar');
    await page.waitForTimeout(900);
  };

  /* ------------------------------------------------ acesso sem sessão -- */
  await page.goto(BASE + '/index.html', { waitUntil: 'networkidle' });
  await page.waitForTimeout(800);
  check('index.html sem sessão redireciona ao login',
    page.url().includes('login.html'), page.url());
  await page.screenshot({ path: OUT + '/00-login.png', fullPage: true });

  await page.fill('#usuario', ADMIN.usuario);
  await page.fill('#senha', 'senhaTotalmenteErrada1');
  await page.click('#btnEntrar');
  await page.waitForTimeout(700);
  check('senha errada mostra erro e permanece no login',
    page.url().includes('login.html') && await page.isVisible('#erroLogin'), page.url());
  const msgErro = await page.textContent('#erroLogin');
  check('mensagem genérica, sem revelar a conta',
    msgErro.includes('Usuário ou senha inválidos'), msgErro);

  /* ------------------------------------------------------ login admin -- */
  await entrar(ADMIN.usuario, ADMIN.senha);
  check('login válido entra na aplicação', page.url().includes('index.html'), page.url());
  check('nome do usuário aparece no topo',
    (await page.textContent('#usuarioNome')).length > 1);
  check('selo mostra Administrador',
    (await page.textContent('#seloPerfilTxt')) === 'Administrador');
  check('abas de admin visíveis', await page.isVisible('.aba[data-view="usuarios"]'));
  await page.screenshot({ path: OUT + '/01-painel-admin.png', fullPage: true });

  /* -------------------------------------------- criar atividade (API) -- */
  await page.click('.aba[data-view="nova"]');
  await page.waitForTimeout(400);
  await page.fill('#aTitulo', 'Oficina de robótica educacional');
  await page.fill('#aResponsavel', 'Prof. Flávio Spina');
  await page.fill('#aCategoria', 'Ensino');
  await page.fill('#aLocal', 'Laboratório 3');
  await page.fill('#aDescricao', 'Montagem de kits e programação em blocos.');
  await page.fill('#aInicio', '2030-05-06T10:00');
  await page.selectOption('#aModelo', 'aula');
  await page.fill('#aHoras', '6');
  await page.waitForTimeout(600);

  const previsao = await page.$$eval('#corpoPrevisao tr', els =>
    els.map(tr => Array.from(tr.querySelectorAll('td')).map(td => td.textContent.trim())));
  check('previsão calculou 4 fases', previsao.length === 4, previsao);
  check('última fase termina 06/05/2030 17:00 (6h úteis c/ almoço)',
    previsao[3] && previsao[3][3].includes('06/05/2030 17:00'), previsao[3]);
  await page.screenshot({ path: OUT + '/02-nova-atividade.png', fullPage: true });

  await page.click('#btnSalvarAtividade');
  await page.waitForTimeout(1200);
  check('após salvar abre o detalhe', await page.isVisible('#modalFundo'));
  const tituloModal = await page.textContent('#modalTitulo');
  check('modal mostra o título salvo', tituloModal.includes('robótica'), tituloModal);
  await page.screenshot({ path: OUT + '/03-detalhe-admin.png', fullPage: true });

  /* ------------------------------------------- registrar execução real -- */
  await page.click('#modalCorpo button[data-acao="iniciar"]');
  await page.waitForTimeout(900);
  check('fase iniciada aparece como "Em andamento"',
    (await page.textContent('#modalCorpo')).includes('Em andamento'));
  await page.click('#modalCorpo button[data-acao="concluir"]');
  await page.waitForTimeout(900);
  check('fase concluída registrada', (await page.textContent('#modalCorpo')).includes('Concluída'));

  const sub = await page.textContent('#modalSub');
  check('progresso avançou', +(sub.match(/(\d+)% concluído/) || [])[1] > 0, sub);

  await page.click('#modalRodape [data-fechar]');
  await page.waitForTimeout(300);

  /* -------------------------------------- persistência real (recarrega) -- */
  await page.reload({ waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);
  await page.click('.aba[data-view="registros"]');
  await page.waitForTimeout(500);
  const linhas = await page.$$eval('#corpoRegistros tr', els => els.length);
  check('atividade persistida no MySQL após recarregar', linhas >= 1, linhas);
  check('continua autenticado após recarregar (cookie de sessão)',
    (await page.textContent('#seloPerfilTxt')) === 'Administrador');

  /* --------------------------------------------------------- exportações */
  const dlXls = page.waitForEvent('download', { timeout: 8000 });
  await page.click('#btnXLS');
  const xls = await dlXls;
  await xls.saveAs(OUT + '/relatorio.xls');
  check('XLS exportado', /\.xls$/.test(xls.suggestedFilename()), xls.suggestedFilename());

  const dlPdf = page.waitForEvent('download', { timeout: 8000 });
  await page.click('#btnPDF');
  const pdf = await dlPdf;
  await pdf.saveAs(OUT + '/relatorio.pdf');
  const buf = fs.readFileSync(OUT + '/relatorio.pdf');
  check('PDF exportado e íntegro',
    buf.slice(0, 8).toString() === '%PDF-1.4' && buf.slice(-6).toString().trim() === '%%EOF');
  await page.screenshot({ path: OUT + '/04-registros.png', fullPage: true });

  /* ------------------------------------------------- gestão de usuários -- */
  await page.click('.aba[data-view="usuarios"]');
  await page.waitForTimeout(700);
  check('tela de usuários lista contas',
    (await page.$$('#corpoUsuarios tr')).length >= 1);
  check('auditoria mostra registros',
    (await page.$$('#corpoAuditoria tr')).length >= 1);

  await page.click('#btnNovoUsuario');
  await page.waitForTimeout(300);
  await page.fill('#uNome', LEITOR.nome);
  await page.fill('#uUsuario', LEITOR.usuario);
  await page.fill('#uEmail', LEITOR.email);
  await page.fill('#uSenha', '123');
  await page.click('#btnSalvarUsuario');
  await page.waitForTimeout(700);
  check('senha fraca é recusada pelo servidor', await page.isVisible('#modalFundo'));

  await page.fill('#uSenha', LEITOR.senha);
  await page.click('#btnSalvarUsuario');
  await page.waitForTimeout(900);
  check('usuário de visualização criado', !(await page.isVisible('#modalFundo')));
  await page.screenshot({ path: OUT + '/05-usuarios.png', fullPage: true });

  /* ------------------------------------------- perfil de visualização -- */
  await page.click('#btnSair');
  await page.waitForTimeout(900);
  check('logout leva ao login', page.url().includes('login.html'), page.url());

  await entrar(LEITOR.usuario, LEITOR.senha);
  check('primeiro acesso exige troca de senha',
    await page.isVisible('#caixaTroca'), page.url());

  await page.fill('#senhaAtual', LEITOR.senha);
  await page.fill('#novaSenha', 'NovaSenhaLeitor2026');
  await page.fill('#novaSenha2', 'NovaSenhaLeitor2026');
  await page.click('#btnTrocar');
  await page.waitForTimeout(1200);
  check('após trocar a senha entra na aplicação', page.url().includes('index.html'), page.url());
  check('selo mostra Visualização',
    (await page.textContent('#seloPerfilTxt')) === 'Visualização');
  check('aba "Nova atividade" oculta', !(await page.isVisible('.aba[data-view="nova"]')));
  check('aba "Usuários" oculta', !(await page.isVisible('.aba[data-view="usuarios"]')));
  check('aba "Configurações" oculta', !(await page.isVisible('.aba[data-view="config"]')));

  await page.click('.aba[data-view="registros"]');
  await page.waitForTimeout(500);
  check('visualizador vê os registros',
    (await page.$$('#corpoRegistros tr')).length >= 1);

  await page.click('#corpoRegistros tr');
  await page.waitForTimeout(500);
  check('visualizador abre o detalhe', await page.isVisible('#modalFundo'));
  check('detalhe sem botões de edição',
    !(await page.isVisible('#modalRodape [data-modal-acao="editar"]')));
  check('detalhe sem botão de exclusão',
    !(await page.isVisible('#modalRodape [data-modal-acao="excluir"]')));
  check('fases em somente leitura (sem campos de data)',
    (await page.$$('#modalCorpo input[type="datetime-local"]')).length === 0);
  check('visualizador ainda exporta e imprime',
    await page.isVisible('#modalRodape [data-modal-acao="pdf"]') &&
    await page.isVisible('#modalRodape [data-modal-acao="imprimir"]'));
  await page.screenshot({ path: OUT + '/06-detalhe-visualizacao.png', fullPage: true });
  await page.click('#modalRodape [data-fechar]');

  // Mesmo forçando a chamada pela API, o servidor recusa.
  const tentativa = await page.evaluate(async () => {
    try {
      await window.Api.criarAtividade({
        titulo: 'Tentativa indevida', inicioPrevisto: '2030-01-01T08:00',
        fimPrevisto: '2030-01-01T09:00',
        fases: [{ nome: 'F', peso: 100, duracaoMin: 60, inicioPrevisto: '2030-01-01T08:00', fimPrevisto: '2030-01-01T09:00' }]
      });
      return { status: 200 };
    } catch (e) { return { status: e.status, msg: e.message }; }
  });
  check('API recusa escrita do visualizador mesmo por chamada direta (403)',
    tentativa.status === 403, tentativa);

  /* ------------------------------------------------------- tema escuro -- */
  await page.click('#btnTema');
  await page.waitForTimeout(400);
  check('tema escuro aplicado', (await page.getAttribute('html', 'data-tema')) === 'escuro');
  await page.screenshot({ path: OUT + '/07-tema-escuro.png', fullPage: true });
  await page.click('#btnTema');
  await page.waitForTimeout(300);

  /* --------------------------------------------------------- responsivo -- */
  await page.setViewportSize({ width: 400, height: 900 });
  await page.click('.aba[data-view="painel"]');
  await page.waitForTimeout(500);
  const larguras = await page.evaluate(() => ({
    scroll: document.documentElement.scrollWidth,
    client: document.documentElement.clientWidth
  }));
  check('sem rolagem horizontal no celular', larguras.scroll <= larguras.client, larguras);
  await page.screenshot({ path: OUT + '/08-mobile.png', fullPage: true });

  /* ------------------------------------------------------------ limpeza -- */
  await page.setViewportSize({ width: 1440, height: 1000 });
  await entrar(ADMIN.usuario, ADMIN.senha);
  const limpeza = await page.evaluate(async (login) => {
    const usuarios = await window.Api.listarUsuarios();
    const alvo = usuarios.find(u => u.usuario === login);
    if (alvo) await window.Api.excluirUsuario(alvo.id);
    const atividades = await window.Api.listarAtividades();
    for (const a of atividades) await window.Api.excluirAtividade(a.id);
    return { usuario: !!alvo, atividades: atividades.length };
  }, LEITOR.usuario);
  check('dados de teste removidos', limpeza.usuario && limpeza.atividades >= 1, limpeza);

  console.log(passos.join('\n'));
  console.log('\nErros de console/página: ' + (erros.length ? '\n  ' + erros.join('\n  ') : 'nenhum'));
  const falhas = passos.filter(p => p.startsWith('✗')).length;
  console.log(falhas ? `\n✗ ${falhas} verificação(ões) falharam` : '\n✓ todas as verificações passaram');

  await browser.close();
  process.exit(falhas || erros.length ? 1 : 0);
})();
