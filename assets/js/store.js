/* =========================================================================
 * store.js — persistência (localStorage), CRUD e regras de status
 * ========================================================================= */
(function (global) {
  'use strict';

  var U = global.Util;
  var CHAVE = 'diario_bordo_cecape_v1';

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
    config: {
      organizacao: 'CECAPE',
      subtitulo: 'Diário de Bordo — Registro de Atividades',
      jornada: null,             // preenchida com Schedule.JORNADA_PADRAO
      pinHash: U.hash('1234'),
      tema: 'claro',
      exemplosCarregados: false
    }
  };

  /* ----------------------------------------------------------- persistência */

  function salvar() {
    try {
      localStorage.setItem(CHAVE, JSON.stringify(estado));
      return true;
    } catch (e) {
      console.error('Falha ao salvar no armazenamento local:', e);
      return false;
    }
  }
  Store.salvar = salvar;

  Store.carregar = function () {
    var bruto = null;
    try { bruto = localStorage.getItem(CHAVE); } catch (e) { /* modo privado */ }
    if (bruto) {
      try {
        var dados = JSON.parse(bruto);
        if (dados && typeof dados === 'object') {
          estado.atividades = Array.isArray(dados.atividades) ? dados.atividades : [];
          estado.config = Object.assign({}, estado.config, dados.config || {});
        }
      } catch (e) {
        console.error('Dados corrompidos no armazenamento local:', e);
      }
    }
    if (!estado.config.jornada) {
      estado.config.jornada = Object.assign({}, global.Schedule.JORNADA_PADRAO);
    }
    if (!estado.config.exemplosCarregados && estado.atividades.length === 0) {
      semear();
      estado.config.exemplosCarregados = true;
      salvar();
    }
    return estado;
  };

  Store.estado = function () { return estado; };
  Store.config = function () { return estado.config; };

  Store.salvarConfig = function (parcial) {
    estado.config = Object.assign({}, estado.config, parcial || {});
    salvar();
    return estado.config;
  };

  /* ------------------------------------------------------------------ CRUD */

  Store.listar = function () { return estado.atividades.slice(); };

  Store.obter = function (id) {
    for (var i = 0; i < estado.atividades.length; i++) {
      if (estado.atividades[i].id === id) return estado.atividades[i];
    }
    return null;
  };

  Store.criar = function (dados) {
    var agora = new Date().toISOString();
    var atividade = Object.assign({
      id: U.uid('atv'),
      titulo: '',
      descricao: '',
      responsavel: '',
      local: '',
      categoria: '',
      inicioPrevisto: '',
      fimPrevisto: '',
      inicioReal: '',
      fimReal: '',
      cancelada: false,
      fases: []
    }, dados, { criadoEm: agora, atualizadoEm: agora });
    estado.atividades.push(atividade);
    salvar();
    return atividade;
  };

  Store.atualizar = function (id, dados) {
    var atv = Store.obter(id);
    if (!atv) return null;
    Object.assign(atv, dados, { atualizadoEm: new Date().toISOString() });
    salvar();
    return atv;
  };

  Store.remover = function (id) {
    var antes = estado.atividades.length;
    estado.atividades = estado.atividades.filter(function (a) { return a.id !== id; });
    salvar();
    return estado.atividades.length < antes;
  };

  Store.limparTudo = function () {
    estado.atividades = [];
    estado.config.exemplosCarregados = true;
    salvar();
  };

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
    if (atv.inicioReal || iniciadas > 0) {
      var fim = U.parseLocal(atv.fimPrevisto);
      if (fim && fim < new Date()) return 'atrasada';
      return 'em_andamento';
    }
    var ini = U.parseLocal(atv.fimPrevisto);
    if (ini && ini < new Date()) return 'atrasada';
    return 'planejada';
  };

  /** Percentual concluído com base nas fases finalizadas (ponderado por duração). */
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
    var fases = atv.fases || [];
    var total = 0, temAlgum = false;
    fases.forEach(function (f) {
      if (f.inicioReal && f.fimReal) {
        var d = U.diffMin(f.inicioReal, f.fimReal);
        if (d != null && d > 0) { total += d; temAlgum = true; }
      }
    });
    if (temAlgum) return total;
    if (atv.inicioReal && atv.fimReal) return U.diffMin(atv.inicioReal, atv.fimReal) || 0;
    return 0;
  };

  /** Desvio (realizado - previsto) em minutos; null quando ainda não concluída. */
  Store.desvio = function (atv) {
    var real = Store.duracaoRealizada(atv);
    if (!real) return null;
    var prev = Store.duracaoPrevista(atv);
    if (!prev) return null;
    return real - prev;
  };

  /** Sincroniza início/fim da atividade a partir das fases. */
  Store.sincronizarTotais = function (atv) {
    var fases = atv.fases || [];
    if (fases.length) {
      atv.inicioPrevisto = fases[0].inicioPrevisto || atv.inicioPrevisto;
      atv.fimPrevisto = fases[fases.length - 1].fimPrevisto || atv.fimPrevisto;

      var reais = fases.filter(function (f) { return f.inicioReal; })
        .map(function (f) { return U.parseLocal(f.inicioReal); })
        .filter(Boolean).sort(function (a, b) { return a - b; });
      atv.inicioReal = reais.length ? U.toInput(reais[0]) : '';

      var todasConcluidas = fases.length > 0 && fases.every(function (f) { return f.fimReal; });
      if (todasConcluidas) {
        var fins = fases.map(function (f) { return U.parseLocal(f.fimReal); })
          .filter(Boolean).sort(function (a, b) { return b - a; });
        atv.fimReal = fins.length ? U.toInput(fins[0]) : '';
      } else {
        atv.fimReal = '';
      }
    }
    return atv;
  };

  /* --------------------------------------------------------------- filtros */

  /**
   * @param {Object} f  { busca, responsavel, categoria, status, de, ate }
   */
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
      var da = U.parseLocal(a.inicioPrevisto) || 0;
      var db = U.parseLocal(b.inicioPrevisto) || 0;
      return db - da;
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

  /* --------------------------------------------------- backup / restauração */

  Store.exportarJSON = function () {
    return JSON.stringify({
      versao: 1,
      exportadoEm: new Date().toISOString(),
      config: estado.config,
      atividades: estado.atividades
    }, null, 2);
  };

  Store.importarJSON = function (texto, substituir) {
    var dados = JSON.parse(texto);
    if (!dados || !Array.isArray(dados.atividades)) {
      throw new Error('Arquivo inválido: não contém a lista de atividades.');
    }
    var recebidas = dados.atividades.map(function (a) {
      if (!a.id) a.id = U.uid('atv');
      a.fases = Array.isArray(a.fases) ? a.fases : [];
      return a;
    });
    if (substituir) {
      estado.atividades = recebidas;
    } else {
      var existentes = {};
      estado.atividades.forEach(function (a) { existentes[a.id] = true; });
      recebidas.forEach(function (a) {
        if (existentes[a.id]) a.id = U.uid('atv');
        estado.atividades.push(a);
      });
    }
    if (dados.config) {
      // preserva o PIN atual: o backup não deve alterar o acesso administrativo
      var pin = estado.config.pinHash;
      estado.config = Object.assign({}, estado.config, dados.config, { pinHash: pin });
    }
    salvar();
    return recebidas.length;
  };

  /* ----------------------------------------------------- dados de exemplo */

  function semear() {
    var S = global.Schedule;
    var agora = new Date();

    /** Data/hora relativa a hoje, com hora cheia. */
    function em(dias, hora) {
      return new Date(agora.getFullYear(), agora.getMonth(), agora.getDate() + dias, hora, 0, 0, 0);
    }

    function criarExemplo(titulo, inicio, duracao, modeloId, extras, jornada) {
      var modelo = S.modelo(modeloId);
      var plano = S.planejar({
        inicio: inicio,
        duracaoMin: duracao,
        fases: modelo.fases.map(function (f) { return { nome: f.nome, peso: f.peso }; }),
        distribuir: 'peso',
        jornada: jornada || estado.config.jornada
      });
      var atv = Object.assign({
        id: U.uid('atv'),
        titulo: titulo,
        descricao: '',
        responsavel: '',
        local: '',
        categoria: '',
        cancelada: false,
        modelo: modeloId,
        fases: plano.fases,
        inicioPrevisto: U.toInput(plano.inicio),
        fimPrevisto: U.toInput(plano.fim),
        inicioReal: '',
        fimReal: '',
        criadoEm: new Date().toISOString(),
        atualizadoEm: new Date().toISOString()
      }, extras || {});
      estado.atividades.push(atv);
      return atv;
    }

    // 1) Concluída: tudo executado conforme o previsto
    var a1 = criarExemplo('Aula inaugural — Informática Básica', em(-7, 8), 240, 'aula', {
      responsavel: 'Prof. Flávio Spina',
      local: 'Laboratório 2',
      categoria: 'Ensino',
      descricao: 'Apresentação do plano de curso, diagnóstico da turma e primeiros exercícios.'
    });
    a1.fases.forEach(function (f) {
      f.inicioReal = f.inicioPrevisto;
      f.fimReal = f.fimPrevisto;
    });
    Store.sincronizarTotais(a1);

    // 2) Em andamento: começou há uma hora e termina daqui a duas.
    //    Usa jornada contínua para o exemplo fazer sentido a qualquer hora do dia.
    var a2 = criarExemplo('Manutenção preventiva do laboratório',
      new Date(agora.getTime() - 60 * 60000), 180, 'padrao', {
        responsavel: 'Equipe de TI',
        local: 'Laboratório 1',
        categoria: 'Infraestrutura',
        descricao: 'Limpeza, atualização das imagens e teste de rede das 20 estações.'
      }, { modo: 'continuo' });
    a2.fases.forEach(function (f) {
      if (U.parseLocal(f.inicioPrevisto) <= agora) f.inicioReal = f.inicioPrevisto;
      if (U.parseLocal(f.fimPrevisto) <= agora) f.fimReal = f.fimPrevisto;
    });
    Store.sincronizarTotais(a2);

    // 3) Atrasada: prazo vencido sem nenhum registro de execução
    criarExemplo('Inventário dos equipamentos da unidade', em(-3, 14), 300, 'projeto', {
      responsavel: 'Equipe de TI',
      local: 'Almoxarifado',
      categoria: 'Infraestrutura',
      descricao: 'Conferência física e atualização da planilha patrimonial.'
    });

    // 4) Planejada: ainda no futuro
    criarExemplo('Reunião pedagógica mensal', em(4, 14), 120, 'reuniao', {
      responsavel: 'Coordenação',
      local: 'Sala de reuniões',
      categoria: 'Gestão',
      descricao: 'Avaliação dos indicadores do mês e planejamento do próximo ciclo.'
    });
  }

  global.Store = Store;
})(window);
