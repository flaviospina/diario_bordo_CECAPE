/* =========================================================================
 * login.js — autenticação e troca obrigatória de senha no primeiro acesso
 * ========================================================================= */
(function () {
  'use strict';

  var Api = window.Api;
  var $ = function (s) { return document.querySelector(s); };

  var formLogin = $('#formLogin');
  var formTroca = $('#formTroca');
  var caixaTroca = $('#caixaTroca');

  function aplicarTema() {
    try {
      var tema = localStorage.getItem('diario_tema') || 'claro';
      document.documentElement.setAttribute('data-tema', tema);
    } catch (e) { /* modo privado */ }
  }

  function mostrarErro(seletor, mensagem) {
    var el = $(seletor);
    el.textContent = mensagem;
    el.classList.remove('oculto');
  }

  function limparErro(seletor) {
    $(seletor).classList.add('oculto');
  }

  function ocupado(botao, ativo, rotulo) {
    botao.disabled = ativo;
    botao.textContent = ativo ? 'Aguarde…' : rotulo;
  }

  function irParaApp() {
    window.location.replace('index.html');
  }

  function pedirTrocaDeSenha() {
    formLogin.parentElement.classList.add('oculto');
    caixaTroca.classList.remove('oculto');
    $('#senhaAtual').value = $('#senha').value;
    ($('#senhaAtual').value ? $('#novaSenha') : $('#senhaAtual')).focus();
    $('#senha').value = '';
  }

  /* --------------------------------------------------------------- login */

  formLogin.addEventListener('submit', function (ev) {
    ev.preventDefault();
    limparErro('#erroLogin');

    var usuario = $('#usuario').value.trim();
    var senha = $('#senha').value;

    if (!usuario || !senha) {
      mostrarErro('#erroLogin', 'Informe usuário e senha.');
      return;
    }

    ocupado($('#btnEntrar'), true, 'Entrar');
    Api.login(usuario, senha).then(function (u) {
      if (u && u.deveTrocarSenha) {
        pedirTrocaDeSenha();
      } else {
        irParaApp();
      }
    }).catch(function (e) {
      mostrarErro('#erroLogin', e.message);
      $('#senha').value = '';
      $('#senha').focus();
    }).finally(function () {
      ocupado($('#btnEntrar'), false, 'Entrar');
    });
  });

  /* ----------------------------------------------------- troca de senha */

  formTroca.addEventListener('submit', function (ev) {
    ev.preventDefault();
    limparErro('#erroTroca');

    var atual = $('#senhaAtual').value;
    var nova = $('#novaSenha').value;

    if (nova !== $('#novaSenha2').value) {
      mostrarErro('#erroTroca', 'A confirmação não confere com a nova senha.');
      return;
    }

    ocupado($('#btnTrocar'), true, 'Salvar e entrar');
    Api.trocarSenha(atual, nova)
      .then(irParaApp)
      .catch(function (e) { mostrarErro('#erroTroca', e.message); })
      .finally(function () { ocupado($('#btnTrocar'), false, 'Salvar e entrar'); });
  });

  /* --------------------------------------------------------------- início */

  aplicarTema();

  // Já autenticado? Vai direto para a aplicação.
  Api.sessao().then(function (s) {
    if (s.autenticado && s.usuario && !s.usuario.deveTrocarSenha) {
      irParaApp();
    } else if (s.autenticado && s.usuario) {
      pedirTrocaDeSenha();
    }
  }).catch(function () { /* sem servidor: mantém o formulário */ });
})();
