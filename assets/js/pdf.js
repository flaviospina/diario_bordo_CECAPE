/* =========================================================================
 * pdf.js — gerador mínimo de PDF (sem dependências externas)
 *
 * Suporta apenas o necessário para o relatório do diário: texto em
 * Helvetica/Helvetica-Bold (codificação WinAnsi), retângulos preenchidos,
 * linhas e múltiplas páginas em A4 retrato ou paisagem.
 *
 * Sistema de coordenadas exposto: origem no canto SUPERIOR esquerdo,
 * com y crescendo para baixo (mais natural para montar tabelas).
 * ========================================================================= */
(function (global) {
  'use strict';

  /* Larguras dos glifos (unidades/1000) para os caracteres ASCII 32–126. */
  var W_NORMAL = [
    278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
    556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
    1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
    667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
    333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
    556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584
  ];
  var W_NEGRITO = [
    278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278,
    556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611,
    975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778,
    667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556,
    333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611,
    611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584
  ];

  var FORMATOS = {
    retrato: { largura: 595.28, altura: 841.89 },
    paisagem: { largura: 841.89, altura: 595.28 }
  };

  /** Converte para Latin-1 (WinAnsi); caracteres fora da tabela viram "?". */
  function paraLatin1(texto) {
    var s = String(texto == null ? '' : texto);
    var out = '';
    for (var i = 0; i < s.length; i++) {
      var c = s.charCodeAt(i);
      if (c === 0x2013 || c === 0x2014) out += '-';           // – —
      else if (c === 0x2018 || c === 0x2019) out += "'";      // ‘ ’
      else if (c === 0x201c || c === 0x201d) out += '"';      // “ ”
      else if (c === 0x2022) out += '*';                      // •
      else if (c === 0x20ac) out += String.fromCharCode(0x80); // € (WinAnsi)
      else if (c <= 255) out += s.charAt(i);
      else out += '?';
    }
    return out;
  }

  function escaparString(s) {
    return s.replace(/\\/g, '\\\\').replace(/\(/g, '\\(').replace(/\)/g, '\\)')
      .replace(/\r/g, '').replace(/\n/g, ' ');
  }

  function larguraTexto(texto, tamanho, negrito) {
    var s = paraLatin1(texto);
    var tabela = negrito ? W_NEGRITO : W_NORMAL;
    var total = 0;
    for (var i = 0; i < s.length; i++) {
      var c = s.charCodeAt(i);
      if (c >= 32 && c <= 126) total += tabela[c - 32];
      else if (c >= 160) total += negrito ? 570 : 540;  // aproximação p/ acentuados
      else total += tabela[0];
    }
    return total * tamanho / 1000;
  }

  /** Quebra o texto em linhas que caibam em `largura`. */
  function quebrarLinhas(texto, largura, tamanho, negrito) {
    var palavras = String(texto == null ? '' : texto).split(/\s+/).filter(Boolean);
    var linhas = [], atual = '';
    for (var i = 0; i < palavras.length; i++) {
      var teste = atual ? atual + ' ' + palavras[i] : palavras[i];
      if (larguraTexto(teste, tamanho, negrito) <= largura || !atual) {
        atual = teste;
      } else {
        linhas.push(atual);
        atual = palavras[i];
      }
    }
    if (atual) linhas.push(atual);
    return linhas.length ? linhas : [''];
  }

  /** Corta o texto com reticências para caber em `largura`. */
  function truncar(texto, largura, tamanho, negrito) {
    var s = String(texto == null ? '' : texto);
    if (larguraTexto(s, tamanho, negrito) <= largura) return s;
    var reticencias = '...';
    var limite = largura - larguraTexto(reticencias, tamanho, negrito);
    var out = '';
    for (var i = 0; i < s.length; i++) {
      if (larguraTexto(out + s.charAt(i), tamanho, negrito) > limite) break;
      out += s.charAt(i);
    }
    return out.replace(/\s+$/, '') + reticencias;
  }

  function num(n) {
    return (Math.round(n * 100) / 100).toString();
  }

  /* ------------------------------------------------------------ documento */

  function Documento(opcoes) {
    opcoes = opcoes || {};
    var fmt = FORMATOS[opcoes.orientacao === 'paisagem' ? 'paisagem' : 'retrato'];
    this.largura = fmt.largura;
    this.altura = fmt.altura;
    this.margem = opcoes.margem == null ? 36 : opcoes.margem;
    this.paginas = [];
    this.novaPagina();
  }

  Documento.prototype.novaPagina = function () {
    this.conteudo = [];
    this.paginas.push(this.conteudo);
    return this.paginas.length;
  };

  Documento.prototype.totalPaginas = function () { return this.paginas.length; };

  /** Escreve na página de índice `i` (0-based) — útil para rodapés. */
  Documento.prototype.naPagina = function (i, fn) {
    var anterior = this.conteudo;
    this.conteudo = this.paginas[i];
    try { fn(); } finally { this.conteudo = anterior; }
  };

  Documento.prototype._y = function (y) { return this.altura - y; };

  Documento.prototype.cor = function (c) {
    // c = [r, g, b] com valores 0–255
    return num(c[0] / 255) + ' ' + num(c[1] / 255) + ' ' + num(c[2] / 255);
  };

  Documento.prototype.texto = function (txt, x, y, op) {
    op = op || {};
    var tamanho = op.tamanho || 9;
    var negrito = !!op.negrito;
    var cor = op.cor || [17, 24, 39];
    var s = escaparString(paraLatin1(txt));
    if (op.largura) {
      s = escaparString(paraLatin1(truncar(txt, op.largura, tamanho, negrito)));
    }
    var px = x;
    if (op.alinhamento === 'direita' && op.largura) {
      px = x + op.largura - larguraTexto(paraLatin1(txt), tamanho, negrito);
    } else if (op.alinhamento === 'centro' && op.largura) {
      px = x + (op.largura - larguraTexto(paraLatin1(txt), tamanho, negrito)) / 2;
    }
    this.conteudo.push(
      'BT ' + this.cor(cor) + ' rg /' + (negrito ? 'F2' : 'F1') + ' ' + num(tamanho) +
      ' Tf 1 0 0 1 ' + num(px) + ' ' + num(this._y(y) - tamanho * 0.82) + ' Tm (' + s + ') Tj ET'
    );
    return this;
  };

  Documento.prototype.retangulo = function (x, y, w, h, cor) {
    this.conteudo.push(
      this.cor(cor) + ' rg ' + num(x) + ' ' + num(this._y(y) - h) + ' ' +
      num(w) + ' ' + num(h) + ' re f'
    );
    return this;
  };

  Documento.prototype.linha = function (x1, y1, x2, y2, cor, espessura) {
    this.conteudo.push(
      this.cor(cor || [220, 224, 230]) + ' RG ' + num(espessura || 0.6) + ' w ' +
      num(x1) + ' ' + num(this._y(y1)) + ' m ' + num(x2) + ' ' + num(this._y(y2)) + ' l S'
    );
    return this;
  };

  Documento.prototype.larguraTexto = larguraTexto;
  Documento.prototype.quebrarLinhas = quebrarLinhas;
  Documento.prototype.truncar = truncar;

  /* ------------------------------------------------------- serialização */

  Documento.prototype.blob = function () {
    var bytes = [];
    var offsets = [];

    function escrever(s) {
      for (var i = 0; i < s.length; i++) bytes.push(s.charCodeAt(i) & 0xff);
    }
    function objeto(numObj, corpo) {
      offsets[numObj] = bytes.length;
      escrever(numObj + ' 0 obj\n' + corpo + '\nendobj\n');
    }

    var nPaginas = this.paginas.length;
    // 1 Catalog | 2 Pages | 3 F1 | 4 F2 | 5..(5+n-1) páginas | depois conteúdos
    var idPrimeiraPagina = 5;
    var idPrimeiroConteudo = idPrimeiraPagina + nPaginas;
    var totalObjetos = idPrimeiroConteudo + nPaginas - 1;

    escrever('%PDF-1.4\n%\xE2\xE3\xCF\xD3\n');

    objeto(1, '<< /Type /Catalog /Pages 2 0 R >>');

    var kids = [];
    for (var p = 0; p < nPaginas; p++) kids.push((idPrimeiraPagina + p) + ' 0 R');
    objeto(2, '<< /Type /Pages /Count ' + nPaginas + ' /Kids [' + kids.join(' ') + '] >>');

    objeto(3, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    objeto(4, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');

    for (p = 0; p < nPaginas; p++) {
      objeto(idPrimeiraPagina + p,
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' + num(this.largura) + ' ' +
        num(this.altura) + '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> ' +
        '/Contents ' + (idPrimeiroConteudo + p) + ' 0 R >>');
    }

    for (p = 0; p < nPaginas; p++) {
      var fluxo = this.paginas[p].join('\n');
      objeto(idPrimeiroConteudo + p,
        '<< /Length ' + fluxo.length + ' >>\nstream\n' + fluxo + '\nendstream');
    }

    var inicioXref = bytes.length;
    escrever('xref\n0 ' + (totalObjetos + 1) + '\n');
    escrever('0000000000 65535 f \n');
    for (var i = 1; i <= totalObjetos; i++) {
      escrever(('0000000000' + (offsets[i] || 0)).slice(-10) + ' 00000 n \n');
    }
    escrever('trailer\n<< /Size ' + (totalObjetos + 1) + ' /Root 1 0 R >>\n' +
      'startxref\n' + inicioXref + '\n%%EOF\n');

    return new Blob([new Uint8Array(bytes)], { type: 'application/pdf' });
  };

  global.PDF = {
    Documento: Documento,
    criar: function (op) { return new Documento(op); },
    larguraTexto: larguraTexto,
    quebrarLinhas: quebrarLinhas,
    truncar: truncar
  };
})(window);
