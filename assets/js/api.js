/* =========================================================================
 * api.js — cliente HTTP da API PHP
 *
 * Cuida do token CSRF, do envio de cookies de sessão e da normalização de
 * erros. Nenhuma tela fala com o servidor sem passar por aqui.
 * ========================================================================= */
(function (global) {
  'use strict';

  var Api = {
    base: 'api/index.php',
    csrf: null,
    usuario: null,
    aoExpirar: null       // callback disparado quando a sessão cai (401)
  };

  /** Erro com o status HTTP e os campos recusados pelo servidor. */
  function ErroApi(mensagem, status, campos) {
    var e = new Error(mensagem);
    e.nome = 'ErroApi';
    e.status = status;
    e.campos = campos || null;
    return e;
  }

  Api.pedir = function (rota, opcoes) {
    opcoes = opcoes || {};
    var partes = rota.split('?');
    var url = Api.base + '?r=' + encodeURIComponent(partes[0]) +
      (partes[1] ? '&' + partes[1] : '');

    var cabecalhos = { 'Accept': 'application/json' };
    var corpo;

    if (opcoes.corpo !== undefined) {
      cabecalhos['Content-Type'] = 'application/json';
      corpo = JSON.stringify(opcoes.corpo);
    }
    if (Api.csrf) {
      cabecalhos['X-CSRF-Token'] = Api.csrf;
    }

    return fetch(url, {
      method: opcoes.metodo || 'GET',
      headers: cabecalhos,
      body: corpo,
      credentials: 'same-origin',
      cache: 'no-store'
    }).then(function (resposta) {
      return resposta.text().then(function (texto) {
        var dados;
        try {
          dados = texto ? JSON.parse(texto) : {};
        } catch (e) {
          throw ErroApi('Resposta inesperada do servidor.', resposta.status);
        }

        if (dados && dados.csrf) {
          Api.csrf = dados.csrf;
        }

        if (!resposta.ok || dados.ok === false) {
          // 401 = sessão caiu; 428 = precisa trocar a senha antes de seguir.
          if (resposta.status === 401 && typeof Api.aoExpirar === 'function') {
            Api.aoExpirar();
          }
          throw ErroApi(
            dados.erro || 'Não foi possível concluir a operação.',
            resposta.status,
            dados.campos
          );
        }

        return dados;
      });
    }, function () {
      throw ErroApi('Sem conexão com o servidor. Verifique a rede e tente de novo.', 0);
    });
  };

  function get(rota) { return Api.pedir(rota); }
  function post(rota, corpo) { return Api.pedir(rota, { metodo: 'POST', corpo: corpo || {} }); }

  /* --------------------------------------------------------------- sessão */

  Api.sessao = function () {
    return get('auth/sessao').then(function (d) {
      Api.usuario = d.usuario || null;
      return d;
    });
  };

  Api.login = function (usuario, senha) {
    // Garante um token CSRF válido antes de tentar autenticar.
    return (Api.csrf ? Promise.resolve() : Api.sessao()).then(function () {
      return post('auth/login', { usuario: usuario, senha: senha });
    }).then(function (d) {
      Api.usuario = d.usuario || null;
      return d.usuario;
    });
  };

  Api.logout = function () {
    return post('auth/logout').then(function (d) {
      Api.usuario = null;
      return d;
    });
  };

  Api.trocarSenha = function (senhaAtual, novaSenha) {
    return post('auth/senha', { senhaAtual: senhaAtual, novaSenha: novaSenha })
      .then(function (d) {
        Api.usuario = d.usuario || Api.usuario;
        return d.usuario;
      });
  };

  /* ----------------------------------------------------------- atividades */

  Api.listarAtividades = function () {
    return get('atividades').then(function (d) { return d.atividades || []; });
  };

  Api.criarAtividade = function (dados) {
    return post('atividades/criar', dados).then(function (d) { return d.atividade; });
  };

  Api.atualizarAtividade = function (dados) {
    return post('atividades/atualizar', dados).then(function (d) { return d.atividade; });
  };

  Api.excluirAtividade = function (id) {
    return post('atividades/excluir', { id: id });
  };

  /* -------------------------------------------------------------- usuários */

  Api.listarUsuarios = function () {
    return get('usuarios').then(function (d) { return d.usuarios || []; });
  };

  Api.criarUsuario = function (dados) {
    return post('usuarios/criar', dados).then(function (d) { return d.usuario; });
  };

  Api.atualizarUsuario = function (dados) {
    return post('usuarios/atualizar', dados).then(function (d) { return d.usuario; });
  };

  Api.redefinirSenha = function (id, senha) {
    return post('usuarios/senha', { id: id, senha: senha });
  };

  Api.excluirUsuario = function (id) {
    return post('usuarios/excluir', { id: id });
  };

  Api.auditoria = function (limite) {
    return get('auditoria?limite=' + (limite || 100))
      .then(function (d) { return d.registros || []; });
  };

  /* --------------------------------------------------------- configurações */

  Api.obterConfig = function () {
    return get('config').then(function (d) { return d.config; });
  };

  Api.salvarConfig = function (config) {
    return post('config/salvar', config).then(function (d) { return d.config; });
  };

  Api.importar = function (atividades, substituir) {
    return post('config/importar', { atividades: atividades, substituir: !!substituir })
      .then(function (d) { return d.importadas; });
  };

  global.Api = Api;
})(window);
