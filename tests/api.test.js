/*
 * Testes da API PHP + MySQL: autenticação, autorização, CSRF, validação,
 * força bruta e tentativas reais de SQL injection.
 *
 *   php -S localhost:8124 -t .        # servidor PHP na raiz do projeto
 *   node tests/api.test.js
 *
 * Requer o banco criado (sql/schema.sql), o api/config.php preenchido e um
 * administrador criado (ferramentas/criar-admin.php).
 */

const BASE = process.env.API_URL || 'http://localhost:8124/api/index.php';
const ADMIN = { usuario: process.env.ADMIN_USER || 'flavio', senha: process.env.ADMIN_PASS || 'DiarioCecape2026' };

let falhas = 0, total = 0;
const grupo = (t) => console.log('\n[' + t + ']');
const ok = (nome, cond, extra) => {
  total++;
  if (cond) { console.log('  ✓ ' + nome); }
  else { falhas++; console.log('  ✗ ' + nome + (extra !== undefined ? '  → ' + JSON.stringify(extra) : '')); }
};

/** Cliente HTTP que guarda cookies e token CSRF, como faria o navegador. */
function criarCliente() {
  const cliente = { cookies: new Map(), csrf: null };

  cliente.pedir = async (rota, { metodo = 'GET', corpo, csrf = true, cabecalhos = {} } = {}) => {
    const headers = { ...cabecalhos };
    if (corpo !== undefined) headers['Content-Type'] = 'application/json';
    if (csrf && cliente.csrf) headers['X-CSRF-Token'] = cliente.csrf;
    if (cliente.cookies.size) {
      headers['Cookie'] = [...cliente.cookies].map(([k, v]) => `${k}=${v}`).join('; ');
    }

    // "atividades?busca=x" → ?r=atividades&busca=x
    const [caminho, query] = rota.split('?');
    const url = `${BASE}?r=${caminho}` + (query ? '&' + query : '');

    const resp = await fetch(url, {
      method: metodo,
      headers,
      body: corpo === undefined ? undefined : JSON.stringify(corpo),
      redirect: 'manual'
    });

    for (const bruto of resp.headers.getSetCookie?.() || []) {
      const [par] = bruto.split(';');
      const idx = par.indexOf('=');
      cliente.cookies.set(par.slice(0, idx).trim(), par.slice(idx + 1).trim());
    }

    let dados = null;
    const texto = await resp.text();
    try { dados = JSON.parse(texto); } catch { dados = { bruto: texto.slice(0, 300) }; }
    if (dados && dados.csrf) cliente.csrf = dados.csrf;

    return { status: resp.status, dados, cabecalhos: resp.headers };
  };

  cliente.entrar = async (usuario, senha) => {
    await cliente.pedir('auth/sessao');
    return cliente.pedir('auth/login', { metodo: 'POST', corpo: { usuario, senha } });
  };

  return cliente;
}

const atividadeValida = (extra = {}) => ({
  titulo: 'Atividade de teste automatizado',
  responsavel: 'Equipe de TI',
  categoria: 'Testes',
  local: 'Laboratório 1',
  descricao: 'Criada pela suíte de testes.',
  inicioPrevisto: '2030-03-04T08:00',
  fimPrevisto: '2030-03-04T12:00',
  fases: [
    { nome: 'Preparação', peso: 25, duracaoMin: 60, inicioPrevisto: '2030-03-04T08:00', fimPrevisto: '2030-03-04T09:00' },
    { nome: 'Execução', peso: 75, duracaoMin: 180, inicioPrevisto: '2030-03-04T09:00', fimPrevisto: '2030-03-04T12:00' }
  ],
  ...extra
});

(async () => {
  /* ------------------------------------------------------------ sessão -- */
  grupo('sessão e autenticação');
  const anonimo = criarCliente();

  let r = await anonimo.pedir('auth/sessao');
  ok('GET auth/sessao responde 200 sem login', r.status === 200, r.dados);
  ok('sessão anônima não está autenticada', r.dados.autenticado === false);
  ok('token CSRF é entregue', typeof r.dados.csrf === 'string' && r.dados.csrf.length >= 32);

  r = await anonimo.pedir('atividades');
  ok('listar atividades sem login é bloqueado (401)', r.status === 401, r.status);

  r = await anonimo.pedir('auth/login', { metodo: 'POST', corpo: ADMIN, csrf: false });
  ok('login sem token CSRF é recusado (419)', r.status === 419, r.status);

  r = await anonimo.pedir('auth/login', { metodo: 'POST', corpo: { usuario: ADMIN.usuario, senha: 'senhaErrada123' } });
  ok('senha errada devolve 401', r.status === 401, r.status);
  ok('mensagem não revela se o usuário existe',
    r.dados.erro === 'Usuário ou senha inválidos.', r.dados.erro);

  r = await anonimo.pedir('auth/login', { metodo: 'POST', corpo: { usuario: 'inexistente_zzz', senha: 'senhaErrada123' } });
  ok('usuário inexistente devolve a MESMA mensagem',
    r.dados.erro === 'Usuário ou senha inválidos.', r.dados.erro);

  const admin = criarCliente();
  r = await admin.entrar(ADMIN.usuario, ADMIN.senha);
  ok('login válido devolve 200', r.status === 200, r.dados);
  ok('perfil admin retornado', r.dados.usuario && r.dados.usuario.perfil === 'admin', r.dados.usuario);
  ok('senha nunca aparece na resposta',
    !JSON.stringify(r.dados).match(/senha_hash|\$argon2|\$2y\$/i));

  /* -------------------------------------------------------- cabeçalhos -- */
  grupo('cabeçalhos e cookies');
  const cab = r.cabecalhos;
  ok('X-Content-Type-Options: nosniff', cab.get('x-content-type-options') === 'nosniff');
  ok('X-Frame-Options: DENY', cab.get('x-frame-options') === 'DENY');
  ok('Content-Security-Policy presente', (cab.get('content-security-policy') || '').includes("default-src 'none'"));
  ok('Cache-Control impede cache', (cab.get('cache-control') || '').includes('no-store'));
  ok('X-Powered-By removido', !cab.get('x-powered-by'));

  const sessaoAnterior = admin.cookies.get('DIARIOSESSID');
  ok('cookie de sessão criado', !!sessaoAnterior);

  /* -------------------------------------------------------------- CRUD -- */
  grupo('CRUD de atividades');
  r = await admin.pedir('atividades/criar', { metodo: 'POST', corpo: atividadeValida() });
  ok('admin cria atividade (201)', r.status === 201, r.dados);
  const criada = r.dados.atividade;
  ok('id devolvido como texto', typeof criada?.id === 'string', criada?.id);
  ok('2 fases persistidas', criada?.fases?.length === 2, criada?.fases?.length);
  ok('datas no formato do input HTML',
    criada?.fases?.[0].inicioPrevisto === '2030-03-04T08:00', criada?.fases?.[0]);

  r = await admin.pedir('atividades/atualizar', {
    metodo: 'POST',
    corpo: atividadeValida({ id: criada.id, titulo: 'Atividade renomeada', fases: criada.fases.slice(0, 1) })
  });
  ok('atualização troca título e reduz fases',
    r.status === 200 && r.dados.atividade.titulo === 'Atividade renomeada' &&
    r.dados.atividade.fases.length === 1, r.dados);

  r = await admin.pedir('atividades?busca=renomeada');
  ok('busca encontra a atividade', r.dados.atividades.some(a => a.id === criada.id), r.status);

  r = await admin.pedir('atividades?busca=%25');
  ok('curinga "%" na busca não vira LIKE aberto',
    r.dados.atividades.length === 0, r.dados.atividades?.length);

  /* --------------------------------------------------- SQL injection -- */
  grupo('tentativas de SQL injection');
  const cargas = [
    "' OR '1'='1",
    "'; DROP TABLE usuarios;--",
    "admin'--",
    "' UNION SELECT id, senha_hash, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1 FROM usuarios--",
    "1' AND SLEEP(3)--",
    "\\'; DELETE FROM atividades WHERE '1'='1"
  ];

  for (const carga of cargas) {
    const t0 = Date.now();
    const login = criarCliente();
    const resLogin = await login.entrar(carga, carga);
    const ms = Date.now() - t0;
    ok(`login com «${carga.slice(0, 26)}» não autentica`,
      resLogin.status === 401 || resLogin.status === 429, resLogin.status);
    ok(`carga «${carga.slice(0, 18)}» não causa atraso (sem SLEEP)`, ms < 2500, ms + 'ms');

    const busca = await admin.pedir('atividades?busca=' + encodeURIComponent(carga));
    ok(`busca com «${carga.slice(0, 18)}» responde normal`,
      busca.status === 200 && Array.isArray(busca.dados.atividades), busca.status);
  }

  r = await admin.pedir('atividades?ordem=' + encodeURIComponent('titulo; DROP TABLE fases'));
  ok('ordenação fora da lista branca é recusada (422)', r.status === 422, r.status);

  r = await admin.pedir('atividades/obter?id=' + encodeURIComponent("1 OR 1=1"));
  ok('id não numérico é recusado', r.status === 422 || r.status === 404, r.status);

  // As tabelas continuam de pé depois de tudo isso
  r = await admin.pedir('usuarios');
  ok('tabela usuarios intacta após as cargas', r.status === 200 && r.dados.usuarios.length >= 1, r.status);
  r = await admin.pedir('atividades');
  ok('tabela atividades intacta após as cargas', r.status === 200 && Array.isArray(r.dados.atividades));

  /* ------------------------------------------------------- validação -- */
  grupo('validação de entrada');
  r = await admin.pedir('atividades/criar', { metodo: 'POST', corpo: atividadeValida({ titulo: '' }) });
  ok('título vazio é recusado (422)', r.status === 422, r.status);

  r = await admin.pedir('atividades/criar', { metodo: 'POST', corpo: atividadeValida({ titulo: 'x'.repeat(300) }) });
  ok('título longo demais é recusado', r.status === 422, r.status);

  r = await admin.pedir('atividades/criar', { metodo: 'POST', corpo: atividadeValida({ inicioPrevisto: '2030-13-45T99:99' }) });
  ok('data inválida é recusada', r.status === 422, r.status);

  r = await admin.pedir('atividades/criar', {
    metodo: 'POST', corpo: atividadeValida({ inicioPrevisto: '2030-03-04T12:00', fimPrevisto: '2030-03-04T08:00' })
  });
  ok('término anterior ao início é recusado', r.status === 422, r.status);

  r = await admin.pedir('atividades/criar', { metodo: 'POST', corpo: atividadeValida({ fases: [] }) });
  ok('atividade sem fases é recusada', r.status === 422, r.status);

  r = await admin.pedir('atividades/criar', {
    metodo: 'POST',
    corpo: atividadeValida({
      fases: [{ nome: 'A', peso: 1, duracaoMin: 60, inicioPrevisto: '2030-03-04T08:00', fimPrevisto: '2030-03-04T09:00', fimReal: '2030-03-04T09:00' }]
    })
  });
  ok('fase com término real sem início real é recusada', r.status === 422, r.status);

  r = await admin.pedir('atividades/criar', {
    metodo: 'POST', cabecalhos: { 'Content-Type': 'application/json' }, corpo: undefined
  });
  ok('corpo vazio é recusado', r.status === 422, r.status);

  const xss = '<img src=x onerror=alert(1)>';
  r = await admin.pedir('atividades/criar', { metodo: 'POST', corpo: atividadeValida({ titulo: xss }) });
  ok('texto com HTML é armazenado literalmente (escape é do front)',
    r.status === 201 && r.dados.atividade.titulo === xss, r.dados.atividade?.titulo);
  const idXss = r.dados.atividade?.id;

  /* ------------------------------------------------- CSRF e método -- */
  grupo('CSRF e métodos');
  r = await admin.pedir('atividades/criar', { metodo: 'POST', corpo: atividadeValida(), csrf: false });
  ok('POST sem token CSRF é recusado (419)', r.status === 419, r.status);

  r = await admin.pedir('atividades/criar', {
    metodo: 'POST', corpo: atividadeValida(), csrf: false,
    cabecalhos: { 'X-CSRF-Token': 'f'.repeat(64) }
  });
  ok('POST com token CSRF falso é recusado', r.status === 419, r.status);

  r = await admin.pedir('atividades', { metodo: 'POST', corpo: {} });
  ok('método errado na rota devolve 405', r.status === 405, r.status);

  r = await admin.pedir('rota/inexistente');
  ok('rota desconhecida devolve 404', r.status === 404, r.status);

  r = await admin.pedir('..%2Fconfig');
  ok('rota com travessia de caminho devolve 404', r.status === 404, r.status);

  /* ------------------------------------------------------ autorização -- */
  grupo('autorização por perfil');
  const senhaLeitor = 'LeitorCecape2026';
  r = await admin.pedir('usuarios/criar', {
    metodo: 'POST',
    corpo: {
      nome: 'Leitor de Teste', usuario: 'leitor.teste',
      email: 'leitor.teste@exemplo.com', perfil: 'usuario',
      senha: senhaLeitor, deveTrocarSenha: false
    }
  });
  ok('admin cria usuário de visualização', r.status === 201 || r.status === 409, r.dados);

  const leitor = criarCliente();
  r = await leitor.entrar('leitor.teste', senhaLeitor);
  ok('usuário de visualização entra', r.status === 200, r.dados);
  ok('perfil é "usuario"', r.dados.usuario?.perfil === 'usuario', r.dados.usuario);

  r = await leitor.pedir('atividades');
  ok('visualizador LÊ atividades', r.status === 200, r.status);

  r = await leitor.pedir('atividades/criar', { metodo: 'POST', corpo: atividadeValida() });
  ok('visualizador NÃO cria atividade (403)', r.status === 403, r.status);

  r = await leitor.pedir('atividades/atualizar', { metodo: 'POST', corpo: atividadeValida({ id: criada.id }) });
  ok('visualizador NÃO edita atividade (403)', r.status === 403, r.status);

  r = await leitor.pedir('atividades/excluir', { metodo: 'POST', corpo: { id: criada.id } });
  ok('visualizador NÃO exclui atividade (403)', r.status === 403, r.status);

  r = await leitor.pedir('usuarios');
  ok('visualizador NÃO lista usuários (403)', r.status === 403, r.status);

  r = await leitor.pedir('usuarios/criar', {
    metodo: 'POST',
    corpo: { nome: 'X', usuario: 'x.malicioso', email: 'x@x.com', perfil: 'admin', senha: 'Senha1234567' }
  });
  ok('visualizador NÃO cria usuário admin (403)', r.status === 403, r.status);

  r = await leitor.pedir('config/salvar', { metodo: 'POST', corpo: { organizacao: 'Invadido' } });
  ok('visualizador NÃO altera configurações (403)', r.status === 403, r.status);

  r = await leitor.pedir('auditoria');
  ok('visualizador NÃO lê a auditoria (403)', r.status === 403, r.status);

  /* --------------------------------------------------- regras de conta -- */
  grupo('regras de conta');
  r = await admin.pedir('usuarios');
  const eu = r.dados.usuarios.find(u => u.usuario === ADMIN.usuario);
  r = await admin.pedir('usuarios/atualizar', { metodo: 'POST', corpo: { id: eu.id, perfil: 'usuario' } });
  ok('admin não pode rebaixar a própria conta', r.status === 422, r.status);

  r = await admin.pedir('usuarios/excluir', { metodo: 'POST', corpo: { id: eu.id } });
  ok('admin não pode excluir a própria conta', r.status === 422, r.status);

  r = await admin.pedir('usuarios/criar', {
    metodo: 'POST',
    corpo: { nome: 'Fraco', usuario: 'senha.fraca', email: 'fraco@exemplo.com', senha: '123456' }
  });
  ok('senha curta é recusada', r.status === 422, r.dados.erro);

  r = await admin.pedir('usuarios/criar', {
    metodo: 'POST',
    corpo: { nome: 'Previsivel', usuario: 'previsivel', email: 'p@exemplo.com', senha: 'previsivel123' }
  });
  ok('senha contendo o login é recusada', r.status === 422, r.dados.erro);

  r = await admin.pedir('usuarios/criar', {
    metodo: 'POST',
    corpo: { nome: 'Dup', usuario: 'leitor.teste', email: 'outro@exemplo.com', senha: 'SenhaBoa123456' }
  });
  ok('login duplicado é recusado (409)', r.status === 409, r.status);

  r = await admin.pedir('usuarios/criar', {
    metodo: 'POST',
    corpo: { nome: 'Invalido', usuario: 'com espaço!', email: 'i@exemplo.com', senha: 'SenhaBoa123456' }
  });
  ok('login com caracteres inválidos é recusado', r.status === 422, r.status);

  /* ------------------------------------------------------ força bruta -- */
  grupo('proteção contra força bruta');
  const atacante = criarCliente();
  let bloqueou = false, respostas = [];
  for (let i = 0; i < 8; i++) {
    const res = await atacante.entrar('leitor.teste', 'senhaIncorreta' + i);
    respostas.push(res.status);
    if (res.status === 429) { bloqueou = true; break; }
  }
  ok('bloqueia após tentativas repetidas (429)', bloqueou, respostas);

  r = await atacante.entrar('leitor.teste', senhaLeitor);
  ok('bloqueio vale mesmo com a senha correta', r.status === 429, r.status);

  /* ------------------------------------------------- sessão e logout -- */
  grupo('ciclo de vida da sessão');
  const paraSair = criarCliente();
  await paraSair.entrar(ADMIN.usuario, ADMIN.senha);
  const idSessaoLogado = paraSair.cookies.get('DIARIOSESSID');
  ok('id de sessão muda após o login (anti-fixation)',
    idSessaoLogado !== sessaoAnterior, { antes: sessaoAnterior?.slice(0, 8), depois: idSessaoLogado?.slice(0, 8) });

  r = await paraSair.pedir('auth/logout', { metodo: 'POST' });
  ok('logout responde 200', r.status === 200, r.status);
  r = await paraSair.pedir('atividades');
  ok('após logout o acesso é negado', r.status === 401, r.status);

  /* -------------------------------------------------------- limpeza -- */
  grupo('limpeza');
  for (const id of [criada.id, idXss].filter(Boolean)) {
    r = await admin.pedir('atividades/excluir', { metodo: 'POST', corpo: { id } });
    ok('atividade ' + id + ' excluída', r.status === 200, r.status);
  }
  const usuarios = (await admin.pedir('usuarios')).dados.usuarios;
  const alvo = usuarios.find(u => u.usuario === 'leitor.teste');
  if (alvo) {
    r = await admin.pedir('usuarios/excluir', { metodo: 'POST', corpo: { id: alvo.id } });
    ok('usuário de teste excluído', r.status === 200, r.status);
  }

  /* -------------------------------------------------------- auditoria -- */
  grupo('auditoria');
  r = await admin.pedir('auditoria?limite=50');
  const acoes = (r.dados.registros || []).map(x => x.acao);
  ok('auditoria registrou os logins', acoes.includes('login'), acoes.slice(0, 8));
  ok('auditoria registrou as falhas de login', acoes.includes('login_negado'));
  ok('auditoria registrou as exclusões', acoes.includes('atividade_excluida'));
  ok('auditoria não guarda senhas',
    !JSON.stringify(r.dados.registros).match(/\$argon2|\$2y\$|DiarioCecape2026/));

  console.log('\n' + (falhas ? `✗ ${falhas} de ${total} verificações falharam`
    : `✓ todas as ${total} verificações da API passaram`) + '\n');
  process.exit(falhas ? 1 : 0);
})().catch(e => { console.error('\nErro fatal na suíte:', e); process.exit(1); });
