/* =========================================================================
 * schedule.js — motor de previsão de fases
 *
 * Dada a data/hora de início de uma atividade, a duração total e a lista de
 * fases (com peso relativo ou duração fixa), calcula início e término
 * previstos de cada fase respeitando a jornada de trabalho configurada.
 * ========================================================================= */
(function (global) {
  'use strict';

  var U = global.Util;
  var Schedule = {};

  /** Jornada padrão: seg–sex, 08:00–18:00, com pausa 12:00–13:00. */
  Schedule.JORNADA_PADRAO = {
    modo: 'comercial',            // 'comercial' | 'continuo'
    dias: [1, 2, 3, 4, 5],        // 0 = domingo … 6 = sábado
    inicio: '08:00',
    fim: '18:00',
    pausaAtiva: true,
    pausaInicio: '12:00',
    pausaFim: '13:00'
  };

  /** Modelos de fases prontos para uso. */
  Schedule.MODELOS = [
    {
      id: 'padrao', nome: 'Padrão (4 fases)', fases: [
        { nome: 'Planejamento', peso: 20 },
        { nome: 'Preparação', peso: 15 },
        { nome: 'Execução', peso: 45 },
        { nome: 'Encerramento e registro', peso: 20 }
      ]
    },
    {
      id: 'aula', nome: 'Aula / Formação', fases: [
        { nome: 'Acolhida e abertura', peso: 10 },
        { nome: 'Exposição do conteúdo', peso: 40 },
        { nome: 'Atividade prática', peso: 35 },
        { nome: 'Avaliação e fechamento', peso: 15 }
      ]
    },
    {
      id: 'projeto', nome: 'Projeto (5 fases)', fases: [
        { nome: 'Levantamento de requisitos', peso: 15 },
        { nome: 'Planejamento', peso: 20 },
        { nome: 'Execução', peso: 40 },
        { nome: 'Validação', peso: 15 },
        { nome: 'Entrega', peso: 10 }
      ]
    },
    {
      id: 'reuniao', nome: 'Reunião', fases: [
        { nome: 'Abertura e pauta', peso: 15 },
        { nome: 'Discussão dos temas', peso: 55 },
        { nome: 'Deliberações', peso: 20 },
        { nome: 'Encaminhamentos', peso: 10 }
      ]
    },
    {
      id: 'visita', nome: 'Visita técnica', fases: [
        { nome: 'Deslocamento', peso: 20 },
        { nome: 'Recepção e briefing', peso: 10 },
        { nome: 'Vistoria / atividade em campo', peso: 45 },
        { nome: 'Relatório de campo', peso: 25 }
      ]
    },
    { id: 'livre', nome: 'Personalizado', fases: [{ nome: 'Fase 1', peso: 100 }] }
  ];

  Schedule.modelo = function (id) {
    for (var i = 0; i < Schedule.MODELOS.length; i++) {
      if (Schedule.MODELOS[i].id === id) return Schedule.MODELOS[i];
    }
    return Schedule.MODELOS[0];
  };

  /* ------------------------------------------------------------- interno */

  function normalizarJornada(j) {
    j = j || {};
    var base = Schedule.JORNADA_PADRAO;
    return {
      modo: j.modo === 'continuo' ? 'continuo' : 'comercial',
      dias: (j.dias && j.dias.length) ? j.dias.slice() : base.dias.slice(),
      inicio: j.inicio || base.inicio,
      fim: j.fim || base.fim,
      pausaAtiva: j.pausaAtiva !== false,
      pausaInicio: j.pausaInicio || base.pausaInicio,
      pausaFim: j.pausaFim || base.pausaFim
    };
  }
  Schedule.normalizarJornada = normalizarJornada;

  /**
   * Janelas de trabalho de um dia, em minutos desde a meia-noite.
   * Retorna [] quando o dia não é útil.
   */
  function janelasDoDia(data, j) {
    if (j.modo === 'continuo') return [[0, 1440]];
    if (j.dias.indexOf(data.getDay()) === -1) return [];

    var ini = U.hhmmParaMin(j.inicio);
    var fim = U.hhmmParaMin(j.fim);
    if (fim <= ini) return [];

    if (!j.pausaAtiva) return [[ini, fim]];

    var pIni = U.hhmmParaMin(j.pausaInicio);
    var pFim = U.hhmmParaMin(j.pausaFim);
    if (pFim <= pIni || pFim <= ini || pIni >= fim) return [[ini, fim]];

    var janelas = [];
    if (pIni > ini) janelas.push([ini, Math.min(pIni, fim)]);
    if (pFim < fim) janelas.push([Math.max(pFim, ini), fim]);
    return janelas.filter(function (w) { return w[1] > w[0]; });
  }
  Schedule.janelasDoDia = janelasDoDia;

  function minutosNoDia(d) { return d.getHours() * 60 + d.getMinutes(); }

  function noDia(data, minutos) {
    var d = new Date(data.getFullYear(), data.getMonth(), data.getDate(), 0, 0, 0, 0);
    return new Date(d.getTime() + minutos * 60000);
  }

  var LIMITE_DIAS = 800; // trava de segurança contra laços infinitos

  /**
   * Avança o instante para o próximo momento válido da jornada.
   * Se já estiver dentro de uma janela de trabalho, devolve o próprio instante.
   */
  Schedule.proximoInstanteUtil = function (data, jornada) {
    var j = normalizarJornada(jornada);
    var cursor = U.parseLocal(data);
    if (!cursor) return null;

    for (var dia = 0; dia < LIMITE_DIAS; dia++) {
      var janelas = janelasDoDia(cursor, j);
      var m = minutosNoDia(cursor);
      for (var i = 0; i < janelas.length; i++) {
        if (m < janelas[i][0]) return noDia(cursor, janelas[i][0]);
        if (m < janelas[i][1]) return cursor;
      }
      // nada mais neste dia: vai para 00:00 do dia seguinte
      cursor = new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate() + 1, 0, 0, 0, 0);
    }
    return cursor;
  };

  /**
   * Soma `minutos` de trabalho efetivo a partir de `data`, pulando
   * intervalos fora da jornada. Retorna o instante final.
   */
  Schedule.somarMinutosUteis = function (data, minutos, jornada) {
    var j = normalizarJornada(jornada);
    var restante = Math.max(0, Math.round(minutos));
    var cursor = Schedule.proximoInstanteUtil(data, j);
    if (!cursor) return null;
    if (restante === 0) return cursor;

    for (var dia = 0; dia < LIMITE_DIAS; dia++) {
      var janelas = janelasDoDia(cursor, j);
      var m = minutosNoDia(cursor);
      for (var i = 0; i < janelas.length; i++) {
        var ini = Math.max(m, janelas[i][0]);
        var fim = janelas[i][1];
        if (fim <= ini) continue;
        var disponivel = fim - ini;
        if (restante <= disponivel) return noDia(cursor, ini + restante);
        restante -= disponivel;
        m = fim;
      }
      cursor = new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate() + 1, 0, 0, 0, 0);
    }
    return cursor;
  };

  /** Minutos úteis entre dois instantes (0 se b <= a). */
  Schedule.minutosUteisEntre = function (a, b, jornada) {
    var j = normalizarJornada(jornada);
    var ini = U.parseLocal(a), fim = U.parseLocal(b);
    if (!ini || !fim || fim <= ini) return 0;
    if (j.modo === 'continuo') return U.diffMin(ini, fim);

    var total = 0;
    var cursor = new Date(ini.getFullYear(), ini.getMonth(), ini.getDate(), 0, 0, 0, 0);
    for (var dia = 0; dia < LIMITE_DIAS; dia++) {
      if (cursor > fim) break;
      var janelas = janelasDoDia(cursor, j);
      for (var i = 0; i < janelas.length; i++) {
        var wIni = noDia(cursor, janelas[i][0]);
        var wFim = noDia(cursor, janelas[i][1]);
        var s = wIni < ini ? ini : wIni;
        var e = wFim > fim ? fim : wFim;
        if (e > s) total += Math.round((e - s) / 60000);
      }
      cursor = new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate() + 1, 0, 0, 0, 0);
    }
    return total;
  };

  /**
   * Distribui a duração total entre as fases e calcula início/término
   * previstos de cada uma, em sequência.
   *
   * @param {Object} opts
   *   inicio      {Date|string}  início previsto da atividade
   *   duracaoMin  {number}       duração total em minutos (modo 'peso')
   *   fases       {Array}        [{ nome, peso, duracaoMin }]
   *   distribuir  {string}       'peso' (rateio) | 'fixa' (usa duracaoMin de cada fase)
   *   jornada     {Object}
   * @returns {Object} { fases: [...], inicio: Date, fim: Date, duracaoMin: number }
   */
  Schedule.planejar = function (opts) {
    var j = normalizarJornada(opts.jornada);
    var fases = (opts.fases || []).filter(function (f) { return f && f.nome; });
    var distribuir = opts.distribuir === 'fixa' ? 'fixa' : 'peso';

    var duracoes = [];
    if (distribuir === 'fixa') {
      duracoes = fases.map(function (f) { return Math.max(1, Math.round(+f.duracaoMin || 0)); });
    } else {
      var total = Math.max(1, Math.round(+opts.duracaoMin || 0));
      var somaPesos = fases.reduce(function (s, f) { return s + (Math.max(0, +f.peso) || 0); }, 0);
      if (somaPesos <= 0) {
        // sem pesos válidos: divide igualmente
        fases.forEach(function () { duracoes.push(Math.floor(total / (fases.length || 1))); });
      } else {
        fases.forEach(function (f) {
          duracoes.push(Math.floor(total * (Math.max(0, +f.peso) || 0) / somaPesos));
        });
      }
      // devolve o resto do arredondamento à última fase
      var soma = duracoes.reduce(function (s, n) { return s + n; }, 0);
      if (duracoes.length) duracoes[duracoes.length - 1] += (total - soma);
      duracoes = duracoes.map(function (n) { return Math.max(1, n); });
    }

    var cursor = Schedule.proximoInstanteUtil(opts.inicio, j);
    var inicioGeral = cursor;
    var resultado = [];

    for (var i = 0; i < fases.length; i++) {
      var ini = Schedule.proximoInstanteUtil(cursor, j);
      var fim = Schedule.somarMinutosUteis(ini, duracoes[i], j);
      resultado.push(Object.assign({}, fases[i], {
        id: fases[i].id || U.uid('fase'),
        nome: fases[i].nome,
        peso: +fases[i].peso || 0,
        duracaoMin: duracoes[i],
        inicioPrevisto: U.toInput(ini),
        fimPrevisto: U.toInput(fim),
        inicioReal: fases[i].inicioReal || '',
        fimReal: fases[i].fimReal || '',
        observacao: fases[i].observacao || ''
      }));
      cursor = fim;
    }

    return {
      fases: resultado,
      inicio: inicioGeral,
      fim: cursor,
      duracaoMin: duracoes.reduce(function (s, n) { return s + n; }, 0)
    };
  };

  /** Descrição curta da jornada, para exibir na interface. */
  Schedule.descreverJornada = function (jornada) {
    var j = normalizarJornada(jornada);
    if (j.modo === 'continuo') return 'Contínuo (24h, todos os dias)';
    var dias = j.dias.slice().sort().map(function (d) { return U.DIAS_SEMANA[d]; }).join(', ');
    var txt = dias + ' · ' + j.inicio + '–' + j.fim;
    if (j.pausaAtiva) txt += ' (pausa ' + j.pausaInicio + '–' + j.pausaFim + ')';
    return txt;
  };

  global.Schedule = Schedule;
})(window);
