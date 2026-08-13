/* =========================================================================
 * util.js — funções utilitárias de data, formatação e DOM
 * Diário de Bordo CECAPE
 * ========================================================================= */
(function (global) {
  'use strict';

  var Util = {};

  /* ---------------------------------------------------------------- datas */

  function pad2(n) { return (n < 10 ? '0' : '') + n; }
  Util.pad2 = pad2;

  /**
   * Converte "YYYY-MM-DDTHH:mm" (valor de <input type="datetime-local">)
   * em Date no fuso local. Evita o parsing como UTC feito por new Date(str).
   */
  Util.parseLocal = function (str) {
    if (!str) return null;
    if (str instanceof Date) return str;
    var m = String(str).match(/^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2}))?/);
    if (!m) return null;
    return new Date(+m[1], +m[2] - 1, +m[3], +(m[4] || 0), +(m[5] || 0), 0, 0);
  };

  /** Date -> "YYYY-MM-DDTHH:mm" (para preencher inputs datetime-local). */
  Util.toInput = function (d) {
    if (!d) return '';
    d = Util.parseLocal(d);
    if (!d) return '';
    return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()) +
      'T' + pad2(d.getHours()) + ':' + pad2(d.getMinutes());
  };

  /** Date -> "YYYY-MM-DD" (para inputs type="date"). */
  Util.toInputDate = function (d) {
    d = Util.parseLocal(d);
    if (!d) return '';
    return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
  };

  var DIAS_SEMANA = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
  Util.DIAS_SEMANA = DIAS_SEMANA;

  Util.fmtData = function (d) {
    d = Util.parseLocal(d);
    if (!d) return '—';
    return pad2(d.getDate()) + '/' + pad2(d.getMonth() + 1) + '/' + d.getFullYear();
  };

  Util.fmtHora = function (d) {
    d = Util.parseLocal(d);
    if (!d) return '—';
    return pad2(d.getHours()) + ':' + pad2(d.getMinutes());
  };

  Util.fmtDataHora = function (d) {
    d = Util.parseLocal(d);
    if (!d) return '—';
    return Util.fmtData(d) + ' ' + Util.fmtHora(d);
  };

  /** "seg, 12/05/2025 08:00" */
  Util.fmtCompleto = function (d) {
    d = Util.parseLocal(d);
    if (!d) return '—';
    return DIAS_SEMANA[d.getDay()] + ', ' + Util.fmtData(d) + ' ' + Util.fmtHora(d);
  };

  /** Minutos -> "2h 30min" */
  Util.fmtDuracao = function (min) {
    if (min == null || isNaN(min)) return '—';
    min = Math.round(min);
    var neg = min < 0;
    min = Math.abs(min);
    var d = Math.floor(min / 1440);
    var h = Math.floor((min % 1440) / 60);
    var m = min % 60;
    var out = [];
    if (d) out.push(d + 'd');
    if (h) out.push(h + 'h');
    if (m || !out.length) out.push(m + 'min');
    return (neg ? '-' : '') + out.join(' ');
  };

  Util.addMin = function (d, min) {
    d = Util.parseLocal(d);
    return new Date(d.getTime() + min * 60000);
  };

  Util.diffMin = function (a, b) {
    a = Util.parseLocal(a); b = Util.parseLocal(b);
    if (!a || !b) return null;
    return Math.round((b.getTime() - a.getTime()) / 60000);
  };

  Util.mesmoDia = function (a, b) {
    a = Util.parseLocal(a); b = Util.parseLocal(b);
    if (!a || !b) return false;
    return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() &&
      a.getDate() === b.getDate();
  };

  Util.inicioDoDia = function (d) {
    d = Util.parseLocal(d);
    return new Date(d.getFullYear(), d.getMonth(), d.getDate(), 0, 0, 0, 0);
  };

  Util.fimDoDia = function (d) {
    d = Util.parseLocal(d);
    return new Date(d.getFullYear(), d.getMonth(), d.getDate(), 23, 59, 59, 999);
  };

  /** "HH:mm" -> minutos desde a meia-noite. */
  Util.hhmmParaMin = function (s) {
    var m = String(s || '').match(/^(\d{1,2}):(\d{2})$/);
    if (!m) return 0;
    return (+m[1]) * 60 + (+m[2]);
  };

  /** minutos desde a meia-noite -> "HH:mm" */
  Util.minParaHhmm = function (min) {
    min = Math.max(0, Math.min(1440, Math.round(min)));
    return pad2(Math.floor(min / 60)) + ':' + pad2(min % 60);
  };

  /* --------------------------------------------------------------- gerais */

  Util.uid = function (prefixo) {
    return (prefixo || 'id') + '_' +
      Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
  };

  Util.escapeHtml = function (s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  };

  /** Normaliza para busca: sem acento, minúsculo. */
  var RE_ACENTOS = new RegExp('[\\u0300-\\u036f]', 'g');
  Util.normalizar = function (s) {
    return String(s == null ? '' : s)
      .normalize('NFD').replace(RE_ACENTOS, '').toLowerCase().trim();
  };

  Util.debounce = function (fn, ms) {
    var t;
    return function () {
      var args = arguments, ctx = this;
      clearTimeout(t);
      t = setTimeout(function () { fn.apply(ctx, args); }, ms || 250);
    };
  };

  /** Hash simples (FNV-1a) — usado apenas para não guardar o PIN em texto puro. */
  Util.hash = function (s) {
    var h = 0x811c9dc5;
    s = String(s);
    for (var i = 0; i < s.length; i++) {
      h ^= s.charCodeAt(i);
      h = (h + ((h << 1) + (h << 4) + (h << 7) + (h << 8) + (h << 24))) >>> 0;
    }
    return ('00000000' + h.toString(16)).slice(-8);
  };

  Util.baixarArquivo = function (blob, nome) {
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = nome;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(function () { URL.revokeObjectURL(url); }, 4000);
  };

  Util.$ = function (sel, ctx) { return (ctx || document).querySelector(sel); };
  Util.$$ = function (sel, ctx) {
    return Array.prototype.slice.call((ctx || document).querySelectorAll(sel));
  };

  global.Util = Util;
})(window);
