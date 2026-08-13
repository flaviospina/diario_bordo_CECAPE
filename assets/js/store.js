/* =========================================================================
 * store.js — estado da aplicação sobre a API (MySQL)
 *
 * As atividades ficam em um cache em memória, carregado no início da sessão.
 * As leituras (filtros, status, progresso) são síncronas sobre esse cache; as
 * escritas vão ao servidor e só então atualizam o cache. Assim a interface
 * continua fluida e o banco permanece a única fonte da verdade.
 * ========================================================================= */
(function (global) {
  'use strict';

  var U = global.Util;
  var Api = global.Api;

  var Store = {};

  var STATUS = {
    planejada: { rotulo: 'Planejada', cor: 'neutro' },
    em_andamento: { rotulo: 'Em andamento', cor: 'info' },
    concluida: { rotulo: 'Concluída', cor: 'ok' },
    atrasada: { rotulo: 'Atrasada', cor: 'alerta' },
    cancelada: { rotulo: 'Cancelada', cor: 'off' }
  };
  Store.STATUS = STATUS;

  var estado = {
    atividades: [],
    usuario: null,
    config: {
      organizacao: 'CECAPE',
      subtitulo: 'Diário de Bordo — Registro de Atividades',
      jornada: null
    },
    tema: 'claro'
  };

  /* ------------------------------------------------------------- sessão -- */

  Store.usuario = function () { return estado.usuario; };

  Store.ehAdmin = function () {
    return !!estado.usuario && estado.usuario.perfil === 'admin';
  };

  /** O tema é preferência visual: fica no navegador mesmo. */
  Store.tema = function () {
    try { return localStorage.getItem('diario_tema') || 'claro'; }
    catch (e) { return 'claro'; }
  };

  Store.salvarTema = function (tema) {
    estado.tema = tema;
    try { localStorage.setItem('diario_tema', tema); } catch (e) { /* modo privado */ }
  };

  /* ------------------------------------------------------------ carga -- */

  /**
   * Carrega sessão, configurações e atividades.
   * @returns {Promise<boolean>} true se há usuário autenticado.
   */
  Store.carregar = function () {
    return Api.sessao().then(function (s) {
      estado.usuario = s.usuario || null;
      if (!estado.usuario) return false;
      // Com troca de senha pendente, o servidor bloqueia as demais rotas.
      if (estado.usuario.deveTrocarSenha) return true;
      return Store.recarregar().then(function () { return true; });
    });
  };

  Store.recarregar = function () {
    return Promise.all([Api.obterConfig(), Api.listarAtividades()])
      .then(function (r) {
        estado.config = Object.assign({}, estado.config, r[0]);
        estado.atividades = r[1];
        return estado;
      });
  };

  Store.estado = function () { return estado; };
  Store.config = function () { return estado.config; };

  Store.salvarConfig = function (parcial) {
    var novo = Object.assign({}, estado.config, parcial || {});
    return Api.salvarConfig({
      organizacao: novo.organizacao,
      subtitulo: novo.subtitulo,
      jornada: novo.jornada
    }).then(function (config) {
      estado.config = Object.assign({}, estado.config, config);
      return estado.config;
    });
  };

  /* -------------------------------------------------------------- CRUD -- */

  Store.listar = function () { return estado.atividades.slice(); };

  Store.obter = function (id) {
    for (var i = 0; i < estado.atividades.length; i++) {
      if (estado.atividades[i].id === id) return estado.atividades[i];
    }
    return null;
  };

  function ordenar() {
    estado.atividades.sort(function (a, b) {
      return (U.parseLocal(b.inicioPrevisto) || 0) - (U.parseLocal(a.inicioPrevisto) || 0);
    });
  }

  function substituir(atividade) {
    var i = estado.atividades.findIndex(function (a) { return a.id === atividade.id; });
    if (i >= 0) estado.atividades[i] = atividade;
    else estado.atividades.push(atividade);
    ordenar();
    return atividade;
  }

  Store.criar = function (dados) {
    return Api.criarAtividade(paraApi(dados)).then(substituir);
  };

  Store.atualizar = function (id, dados) {
    var base = Object.assign({}, Store.obter(id) || {}, dados, { id: id });
    return Api.atualizarAtividade(paraApi(base)).then(substituir);
  };

  Store.remover = function (id) {
    return Api.excluirAtividade(id).then(function () {
      estado.atividades = estado.atividades.filter(function (a) { return a.id !== id; });
      return true;
    });
  };

  /** Mantém no corpo da requisição apenas os campos que o servidor aceita. */
  function paraApi(a) {
    return {
      id: a.id,
      titulo: a.titulo || '',
      descricao: a.descricao || '',
      responsavel: a.responsavel || '',
      categoria: a.categoria || '',
      local: a.local || '',
      modelo: a.modelo || '',
      inicioPrevisto: a.inicioPrevisto || '',
      fimPrevisto: a.fimPrevisto || '',
      inicioReal: a.inicioReal || '',
      fimReal: a.fimReal || '',
      cancelada: !!a.cancelada,
      fases: (a.fases || []).map(function (f) {
        return {
          nome: f.nome || '',
          peso: +f.peso || 0,
          duracaoMin: Math.max(1, +f.duracaoMin || 1),
          inicioPrevisto: f.inicioPrevisto || '',
          fimPrevisto: f.fimPrevisto || '',
          inicioReal: f.inicioReal || '',
          fimReal: f.fimReal || '',
          observacao: f.observacao || ''
        };
      })
    };
  }
  Store.paraApi = paraApi;

  /* ---------------------------------------------------------------- status */

  Store.statusFase = function (fase) {
    if (fase.fimReal) return 'concluida';
    if (fase.inicioReal) return 'em_andamento';
    if (fase.fimPrevisto && U.parseLocal(fase.fimPrevisto) < new Date()) return 'atrasada';
    return 'planejada';
  };

  Store.statusAtividade = function (atv) {
    if (atv.cancelada) return 'cancelada';
    var fases = atv.fases || [];
    var concluidas = 0, iniciadas = 0;
    fases.forEach(function (f) {
      if (f.fimReal) concluidas++;
      if (f.inicioReal) iniciadas++;
    });
    if (atv.fimReal || (fases.length && concluidas === fases.length)) return 'concluida';
    var fim = U.parseLocal(atv.fimPrevisto);
    if (atv.inicioReal || iniciadas > 0) {
      return (fim && fim < new Date()) ? 'atrasada' : 'em_andamento';
    }
    return (fim && fim < new Date()) ? 'atrasada' : 'planejada';
  };

  Store.progresso = function (atv) {
    var fases = atv.fases || [];
    if (!fases.length) return atv.fimReal ? 100 : 0;
    var total = 0, feito = 0;
    fases.forEach(function (f) {
      var d = Math.max(1, +f.duracaoMin || 1);
      total += d;
      if (f.fimReal) feito += d;
      else if (f.inicioReal) feito += d * 0.5;
    });
    return total ? Math.round(feito * 100 / total) : 0;
  };

  Store.duracaoPrevista = function (atv) {
    return (atv.fases || []).reduce(function (s, f) {
      return s + (Math.max(0, +f.duracaoMin) || 0);
    }, 0) || (U.diffMin(atv.inicioPrevisto, atv.fimPrevisto) || 0);
  };

  Store.duracaoRealizada = function (atv) {
    var total = 0, temAlgum = false;
    (atv.fases || []).forEach(function (f) {
      if (f.inicioReal && f.fimReal) {
        var d = U.diffMin(f.inicioReal, f.fimReal);
        if (d != null && d > 0) { total += d; temAlgum = true; }
      }
    });
    if (temAlgum) return total;
    if (atv.inicioReal && atv.fimReal) return U.diffMin(atv.inicioReal, atv.fimReal) || 0;
    return 0;
  };

  Store.desvio = function (atv) {
    var real = Store.duracaoRealizada(atv);
    if (!real) return null;
    var prev = Store.duracaoPrevista(atv);
    return prev ? real - prev : null;
  };

  /** Sincroniza início/fim da atividade a partir das fases. */
  Store.sincronizarTotais = function (atv) {
    var fases = atv.fases || [];
    if (!fases.length) return atv;

    atv.inicioPrevisto = fases[0].inicioPrevisto || atv.inicioPrevisto;
    atv.fimPrevisto = fases[fases.length - 1].fimPrevisto || atv.fimPrevisto;

    var inicios = fases.filter(function (f) { return f.inicioReal; })
      .map(function (f) { return U.parseLocal(f.inicioReal); })
      .filter(Boolean).sort(function (a, b) { return a - b; });
    atv.inicioReal = inicios.length ? U.toInput(inicios[0]) : '';

    var todas = fases.every(function (f) { return f.fimReal; });
    if (todas) {
      var fins = fases.map(function (f) { return U.parseLocal(f.fimReal); })
        .filter(Boolean).sort(function (a, b) { return b - a; });
      atv.fimReal = fins.length ? U.toInput(fins[0]) : '';
    } else {
      atv.fimReal = '';
    }
    return atv;
  };

  /* --------------------------------------------------------------- filtros */

  Store.filtrar = function (f) {
    f = f || {};
    var busca = U.normalizar(f.busca || '');
    var de = f.de ? U.inicioDoDia(U.parseLocal(f.de)) : null;
    var ate = f.ate ? U.fimDoDia(U.parseLocal(f.ate)) : null;

    return estado.atividades.filter(function (atv) {
      if (f.responsavel && atv.responsavel !== f.responsavel) return false;
      if (f.categoria && atv.categoria !== f.categoria) return false;
      if (f.status && Store.statusAtividade(atv) !== f.status) return false;

      if (de || ate) {
        var ini = U.parseLocal(atv.inicioReal || atv.inicioPrevisto);
        if (!ini) return false;
        if (de && ini < de) return false;
        if (ate && ini > ate) return false;
      }

      if (busca) {
        var alvo = U.normalizar([
          atv.titulo, atv.descricao, atv.responsavel, atv.local, atv.categoria,
          (atv.fases || []).map(function (x) { return x.nome; }).join(' ')
        ].join(' '));
        if (alvo.indexOf(busca) === -1) return false;
      }
      return true;
    }).sort(function (a, b) {
      return (U.parseLocal(b.inicioPrevisto) || 0) - (U.parseLocal(a.inicioPrevisto) || 0);
    });
  };

  Store.responsaveis = function () {
    var set = {};
    estado.atividades.forEach(function (a) { if (a.responsavel) set[a.responsavel] = 1; });
    return Object.keys(set).sort();
  };

  Store.categorias = function () {
    var set = {};
    estado.atividades.forEach(function (a) { if (a.categoria) set[a.categoria] = 1; });
    return Object.keys(set).sort();
  };

  /* ---------------------------------------------------- backup / importação */

  Store.exportarJSON = function () {
    return JSON.stringify({
      versao: 2,
      exportadoEm: new Date().toISOString(),
      config: estado.config,
      atividades: estado.atividades
    }, null, 2);
  };

  /** Envia o backup ao servidor; a validação real acontece lá. */
  Store.importarJSON = function (texto, substituir) {
    var dados = JSON.parse(texto);
    if (!dados || !Array.isArray(dados.atividades)) {
      throw new Error('Arquivo inválido: não contém a lista de atividades.');
    }
    return Api.importar(dados.atividades, substituir).then(function (n) {
      return Store.recarregar().then(function () { return n; });
    });
  };

  global.Store = Store;
})(window);
