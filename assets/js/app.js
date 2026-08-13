/* =========================================================================
 * app.js — interface, navegação e regras de tela
 * ========================================================================= */
(function (global) {
  'use strict';

  var U = global.Util;
  var S = global.Schedule;
  var St = global.Store;
  var Ex = global.Export;
  var Api = global.Api;
  var $ = U.$, $$ = U.$$;
  var e = U.escapeHtml;

  var app = {
    view: 'painel',
    editandoId: null,       // atividade em edição no formulário
    fases: [],              // fases em edição no formulário
    modalId: null,          // atividade aberta no modal
    modoModal: null,        // 'detalhe' | 'senha' | 'usuario'
    usuarios: []            // cache da tela de usuários
  };

  /** O perfil vem sempre do servidor — a interface só reflete o que ele diz. */
  function ehAdmin() { return St.ehAdmin(); }

  /* ====================================================== notificações == */

  function avisar(msg, tipo) {
    var cx = $('#notificacoes');
    var t = document.createElement('div');
    t.className = 'toast' + (tipo ? ' ' + tipo : '');
    t.textContent = msg;
    cx.appendChild(t);
    setTimeout(function () {
      t.classList.add('saindo');
      setTimeout(function () { t.remove(); }, 260);
    }, 3200);
  }

  /* ============================================================ auxiliares */

  function selo(status) {
    var s = St.STATUS[status] || { rotulo: status, cor: 'neutro' };
    return '<span class="selo selo-' + s.cor + '">' + e(s.rotulo) + '</span>';
  }

  function barraProgresso(pct) {
    return '<div class="progresso"><div class="progresso-trilho">' +
      '<div class="progresso-barra' + (pct >= 100 ? ' completo' : '') +
      '" style="width:' + pct + '%"></div></div>' +
      '<span class="progresso-num">' + pct + '%</span></div>';
  }

  /** Segmentos internos da linha do tempo (sem o contêiner). */
  function segmentosFases(fases) {
    fases = fases || [];
    if (!fases.length) return '';
    var total = fases.reduce(function (s, f) { return s + Math.max(1, +f.duracaoMin || 1); }, 0);
    return fases.map(function (f) {
      var pct = Math.max(4, Math.round(Math.max(1, +f.duracaoMin || 1) * 100 / total));
      var st = St.statusFase(f);
      return '<div class="barra-fase f-' + st + '" style="flex:' + pct + ' 1 0" title="' +
        e(f.nome + ' — ' + U.fmtDataHora(f.inicioPrevisto) + ' até ' +
          U.fmtDataHora(f.fimPrevisto) + ' (' + U.fmtDuracao(f.duracaoMin) + ')') + '">' +
        e(f.nome) + '</div>';
    }).join('');
  }

  function barraFases(atv) {
    var inner = segmentosFases(atv.fases);
    return inner ? '<div class="barra-fases">' + inner + '</div>' : '';
  }

  function agora() { return U.toInput(new Date()); }

  /* ============================================================== sessão */

  function aplicarSessao() {
    var usuario = St.usuario();
    var admin = ehAdmin();

    document.body.classList.toggle('modo-admin', admin);
    $('#seloPerfil').classList.toggle('admin', admin);
    $('#seloPerfilTxt').textContent = admin ? 'Administrador' : 'Visualização';
    $('#usuarioNome').textContent = usuario ? usuario.nome : '—';
    $('#usuarioAtual').title = usuario ? (usuario.nome + ' (' + usuario.usuario + ')') : '';

    if (!admin && ['nova', 'config', 'usuarios'].indexOf(app.view) !== -1) irPara('painel');
    if (app.modalId) abrirDetalhe(app.modalId); // redesenha com as permissões corretas
  }

  function irParaLogin() {
    window.location.replace('login.html');
  }

  function sair() {
    Api.logout().catch(function () { /* mesmo com falha, sai da tela */ })
      .then(irParaLogin);
  }

  /** Trata a falha de uma chamada à API de forma consistente. */
  function tratarFalha(e) {
    if (e && e.status === 401) {
      avisar('Sessão expirada. Entre novamente.', 'erro');
      setTimeout(irParaLogin, 1200);
      return;
    }
    if (e && e.status === 403) {
      avisar('Você não tem permissão para esta ação.', 'erro');
      return;
    }
    avisar((e && e.message) || 'Falha inesperada.', 'erro');
  }

  function abrirTrocaSenha() {
    abrirModal({
      titulo: 'Alterar minha senha',
      sub: 'Mínimo de 10 caracteres, com letras e números.',
      corpo: '<form id="formSenha" autocomplete="off">' +
        '<div class="form-grade">' +
        '<label class="campo col-2"><span>Senha atual</span>' +
        '<input type="password" id="sAtual" autocomplete="current-password" maxlength="200"></label>' +
        '<label class="campo"><span>Nova senha</span>' +
        '<input type="password" id="sNova" autocomplete="new-password" maxlength="200"></label>' +
        '<label class="campo"><span>Repetir nova senha</span>' +
        '<input type="password" id="sNova2" autocomplete="new-password" maxlength="200"></label>' +
        '</div><p class="nota">A senha não pode conter seu nome de usuário, e-mail ou nome.</p></form>',
      rodape: '<button class="btn btn-fantasma" data-fechar>Cancelar</button>' +
        '<button class="btn btn-primario" id="btnConfirmarSenha">Alterar senha</button>',
      modo: 'senha'
    });

    function confirmar(ev) {
      if (ev) ev.preventDefault();
      var nova = $('#sNova').value;
      if (nova !== $('#sNova2').value) {
        avisar('A confirmação não confere com a nova senha.', 'erro');
        return;
      }
      var botao = $('#btnConfirmarSenha');
      botao.disabled = true;
      Api.trocarSenha($('#sAtual').value, nova).then(function () {
        fecharModal();
        avisar('Senha alterada.', 'ok');
      }).catch(tratarFalha).finally(function () { botao.disabled = false; });
    }

    $('#formSenha').addEventListener('submit', confirmar);
    $('#btnConfirmarSenha').addEventListener('click', confirmar);
    setTimeout(function () { var c = $('#sAtual'); if (c) c.focus(); }, 60);
  }

  /* ============================================================ navegação */

  function irPara(view) {
    app.view = view;
    $$('.aba').forEach(function (b) { b.classList.toggle('ativa', b.dataset.view === view); });
    $$('.view').forEach(function (s) { s.classList.toggle('ativa', s.id === 'view-' + view); });
    window.scrollTo({ top: 0, behavior: 'smooth' });
    if (view === 'painel') renderPainel();
    if (view === 'registros') renderRegistros();
    if (view === 'config') carregarConfigNaTela();
    if (view === 'usuarios') { carregarUsuarios(); carregarAuditoria(); }
  }

  /* ================================================================ modal */

  function abrirModal(op) {
    app.modoModal = op.modo || 'detalhe';
    $('#modalTitulo').textContent = op.titulo || '';
    $('#modalSub').textContent = op.sub || '';
    $('#modalCorpo').innerHTML = op.corpo || '';
    $('#modalRodape').innerHTML = op.rodape || '';
    $('#modalFundo').classList.remove('oculto');
    document.body.style.overflow = 'hidden';
  }

  function fecharModal() {
    $('#modalFundo').classList.add('oculto');
    document.body.style.overflow = '';
    app.modalId = null;
    app.modoModal = null;
  }

  /* =============================================================== PAINEL */

  function renderPainel() {
    var lista = St.listar();
    var hoje = new Date();

    var contagem = { planejada: 0, em_andamento: 0, concluida: 0, atrasada: 0, cancelada: 0 };
    var minPrev = 0, minReal = 0;
    lista.forEach(function (a) {
      contagem[St.statusAtividade(a)]++;
      minPrev += St.duracaoPrevista(a);
      minReal += St.duracaoRealizada(a);
    });

    var doMes = lista.filter(function (a) {
      var d = U.parseLocal(a.inicioPrevisto);
      return d && d.getMonth() === hoje.getMonth() && d.getFullYear() === hoje.getFullYear();
    }).length;

    $('#kpis').innerHTML = [
      { rotulo: 'Atividades', valor: lista.length, nota: doMes + ' neste mês', classe: '' },
      { rotulo: 'Em andamento', valor: contagem.em_andamento, nota: 'iniciadas e não concluídas', classe: 'acento' },
      { rotulo: 'Concluídas', valor: contagem.concluida, nota: 'com todas as fases finalizadas', classe: 'ok' },
      { rotulo: 'Atrasadas', valor: contagem.atrasada, nota: 'prazo previsto vencido', classe: 'alerta' },
      {
        rotulo: 'Horas previstas', valor: (minPrev / 60).toFixed(1).replace('.', ','),
        nota: 'realizado: ' + (minReal / 60).toFixed(1).replace('.', ',') + ' h', classe: ''
      }
    ].map(function (k) {
      return '<div class="kpi ' + k.classe + '"><div class="kpi-rotulo">' + e(k.rotulo) + '</div>' +
        '<div class="kpi-valor">' + e(String(k.valor)) + '</div>' +
        '<div class="kpi-nota">' + e(k.nota) + '</div></div>';
    }).join('');

    $('#dataHoje').textContent = U.fmtCompleto(hoje).replace(/ \d{2}:\d{2}$/, '');

    var deHoje = lista.filter(function (a) {
      return U.mesmoDia(a.inicioPrevisto, hoje) || U.mesmoDia(a.inicioReal, hoje);
    }).sort(ordenarPorInicio);
    render_itens('#listaHoje', deHoje, 'Nenhuma atividade prevista para hoje.');

    var limite = new Date(hoje.getTime() + 14 * 86400000);
    var proximas = lista.filter(function (a) {
      var d = U.parseLocal(a.inicioPrevisto);
      var st = St.statusAtividade(a);
      return d && d > hoje && d <= limite && st !== 'cancelada' && st !== 'concluida';
    }).sort(ordenarPorInicio).slice(0, 8);
    render_itens('#listaProximas', proximas, 'Nada previsto para os próximos 14 dias.');

    var pendentes = lista.filter(function (a) {
      var st = St.statusAtividade(a);
      return st === 'em_andamento' || st === 'atrasada';
    }).sort(ordenarPorInicio);
    render_itens('#listaPendencias', pendentes, 'Nenhuma pendência — tudo em dia.');
  }

  function ordenarPorInicio(a, b) {
    return (U.parseLocal(a.inicioPrevisto) || 0) - (U.parseLocal(b.inicioPrevisto) || 0);
  }

  function render_itens(seletor, lista, vazio) {
    var cx = $(seletor);
    if (!lista.length) {
      cx.innerHTML = '<div class="estado-vazio"><p>' + e(vazio) + '</p></div>';
      return;
    }
    cx.innerHTML = lista.map(function (a) {
      var st = St.statusAtividade(a);
      var cor = (St.STATUS[st] || {}).cor || 'neutro';
      var meta = [a.responsavel, a.local].filter(Boolean).join(' · ');
      return '<div class="item b-' + cor + '" data-id="' + e(a.id) + '">' +
        '<span class="hora">' + e(U.fmtHora(a.inicioPrevisto)) + '</span>' +
        '<span class="corpo"><strong>' + e(a.titulo) + '</strong>' +
        '<small>' + e(U.fmtData(a.inicioPrevisto)) + (meta ? ' · ' + e(meta) : '') +
        ' · ' + e(U.fmtDuracao(St.duracaoPrevista(a))) + '</small></span>' +
        selo(st) + '</div>';
    }).join('');
  }

  /* ============================================================ REGISTROS */

  function filtrosAtuais() {
    return {
      busca: $('#fBusca').value.trim(),
      de: $('#fDe').value,
      ate: $('#fAte').value,
      responsavel: $('#fResponsavel').value,
      categoria: $('#fCategoria').value,
      status: $('#fStatus').value
    };
  }

  function atividadesFiltradas() { return St.filtrar(filtrosAtuais()); }

  function atualizarSelects() {
    function preencher(sel, itens, rotuloTodos) {
      var atual = sel.value;
      sel.innerHTML = '<option value="">' + e(rotuloTodos) + '</option>' +
        itens.map(function (i) {
          return '<option value="' + e(i) + '">' + e(i) + '</option>';
        }).join('');
      if (itens.indexOf(atual) !== -1) sel.value = atual;
    }
    preencher($('#fResponsavel'), St.responsaveis(), 'Todos');
    preencher($('#fCategoria'), St.categorias(), 'Todas');

    $('#listaResponsaveis').innerHTML = St.responsaveis().map(function (i) {
      return '<option value="' + e(i) + '"></option>';
    }).join('');
    $('#listaCategorias').innerHTML = St.categorias().map(function (i) {
      return '<option value="' + e(i) + '"></option>';
    }).join('');
  }

  function renderRegistros() {
    atualizarSelects();
    var lista = atividadesFiltradas();
    $('#contadorRegistros').textContent = lista.length;
    $('#vazioRegistros').classList.toggle('oculto', lista.length > 0);
    $('#tabelaRegistros').classList.toggle('oculto', lista.length === 0);

    $('#corpoRegistros').innerHTML = lista.map(function (a) {
      var st = St.statusAtividade(a);
      var meta = [a.responsavel, a.categoria, a.local].filter(Boolean)
        .map(function (m) { return '<span>' + e(m) + '</span>'; }).join('<i>·</i>');

      return '<tr class="clicavel" data-id="' + e(a.id) + '">' +
        '<td><div class="cel-titulo">' + e(a.titulo) + '</div>' +
        (meta ? '<div class="cel-meta">' + meta + '</div>' : '') + '</td>' +
        '<td class="td-nowrap">' + e(U.fmtDataHora(a.inicioPrevisto)) + '</td>' +
        '<td class="td-nowrap">' + e(U.fmtDataHora(a.fimPrevisto)) + '</td>' +
        '<td class="td-nowrap">' + e(U.fmtDuracao(St.duracaoPrevista(a))) + '</td>' +
        '<td>' + barraProgresso(St.progresso(a)) + '</td>' +
        '<td>' + selo(st) + '</td>' +
        '<td class="col-acoes"><button class="btn btn-fantasma btn-p" data-abrir="' +
        e(a.id) + '">Abrir</button></td>' +
        '</tr>';
    }).join('');
  }

  /* =============================================== detalhe da atividade */

  function abrirDetalhe(id) {
    var a = St.obter(id);
    if (!a) return;
    app.modalId = id;
    var admin = ehAdmin();
    var st = St.statusAtividade(a);
    var desvio = St.desvio(a);

    var ficha = [
      ['Responsável', a.responsavel || '—'],
      ['Categoria', a.categoria || '—'],
      ['Local', a.local || '—'],
      ['Início previsto', U.fmtCompleto(a.inicioPrevisto)],
      ['Término previsto', U.fmtCompleto(a.fimPrevisto)],
      ['Duração prevista', U.fmtDuracao(St.duracaoPrevista(a))],
      ['Início real', a.inicioReal ? U.fmtCompleto(a.inicioReal) : '—'],
      ['Término real', a.fimReal ? U.fmtCompleto(a.fimReal) : '—'],
      ['Duração realizada', St.duracaoRealizada(a) ? U.fmtDuracao(St.duracaoRealizada(a)) : '—'],
      ['Desvio', desvio == null ? '—' : (desvio > 0 ? '+' : '') + U.fmtDuracao(desvio)]
    ].map(function (p) {
      return '<div class="ficha-item"><span>' + e(p[0]) + '</span><strong>' + e(p[1]) + '</strong></div>';
    }).join('');

    var fases = (a.fases || []).map(function (f, i) {
      var stf = St.statusFase(f);
      var dur = (f.inicioReal && f.fimReal) ? U.diffMin(f.inicioReal, f.fimReal) : f.duracaoMin;
      var celInicio, celFim, acoes = '';

      if (admin) {
        celInicio = '<input type="datetime-local" data-campo="inicioReal" data-fase="' +
          e(f.id) + '" value="' + e(f.inicioReal || '') + '">';
        celFim = '<input type="datetime-local" data-campo="fimReal" data-fase="' +
          e(f.id) + '" value="' + e(f.fimReal || '') + '">';
        acoes = '<div class="acoes-fase">' +
          (!f.inicioReal
            ? '<button class="btn btn-fantasma btn-p" data-acao="iniciar" data-fase="' + e(f.id) + '">Iniciar</button>'
            : (!f.fimReal
              ? '<button class="btn btn-fantasma btn-p" data-acao="concluir" data-fase="' + e(f.id) + '">Concluir</button>'
              : '')) +
          ((f.inicioReal || f.fimReal)
            ? '<button class="mini-btn remover" title="Limpar registro" data-acao="limpar" data-fase="' +
              e(f.id) + '">↺</button>' : '') +
          '</div>';
      } else {
        celInicio = e(f.inicioReal ? U.fmtDataHora(f.inicioReal) : '—');
        celFim = e(f.fimReal ? U.fmtDataHora(f.fimReal) : '—');
      }

      return '<tr>' +
        '<td class="idx">' + (i + 1) + '</td>' +
        '<td class="cel-titulo">' + e(f.nome) +
        (f.observacao ? '<div class="cel-meta"><span>' + e(f.observacao) + '</span></div>' : '') + '</td>' +
        '<td class="td-nowrap cel-previsto">' + e(U.fmtDataHora(f.inicioPrevisto)) +
        '<span>até ' + e(U.fmtDataHora(f.fimPrevisto)) + '</span></td>' +
        '<td>' + celInicio + '</td>' +
        '<td>' + celFim + '</td>' +
        '<td class="td-nowrap">' + e(U.fmtDuracao(dur)) + '</td>' +
        '<td>' + selo(stf) + '</td>' +
        (admin ? '<td class="col-acoes">' + acoes + '</td>' : '') +
        '</tr>';
    }).join('');

    var corpo =
      '<div class="ficha">' + ficha + '</div>' +
      (a.descricao ? '<div class="bloco-titulo">Descrição</div><div class="descricao-box">' +
        e(a.descricao) + '</div>' : '') +
      '<div class="bloco-titulo">Linha do tempo das fases</div>' +
      (barraFases(a) || '<p class="nota">Sem fases cadastradas.</p>') +
      '<div class="bloco-titulo">Fases</div>' +
      '<div class="tabela-rolagem"><table class="tabela tabela-fases"><thead><tr>' +
      '<th>#</th><th>Fase</th><th>Previsto</th>' +
      '<th>Início real</th><th>Término real</th><th>Duração</th><th>Situação</th>' +
      (admin ? '<th></th>' : '') +
      '</tr></thead><tbody>' +
      (fases || '<tr><td colspan="' + (admin ? 8 : 7) +
        '" class="estado-vazio">Sem fases cadastradas.</td></tr>') +
      '</tbody></table></div>';

    var rodape = '';
    if (admin) {
      rodape += '<button class="btn btn-perigo btn-p" data-modal-acao="excluir">Excluir</button>' +
        '<button class="btn btn-fantasma btn-p" data-modal-acao="cancelar">' +
        (a.cancelada ? 'Reativar' : 'Cancelar atividade') + '</button>' +
        '<button class="btn btn-fantasma btn-p" data-modal-acao="duplicar">Duplicar</button>' +
        '<button class="btn btn-fantasma btn-p" data-modal-acao="replanejar">Replanejar restante</button>' +
        '<button class="btn btn-fantasma btn-p" data-modal-acao="editar">Editar</button>';
    }
    rodape += '<button class="btn btn-fantasma btn-p" data-modal-acao="imprimir">Imprimir</button>' +
      '<button class="btn btn-fantasma btn-p" data-modal-acao="pdf">PDF</button>' +
      '<button class="btn btn-primario btn-p" data-fechar>Fechar</button>';

    abrirModal({
      titulo: a.titulo,
      sub: (St.STATUS[st] || {}).rotulo + ' · ' + St.progresso(a) + '% concluído · ' +
        U.fmtCompleto(a.inicioPrevisto),
      corpo: corpo,
      rodape: rodape,
      modo: 'detalhe'
    });
    app.modalId = id;
  }

  /** Cópia da atividade para editar sem sujar o cache antes de o servidor aceitar. */
  function rascunho(a) {
    return JSON.parse(JSON.stringify(a));
  }

  /** Grava a atividade no servidor e redesenha as telas. */
  function gravar(id, dados, mensagem) {
    return St.atualizar(id, dados).then(function (atv) {
      if (app.modalId === id) abrirDetalhe(id);
      atualizarTudo();
      if (mensagem) avisar(mensagem, 'ok');
      return atv;
    }).catch(function (e) {
      tratarFalha(e);
      // Recarrega do servidor para descartar qualquer divergência local.
      return St.recarregar().then(function () {
        if (app.modalId === id) abrirDetalhe(id);
        atualizarTudo();
      });
    });
  }

  /** Ações dentro do modal de detalhe (delegação de eventos). */
  function tratarAcaoModal(ev) {
    var alvo = ev.target.closest('[data-acao], [data-modal-acao], [data-fechar]');
    if (!alvo) return;
    if (alvo.hasAttribute('data-fechar')) { fecharModal(); return; }

    var a = St.obter(app.modalId);
    if (!a) return;

    var acaoFase = alvo.getAttribute('data-acao');
    if (acaoFase) {
      if (!ehAdmin()) return;
      var copia = rascunho(a);
      var fase = (copia.fases || []).filter(function (f) { return f.id === alvo.dataset.fase; })[0];
      if (!fase) return;

      if (acaoFase === 'iniciar') {
        fase.inicioReal = agora();
      } else if (acaoFase === 'concluir') {
        if (!fase.inicioReal) fase.inicioReal = fase.inicioPrevisto;
        fase.fimReal = agora();
      } else if (acaoFase === 'limpar') {
        fase.inicioReal = ''; fase.fimReal = '';
      }
      St.sincronizarTotais(copia);
      gravar(a.id, copia, 'Registro atualizado.');
      return;
    }

    var acao = alvo.getAttribute('data-modal-acao');
    var meta = metaRelatorio('Atividade: ' + a.titulo);
    if (acao === 'imprimir') { Ex.imprimir([a], meta); return; }
    if (acao === 'pdf') { Ex.paraPDF([a], meta); avisar('PDF gerado.', 'ok'); return; }

    // A interface esconde os botões; esta é a segunda barreira — a definitiva
    // está no servidor, que recusa a requisição de quem não é admin.
    if (!ehAdmin()) return;

    if (acao === 'editar') {
      fecharModal();
      carregarNoFormulario(a);
      irPara('nova');
      return;
    }
    if (acao === 'duplicar') {
      var nova = rascunho(a);
      delete nova.id;
      nova.titulo = a.titulo + ' (cópia)';
      nova.inicioReal = ''; nova.fimReal = ''; nova.cancelada = false;
      nova.fases = (nova.fases || []).map(function (f) {
        return Object.assign({}, f, { inicioReal: '', fimReal: '' });
      });
      St.criar(nova).then(function (criada) {
        atualizarTudo();
        avisar('Atividade duplicada.', 'ok');
        abrirDetalhe(criada.id);
      }).catch(tratarFalha);
      return;
    }
    if (acao === 'cancelar') {
      var alterada = rascunho(a);
      alterada.cancelada = !a.cancelada;
      gravar(a.id, alterada, alterada.cancelada ? 'Atividade cancelada.' : 'Atividade reativada.');
      return;
    }
    if (acao === 'replanejar') {
      replanejar(a);
      return;
    }
    if (acao === 'excluir') {
      if (!confirm('Excluir definitivamente a atividade "' + a.titulo + '"?')) return;
      St.remover(a.id).then(function () {
        fecharModal();
        atualizarTudo();
        avisar('Atividade excluída.');
      }).catch(tratarFalha);
    }
  }

  /** Recalcula a previsão das fases ainda não concluídas, a partir de agora. */
  function replanejar(a) {
    var copia = rascunho(a);
    var pendentes = (copia.fases || []).filter(function (f) { return !f.fimReal; });
    if (!pendentes.length) { avisar('Todas as fases já foram concluídas.'); return; }
    if (!confirm('Recalcular o início e o término previstos das ' + pendentes.length +
      ' fase(s) ainda não concluídas, a partir de agora?')) return;

    var jornada = St.config().jornada;
    var cursor = new Date();
    copia.fases.forEach(function (f) {
      if (f.fimReal) return;
      var ini = f.inicioReal ? U.parseLocal(f.inicioReal) : S.proximoInstanteUtil(cursor, jornada);
      var fim = S.somarMinutosUteis(ini, Math.max(1, +f.duracaoMin || 1), jornada);
      f.inicioPrevisto = U.toInput(ini);
      f.fimPrevisto = U.toInput(fim);
      cursor = fim;
    });
    St.sincronizarTotais(copia);
    gravar(a.id, copia, 'Previsão recalculada.');
  }

  /** Edição direta de início/término reais nos campos do modal. */
  function tratarEdicaoFase(ev) {
    if (!ehAdmin()) return;
    var campo = ev.target.getAttribute && ev.target.getAttribute('data-campo');
    if (!campo) return;
    var a = St.obter(app.modalId);
    if (!a) return;

    var copia = rascunho(a);
    var fase = (copia.fases || []).filter(function (f) { return f.id === ev.target.dataset.fase; })[0];
    if (!fase) return;

    fase[campo] = ev.target.value;
    if (fase.fimReal && fase.inicioReal &&
      U.parseLocal(fase.fimReal) < U.parseLocal(fase.inicioReal)) {
      avisar('O término não pode ser anterior ao início.', 'erro');
      ev.target.value = '';
      return;
    }
    St.sincronizarTotais(copia);
    gravar(a.id, copia);
  }

  /* =========================================================== FORMULÁRIO */

  function novaFase(nome, peso, duracao) {
    return {
      id: U.uid('fase'), nome: nome || '', peso: peso == null ? 25 : peso,
      duracaoMin: duracao == null ? 60 : duracao, inicioReal: '', fimReal: '', observacao: ''
    };
  }

  function modoDistribuicao() {
    var el = document.querySelector('input[name="modoDistribuicao"]:checked');
    return el ? el.value : 'peso';
  }

  function modoTermino() {
    var el = document.querySelector('input[name="modoTermino"]:checked');
    return el ? el.value : 'duracao';
  }

  function duracaoTotalInformada() {
    if (modoTermino() === 'fim') {
      var ini = $('#aInicio').value, fim = $('#aFim').value;
      if (!ini || !fim) return 0;
      return S.minutosUteisEntre(ini, fim, St.config().jornada);
    }
    return (parseInt($('#aHoras').value, 10) || 0) * 60 + (parseInt($('#aMinutos').value, 10) || 0);
  }

  function renderEditorFases() {
    var fixa = modoDistribuicao() === 'fixa';
    $('#rotuloValorFase').textContent = fixa ? 'Duração (min)' : 'Peso (%)';

    $('#editorFases').innerHTML = app.fases.map(function (f, i) {
      return '<div class="linha-fase" data-idx="' + i + '">' +
        '<span class="ordem">' + (i + 1) + '</span>' +
        '<input type="text" data-campo="nome" value="' + e(f.nome) +
        '" placeholder="Nome da fase" maxlength="80">' +
        '<input type="number" data-campo="' + (fixa ? 'duracaoMin' : 'peso') + '" min="' +
        (fixa ? '1' : '0') + '" step="' + (fixa ? '5' : '1') + '" value="' +
        e(String(fixa ? (f.duracaoMin || 30) : (f.peso || 0))) + '">' +
        '<span class="mini-acoes">' +
        '<button type="button" class="mini-btn" data-mover="-1" title="Subir"' +
        (i === 0 ? ' disabled' : '') + '>↑</button>' +
        '<button type="button" class="mini-btn" data-mover="1" title="Descer"' +
        (i === app.fases.length - 1 ? ' disabled' : '') + '>↓</button>' +
        '<button type="button" class="mini-btn remover" data-remover="1" title="Remover">✕</button>' +
        '</span></div>';
    }).join('');

    if (!fixa) {
      var soma = app.fases.reduce(function (s, f) { return s + (+f.peso || 0); }, 0);
      $('#avisoPesos').textContent = soma === 100 ? '' :
        'Soma dos pesos: ' + soma + '% (o tempo será rateado proporcionalmente).';
    } else {
      var somaMin = app.fases.reduce(function (s, f) { return s + (+f.duracaoMin || 0); }, 0);
      $('#avisoPesos').textContent = 'Duração somada das fases: ' + U.fmtDuracao(somaMin);
    }
  }

  function calcularPrevisao() {
    var inicio = $('#aInicio').value;
    var corpo = $('#corpoPrevisao');

    if (!inicio || !app.fases.length) {
      corpo.innerHTML = '<tr><td colspan="5" class="estado-vazio">' +
        'Informe o início previsto e ao menos uma fase para ver a previsão.</td></tr>';
      $('#resumoPrevisao').textContent = '—';
      $('#barraPrevisao').innerHTML = '';
      return null;
    }

    var plano = S.planejar({
      inicio: inicio,
      duracaoMin: duracaoTotalInformada(),
      fases: app.fases,
      distribuir: modoDistribuicao(),
      jornada: St.config().jornada
    });

    corpo.innerHTML = plano.fases.map(function (f, i) {
      return '<tr>' +
        '<td class="idx">' + (i + 1) + '</td>' +
        '<td class="cel-titulo">' + e(f.nome) + '</td>' +
        '<td class="td-nowrap">' + e(U.fmtCompleto(f.inicioPrevisto)) + '</td>' +
        '<td class="td-nowrap">' + e(U.fmtCompleto(f.fimPrevisto)) + '</td>' +
        '<td class="td-nowrap">' + e(U.fmtDuracao(f.duracaoMin)) + '</td>' +
        '</tr>';
    }).join('');

    $('#resumoPrevisao').textContent =
      U.fmtCompleto(plano.inicio) + '  →  ' + U.fmtCompleto(plano.fim) +
      '  ·  ' + U.fmtDuracao(plano.duracaoMin) + ' em ' + plano.fases.length + ' fase(s)';

    $('#barraPrevisao').innerHTML = segmentosFases(plano.fases);

    return plano;
  }

  function limparFormulario() {
    app.editandoId = null;
    $('#tituloForm').textContent = 'Nova atividade';
    $('#btnSalvarAtividade').textContent = 'Salvar atividade';
    $('#formAtividade').reset();

    var d = new Date();
    d.setMinutes(0, 0, 0);
    d.setHours(d.getHours() + 1);
    $('#aInicio').value = U.toInput(d);
    $('#aHoras').value = 4;
    $('#aMinutos').value = 0;
    $('#aModelo').value = 'padrao';

    aplicarModelo('padrao');
    trocarModoTermino();
    renderEditorFases();
    calcularPrevisao();
  }

  function aplicarModelo(id) {
    var modelo = S.modelo(id);
    var total = duracaoTotalInformada() || 240;
    app.fases = modelo.fases.map(function (f) {
      return novaFase(f.nome, f.peso, Math.round(total * f.peso / 100));
    });
    renderEditorFases();
  }

  function carregarNoFormulario(a) {
    app.editandoId = a.id;
    $('#tituloForm').textContent = 'Editar atividade';
    $('#btnSalvarAtividade').textContent = 'Salvar alterações';

    $('#aTitulo').value = a.titulo || '';
    $('#aResponsavel').value = a.responsavel || '';
    $('#aCategoria').value = a.categoria || '';
    $('#aLocal').value = a.local || '';
    $('#aDescricao').value = a.descricao || '';
    $('#aInicio').value = a.inicioPrevisto || '';
    $('#aModelo').value = a.modelo || 'livre';

    var total = St.duracaoPrevista(a);
    $('#aHoras').value = Math.floor(total / 60);
    $('#aMinutos').value = total % 60;
    document.querySelector('input[name="modoTermino"][value="duracao"]').checked = true;
    document.querySelector('input[name="modoDistribuicao"][value="fixa"]').checked = true;

    app.fases = (a.fases || []).map(function (f) {
      return {
        id: f.id || U.uid('fase'), nome: f.nome, peso: +f.peso || 0,
        duracaoMin: +f.duracaoMin || 30,
        inicioReal: f.inicioReal || '', fimReal: f.fimReal || '',
        observacao: f.observacao || ''
      };
    });
    if (!app.fases.length) app.fases = [novaFase('Fase 1', 100, total || 60)];

    trocarModoTermino();
    renderEditorFases();
    calcularPrevisao();
  }

  function trocarModoTermino() {
    var porDuracao = modoTermino() === 'duracao';
    $('#grupoDuracao').classList.toggle('oculto', !porDuracao);
    $('#grupoFim').classList.toggle('oculto', porDuracao);
    var fixa = modoDistribuicao() === 'fixa';
    // com duração fixa por fase, o total é a soma das fases
    $('#grupoDuracao').classList.toggle('oculto', !porDuracao || fixa);
    $('#grupoFim').classList.toggle('oculto', porDuracao || fixa);
  }

  function salvarAtividade(ev) {
    ev.preventDefault();
    if (!ehAdmin()) { avisar('Apenas administradores podem salvar.', 'erro'); return; }

    var titulo = $('#aTitulo').value.trim();
    if (!titulo) { avisar('Informe o título da atividade.', 'erro'); $('#aTitulo').focus(); return; }
    if (!$('#aInicio').value) { avisar('Informe o início previsto.', 'erro'); $('#aInicio').focus(); return; }
    if (!app.fases.length) { avisar('Cadastre ao menos uma fase.', 'erro'); return; }
    if (app.fases.some(function (f) { return !f.nome.trim(); })) {
      avisar('Todas as fases precisam de um nome.', 'erro'); return;
    }
    if (modoDistribuicao() === 'peso' && duracaoTotalInformada() <= 0) {
      avisar('Informe a duração total (ou um término posterior ao início).', 'erro'); return;
    }

    var plano = calcularPrevisao();
    if (!plano) { avisar('Não foi possível calcular a previsão.', 'erro'); return; }

    var dados = {
      titulo: titulo,
      responsavel: $('#aResponsavel').value.trim(),
      categoria: $('#aCategoria').value.trim(),
      local: $('#aLocal').value.trim(),
      descricao: $('#aDescricao').value.trim(),
      modelo: $('#aModelo').value,
      fases: plano.fases,
      inicioPrevisto: U.toInput(plano.inicio),
      fimPrevisto: U.toInput(plano.fim)
    };

    var editando = app.editandoId;
    var base = editando ? Object.assign(rascunho(St.obter(editando) || {}), dados) : dados;
    St.sincronizarTotais(base);

    var botao = $('#btnSalvarAtividade');
    var rotulo = botao.textContent;
    botao.disabled = true;
    botao.textContent = 'Salvando…';

    var envio = editando ? St.atualizar(editando, base) : St.criar(base);

    envio.then(function (atv) {
      avisar(editando ? 'Atividade atualizada.' : 'Atividade registrada com a previsão das fases.', 'ok');
      limparFormulario();
      atualizarTudo();
      irPara('registros');
      setTimeout(function () { abrirDetalhe(atv.id); }, 220);
    }).catch(tratarFalha).finally(function () {
      botao.disabled = false;
      botao.textContent = rotulo;
    });
  }

  /* ======================================================== CONFIGURAÇÕES */

  function carregarConfigNaTela() {
    var c = St.config();
    var j = S.normalizarJornada(c.jornada);

    $('#cOrganizacao').value = c.organizacao || '';
    $('#cSubtitulo').value = c.subtitulo || '';
    document.querySelector('input[name="jModo"][value="' + j.modo + '"]').checked = true;
    $('#jInicio').value = j.inicio;
    $('#jFim').value = j.fim;
    $('#jPausaAtiva').checked = !!j.pausaAtiva;
    $('#jPausaInicio').value = j.pausaInicio;
    $('#jPausaFim').value = j.pausaFim;

    $('#jDias').innerHTML = U.DIAS_SEMANA.map(function (nome, i) {
      return '<label class="dia-chip"><input type="checkbox" value="' + i + '"' +
        (j.dias.indexOf(i) !== -1 ? ' checked' : '') + '>' + e(nome) + '</label>';
    }).join('');

    alternarCamposJornada();
  }

  function alternarCamposJornada() {
    var continuo = document.querySelector('input[name="jModo"]:checked').value === 'continuo';
    $$('.grupo-comercial').forEach(function (el) { el.classList.toggle('oculto', continuo); });
  }

  function salvarConfiguracoes() {
    var dias = $$('#jDias input:checked').map(function (i) { return +i.value; });
    var modo = document.querySelector('input[name="jModo"]:checked').value;

    if (modo === 'comercial') {
      if (!dias.length) { avisar('Selecione ao menos um dia útil.', 'erro'); return; }
      if (U.hhmmParaMin($('#jFim').value) <= U.hhmmParaMin($('#jInicio').value)) {
        avisar('O fim do expediente deve ser posterior ao início.', 'erro'); return;
      }
    }

    var botao = $('#btnSalvarConfig');
    botao.disabled = true;

    St.salvarConfig({
      organizacao: $('#cOrganizacao').value.trim() || 'CECAPE',
      subtitulo: $('#cSubtitulo').value.trim() || 'Diário de Bordo — Registro de Atividades',
      jornada: {
        modo: modo,
        dias: dias,
        inicio: $('#jInicio').value || '08:00',
        fim: $('#jFim').value || '18:00',
        pausaAtiva: $('#jPausaAtiva').checked,
        pausaInicio: $('#jPausaInicio').value || '12:00',
        pausaFim: $('#jPausaFim').value || '13:00'
      }
    }).then(function () {
      aplicarIdentidade();
      atualizarTudo();
      avisar('Configurações salvas.', 'ok');
    }).catch(tratarFalha).finally(function () { botao.disabled = false; });
  }

  function aplicarIdentidade() {
    var c = St.config();
    $('#marcaOrg').textContent = c.organizacao || 'CECAPE';
    $('#marcaSub').textContent = c.subtitulo || 'Diário de Bordo';
    document.title = (c.organizacao || 'CECAPE') + ' — Diário de Bordo';
    $('#resumoJornada').textContent = 'Jornada: ' + S.descreverJornada(c.jornada);
  }

  /* ============================================================ backup */

  function exportarBackup() {
    var blob = new Blob([St.exportarJSON()], { type: 'application/json' });
    var d = new Date();
    U.baixarArquivo(blob, 'backup-diario-bordo_' + d.getFullYear() +
      U.pad2(d.getMonth() + 1) + U.pad2(d.getDate()) + '.json');
    avisar('Backup exportado.', 'ok');
  }

  function importarBackup(arquivo) {
    if (arquivo.size > 8 * 1024 * 1024) {
      avisar('Arquivo grande demais (limite de 8 MB).', 'erro');
      return;
    }

    var leitor = new FileReader();
    leitor.onload = function () {
      var substituir = confirm(
        'OK = substituir todos os registros atuais pelo backup.\n' +
        'Cancelar = acrescentar os registros do backup aos existentes.');

      avisar('Enviando o backup ao servidor…');
      try {
        St.importarJSON(leitor.result, substituir).then(function (n) {
          aplicarIdentidade();
          carregarConfigNaTela();
          atualizarTudo();
          avisar(n + ' atividade(s) importada(s).', 'ok');
        }).catch(tratarFalha);
      } catch (err) {
        avisar('Falha ao importar: ' + err.message, 'erro');
      }
    };
    leitor.readAsText(arquivo);
  }

  /* ============================================================ USUÁRIOS */

  function carregarUsuarios() {
    if (!ehAdmin()) return;
    Api.listarUsuarios().then(function (lista) {
      app.usuarios = lista;
      $('#contadorUsuarios').textContent = lista.length;

      $('#corpoUsuarios').innerHTML = lista.map(function (u) {
        var eu = St.usuario() && St.usuario().id === u.id;
        return '<tr data-id="' + e(u.id) + '">' +
          '<td class="cel-titulo">' + e(u.nome) + (eu ? ' <span class="marca-eu">você</span>' : '') + '</td>' +
          '<td class="td-nowrap">' + e(u.usuario) + '</td>' +
          '<td class="td-nowrap">' + e(u.email) + '</td>' +
          '<td>' + (u.perfil === 'admin'
            ? '<span class="selo selo-info">Administrador</span>'
            : '<span class="selo selo-neutro">Visualização</span>') + '</td>' +
          '<td>' + (u.ativo
            ? '<span class="selo selo-ok">Ativo</span>'
            : '<span class="selo selo-off">Inativo</span>') +
          (u.deveTrocarSenha ? ' <span class="selo selo-alerta">Trocar senha</span>' : '') + '</td>' +
          '<td class="td-nowrap">' + e(u.ultimoAcesso ? U.fmtDataHora(u.ultimoAcesso.replace(' ', 'T')) : 'nunca') + '</td>' +
          '<td class="col-acoes"><div class="grupo-btn">' +
          '<button class="btn btn-fantasma btn-p" data-usuario-acao="editar">Editar</button>' +
          '<button class="btn btn-fantasma btn-p" data-usuario-acao="senha">Redefinir senha</button>' +
          (eu ? '' : '<button class="mini-btn remover" data-usuario-acao="excluir" title="Excluir">✕</button>') +
          '</div></td>' +
          '</tr>';
      }).join('');
    }).catch(tratarFalha);
  }

  function carregarAuditoria() {
    if (!ehAdmin()) return;
    Api.auditoria(80).then(function (registros) {
      $('#corpoAuditoria').innerHTML = registros.length ? registros.map(function (r) {
        return '<tr>' +
          '<td class="td-nowrap">' + e(U.fmtDataHora((r.criado_em || '').replace(' ', 'T'))) + '</td>' +
          '<td>' + e(r.usuario_nome || '—') + '</td>' +
          '<td><code class="acao">' + e(r.acao) + '</code></td>' +
          '<td class="td-nowrap">' + e([r.entidade, r.entidade_id].filter(Boolean).join(' #') || '—') + '</td>' +
          '<td class="td-nowrap">' + e(r.ip || '—') + '</td>' +
          '</tr>';
      }).join('') : '<tr><td colspan="5" class="estado-vazio">Nenhum registro ainda.</td></tr>';
    }).catch(tratarFalha);
  }

  function abrirFormularioUsuario(usuario) {
    var edicao = !!usuario;
    abrirModal({
      titulo: edicao ? 'Editar usuário' : 'Novo usuário',
      sub: edicao ? usuario.usuario : 'O login não pode ser alterado depois de criado.',
      corpo: '<form id="formUsuario" autocomplete="off"><div class="form-grade">' +
        '<label class="campo col-2"><span>Nome completo <b class="obrig">*</b></span>' +
        '<input type="text" id="uNome" maxlength="120" value="' +
        e(edicao ? usuario.nome : '') + '"></label>' +
        '<label class="campo"><span>Login <b class="obrig">*</b></span>' +
        '<input type="text" id="uUsuario" maxlength="60" value="' +
        e(edicao ? usuario.usuario : '') + '"' + (edicao ? ' disabled' : '') +
        ' placeholder="letras, números, . _ -"></label>' +
        '<label class="campo"><span>E-mail <b class="obrig">*</b></span>' +
        '<input type="email" id="uEmail" maxlength="160" value="' +
        e(edicao ? usuario.email : '') + '"></label>' +
        '<fieldset class="campo sem-borda"><span class="rotulo">Perfil</span><div class="opcoes">' +
        '<label class="opcao"><input type="radio" name="uPerfil" value="usuario"' +
        (!edicao || usuario.perfil === 'usuario' ? ' checked' : '') + '><span>Visualização</span></label>' +
        '<label class="opcao"><input type="radio" name="uPerfil" value="admin"' +
        (edicao && usuario.perfil === 'admin' ? ' checked' : '') + '><span>Administrador</span></label>' +
        '</div></fieldset>' +
        (edicao
          ? '<label class="campo opcao-inline"><input type="checkbox" id="uAtivo"' +
            (usuario.ativo ? ' checked' : '') + '><span>Conta ativa</span></label>'
          : '<label class="campo"><span>Senha inicial <b class="obrig">*</b></span>' +
            '<input type="password" id="uSenha" maxlength="200" autocomplete="new-password"></label>') +
        '</div>' +
        (edicao ? '' : '<p class="nota">Mínimo de 10 caracteres, com letras e números. ' +
          'O usuário será obrigado a trocá-la no primeiro acesso.</p>') +
        '</form>',
      rodape: '<button class="btn btn-fantasma" data-fechar>Cancelar</button>' +
        '<button class="btn btn-primario" id="btnSalvarUsuario">' +
        (edicao ? 'Salvar alterações' : 'Criar usuário') + '</button>',
      modo: 'usuario'
    });

    function salvar(ev) {
      if (ev) ev.preventDefault();
      var botao = $('#btnSalvarUsuario');
      var perfil = document.querySelector('input[name="uPerfil"]:checked').value;
      var dados = {
        nome: $('#uNome').value.trim(),
        email: $('#uEmail').value.trim(),
        perfil: perfil
      };

      var envio;
      if (edicao) {
        dados.id = usuario.id;
        dados.ativo = $('#uAtivo').checked;
        envio = Api.atualizarUsuario(dados);
      } else {
        dados.usuario = $('#uUsuario').value.trim();
        dados.senha = $('#uSenha').value;
        dados.deveTrocarSenha = true;
        envio = Api.criarUsuario(dados);
      }

      botao.disabled = true;
      envio.then(function () {
        fecharModal();
        carregarUsuarios();
        carregarAuditoria();
        avisar(edicao ? 'Usuário atualizado.' : 'Usuário criado.', 'ok');
      }).catch(tratarFalha).finally(function () { botao.disabled = false; });
    }

    $('#formUsuario').addEventListener('submit', salvar);
    $('#btnSalvarUsuario').addEventListener('click', salvar);
    setTimeout(function () { var c = $('#uNome'); if (c) c.focus(); }, 60);
  }

  function abrirRedefinicaoSenha(usuario) {
    abrirModal({
      titulo: 'Redefinir senha',
      sub: usuario.nome + ' (' + usuario.usuario + ')',
      corpo: '<form id="formReset" autocomplete="off"><label class="campo">' +
        '<span>Nova senha</span>' +
        '<input type="password" id="rSenha" maxlength="200" autocomplete="new-password"></label>' +
        '<p class="nota">Mínimo de 10 caracteres, com letras e números. A pessoa será ' +
        'obrigada a definir uma nova senha no próximo acesso.</p></form>',
      rodape: '<button class="btn btn-fantasma" data-fechar>Cancelar</button>' +
        '<button class="btn btn-primario" id="btnConfirmarReset">Redefinir</button>',
      modo: 'usuario'
    });

    function confirmar(ev) {
      if (ev) ev.preventDefault();
      var botao = $('#btnConfirmarReset');
      botao.disabled = true;
      Api.redefinirSenha(usuario.id, $('#rSenha').value).then(function () {
        fecharModal();
        carregarUsuarios();
        avisar('Senha redefinida.', 'ok');
      }).catch(tratarFalha).finally(function () { botao.disabled = false; });
    }

    $('#formReset').addEventListener('submit', confirmar);
    $('#btnConfirmarReset').addEventListener('click', confirmar);
    setTimeout(function () { var c = $('#rSenha'); if (c) c.focus(); }, 60);
  }

  function tratarAcaoUsuario(ev) {
    var botao = ev.target.closest('[data-usuario-acao]');
    if (!botao) return;
    var linha = botao.closest('tr[data-id]');
    if (!linha) return;

    var usuario = app.usuarios.filter(function (u) { return u.id === linha.dataset.id; })[0];
    if (!usuario) return;

    var acao = botao.getAttribute('data-usuario-acao');
    if (acao === 'editar') { abrirFormularioUsuario(usuario); return; }
    if (acao === 'senha') { abrirRedefinicaoSenha(usuario); return; }
    if (acao === 'excluir') {
      if (!confirm('Excluir o usuário "' + usuario.nome + '"?\n' +
        'As atividades registradas por ele são mantidas.')) return;
      Api.excluirUsuario(usuario.id).then(function () {
        carregarUsuarios();
        carregarAuditoria();
        avisar('Usuário excluído.');
      }).catch(tratarFalha);
    }
  }

  /* ========================================================== exportação */

  function metaRelatorio(filtroTexto) {
    var c = St.config();
    return {
      organizacao: c.organizacao || 'CECAPE',
      subtitulo: c.subtitulo || 'Diário de Bordo — Registro de Atividades',
      filtros: filtroTexto || Ex.descreverFiltros(filtrosAtuais())
    };
  }

  function exportar(tipo) {
    var lista = atividadesFiltradas();
    if (!lista.length && !confirm('Nenhum registro no filtro atual. Gerar mesmo assim?')) return;
    var meta = metaRelatorio();
    if (tipo === 'xls') { Ex.paraXLS(lista, meta); avisar('Planilha XLS gerada.', 'ok'); }
    if (tipo === 'pdf') { Ex.paraPDF(lista, meta); avisar('PDF gerado.', 'ok'); }
    if (tipo === 'print') { Ex.imprimir(lista, meta); }
  }

  /* =============================================================== tema */

  function aplicarTema(tema) {
    document.documentElement.setAttribute('data-tema', tema);
    St.salvarTema(tema);
  }

  /* ========================================================= atualização */

  function atualizarTudo() {
    if (app.view === 'painel') renderPainel();
    if (app.view === 'registros') renderRegistros(); else atualizarSelects();
  }

  /* ============================================================== eventos */

  function ligarEventos() {
    $('#abas').addEventListener('click', function (ev) {
      var b = ev.target.closest('.aba');
      if (b) irPara(b.dataset.view);
    });

    $('#btnSair').addEventListener('click', sair);
    $('#btnMinhaSenha').addEventListener('click', abrirTrocaSenha);

    $('#btnTema').addEventListener('click', function () {
      var atual = document.documentElement.getAttribute('data-tema');
      aplicarTema(atual === 'escuro' ? 'claro' : 'escuro');
    });

    // ---- filtros
    var refiltrar = U.debounce(renderRegistros, 200);
    ['#fBusca', '#fDe', '#fAte', '#fResponsavel', '#fCategoria', '#fStatus'].forEach(function (sel) {
      $(sel).addEventListener('input', refiltrar);
      $(sel).addEventListener('change', renderRegistros);
    });
    $('#btnLimparFiltros').addEventListener('click', function () {
      ['#fBusca', '#fDe', '#fAte', '#fResponsavel', '#fCategoria', '#fStatus'].forEach(function (s) {
        $(s).value = '';
      });
      renderRegistros();
    });

    // ---- exportação
    $('#btnXLS').addEventListener('click', function () { exportar('xls'); });
    $('#btnPDF').addEventListener('click', function () { exportar('pdf'); });
    $('#btnImprimir').addEventListener('click', function () { exportar('print'); });

    // ---- abrir detalhe
    $('#corpoRegistros').addEventListener('click', function (ev) {
      var linha = ev.target.closest('tr[data-id]');
      if (linha) abrirDetalhe(linha.dataset.id);
    });
    ['#listaHoje', '#listaProximas', '#listaPendencias'].forEach(function (sel) {
      $(sel).addEventListener('click', function (ev) {
        var item = ev.target.closest('.item[data-id]');
        if (item) abrirDetalhe(item.dataset.id);
      });
    });

    // ---- modal
    $('#btnFecharModal').addEventListener('click', fecharModal);
    $('#modalFundo').addEventListener('mousedown', function (ev) {
      if (ev.target === $('#modalFundo')) fecharModal();
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && !$('#modalFundo').classList.contains('oculto')) fecharModal();
    });
    $('#modalCorpo').addEventListener('click', tratarAcaoModal);
    $('#modalRodape').addEventListener('click', tratarAcaoModal);
    $('#modalCorpo').addEventListener('change', tratarEdicaoFase);

    // ---- formulário
    var recalcular = U.debounce(calcularPrevisao, 220);
    ['#aInicio', '#aFim', '#aHoras', '#aMinutos'].forEach(function (sel) {
      $(sel).addEventListener('input', recalcular);
      $(sel).addEventListener('change', calcularPrevisao);
    });

    $$('input[name="modoTermino"]').forEach(function (r) {
      r.addEventListener('change', function () { trocarModoTermino(); calcularPrevisao(); });
    });
    $$('input[name="modoDistribuicao"]').forEach(function (r) {
      r.addEventListener('change', function () {
        // ao trocar para duração fixa, converte os pesos em minutos
        var total = duracaoTotalInformada() || 240;
        if (modoDistribuicao() === 'fixa') {
          var soma = app.fases.reduce(function (s, f) { return s + (+f.peso || 0); }, 0) || 100;
          app.fases.forEach(function (f) {
            f.duracaoMin = Math.max(1, Math.round(total * (+f.peso || 0) / soma));
          });
        } else {
          var somaMin = app.fases.reduce(function (s, f) { return s + (+f.duracaoMin || 0); }, 0) || 1;
          app.fases.forEach(function (f) {
            f.peso = Math.round((+f.duracaoMin || 0) * 100 / somaMin);
          });
        }
        trocarModoTermino();
        renderEditorFases();
        calcularPrevisao();
      });
    });

    $('#aModelo').addEventListener('change', function () {
      aplicarModelo(this.value);
      calcularPrevisao();
    });

    $('#btnAddFase').addEventListener('click', function () {
      app.fases.push(novaFase('Nova fase', 10, 30));
      renderEditorFases();
      calcularPrevisao();
      $('#aModelo').value = 'livre';
    });

    $('#editorFases').addEventListener('input', function (ev) {
      var linha = ev.target.closest('.linha-fase');
      if (!linha) return;
      var f = app.fases[+linha.dataset.idx];
      var campo = ev.target.getAttribute('data-campo');
      if (!f || !campo) return;
      f[campo] = campo === 'nome' ? ev.target.value : Math.max(0, +ev.target.value || 0);
      if (campo !== 'nome') {
        var soma = app.fases.reduce(function (s, x) {
          return s + (+x[campo] || 0);
        }, 0);
        $('#avisoPesos').textContent = campo === 'peso'
          ? (soma === 100 ? '' : 'Soma dos pesos: ' + soma + '% (rateio proporcional).')
          : 'Duração somada das fases: ' + U.fmtDuracao(soma);
      }
      calcularPrevisao();
    });

    $('#editorFases').addEventListener('click', function (ev) {
      var botao = ev.target.closest('button');
      var linha = ev.target.closest('.linha-fase');
      if (!botao || !linha) return;
      var i = +linha.dataset.idx;

      if (botao.hasAttribute('data-remover')) {
        if (app.fases.length === 1) { avisar('É preciso manter ao menos uma fase.', 'erro'); return; }
        app.fases.splice(i, 1);
      } else if (botao.hasAttribute('data-mover')) {
        var destino = i + (+botao.dataset.mover);
        if (destino < 0 || destino >= app.fases.length) return;
        var tmp = app.fases[i];
        app.fases[i] = app.fases[destino];
        app.fases[destino] = tmp;
      } else { return; }

      $('#aModelo').value = 'livre';
      renderEditorFases();
      calcularPrevisao();
    });

    $('#formAtividade').addEventListener('submit', salvarAtividade);
    $('#btnCancelarForm').addEventListener('click', function () {
      limparFormulario();
      irPara('registros');
    });

    // ---- configurações
    $$('input[name="jModo"]').forEach(function (r) {
      r.addEventListener('change', alternarCamposJornada);
    });
    $('#btnSalvarConfig').addEventListener('click', salvarConfiguracoes);
    $('#btnBackup').addEventListener('click', exportarBackup);
    $('#btnRestaurar').addEventListener('click', function () { $('#arquivoBackup').click(); });
    $('#arquivoBackup').addEventListener('change', function () {
      if (this.files && this.files[0]) importarBackup(this.files[0]);
      this.value = '';
    });

    // ---- usuários
    $('#btnNovoUsuario').addEventListener('click', function () { abrirFormularioUsuario(null); });
    $('#corpoUsuarios').addEventListener('click', tratarAcaoUsuario);
    $('#btnAtualizarAuditoria').addEventListener('click', carregarAuditoria);
  }

  /* ================================================================ init */

  function iniciar() {
    aplicarTema(St.tema());

    $('#aModelo').innerHTML = S.MODELOS.map(function (m) {
      return '<option value="' + e(m.id) + '">' + e(m.nome) + '</option>';
    }).join('');

    // Sessão caindo no meio do uso: volta para o login sem travar a tela.
    Api.aoExpirar = function () {
      setTimeout(irParaLogin, 1200);
    };

    ligarEventos();

    St.carregar().then(function (autenticado) {
      if (!autenticado) { irParaLogin(); return; }

      var usuario = St.usuario();
      if (usuario && usuario.deveTrocarSenha) {
        // O servidor bloqueia as demais rotas até a troca; a tela de login
        // conduz esse fluxo.
        irParaLogin();
        return;
      }

      aplicarIdentidade();
      aplicarSessao();
      limparFormulario();
      carregarConfigNaTela();
      irPara('painel');
      document.body.classList.add('pronto');
    }).catch(function (e) {
      if (e && e.status === 401) { irParaLogin(); return; }
      document.body.classList.add('pronto');
      avisar('Não foi possível carregar os dados: ' + e.message, 'erro');
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciar);
  } else {
    iniciar();
  }

  global.App = app;
})(window);
