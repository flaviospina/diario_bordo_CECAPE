/* =========================================================================
 * export.js — exportação para XLS (Excel), PDF e impressão
 * ========================================================================= */
(function (global) {
  'use strict';

  var U = global.Util;
  var Store = global.Store;
  var Export = {};

  function rotuloStatus(s) {
    return (Store.STATUS[s] || { rotulo: s }).rotulo;
  }

  function nomeArquivo(ext) {
    var d = new Date();
    return 'diario-de-bordo_' + d.getFullYear() + U.pad2(d.getMonth() + 1) +
      U.pad2(d.getDate()) + '-' + U.pad2(d.getHours()) + U.pad2(d.getMinutes()) + '.' + ext;
  }

  /** Linha de texto descrevendo os filtros ativos no momento da exportação. */
  Export.descreverFiltros = function (filtros) {
    var partes = [];
    if (filtros.de) partes.push('de ' + U.fmtData(filtros.de));
    if (filtros.ate) partes.push('até ' + U.fmtData(filtros.ate));
    if (filtros.responsavel) partes.push('responsável: ' + filtros.responsavel);
    if (filtros.categoria) partes.push('categoria: ' + filtros.categoria);
    if (filtros.status) partes.push('situação: ' + rotuloStatus(filtros.status));
    if (filtros.busca) partes.push('busca: "' + filtros.busca + '"');
    return partes.length ? partes.join(' · ') : 'Todos os registros';
  };

  /* ------------------------------------------------------------------ XLS */

  function escXml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&apos;');
  }

  function celulaTexto(valor, estilo) {
    return '<Cell' + (estilo ? ' ss:StyleID="' + estilo + '"' : '') +
      '><Data ss:Type="String">' + escXml(valor) + '</Data></Cell>';
  }

  function celulaNumero(valor, estilo) {
    if (valor == null || valor === '' || isNaN(valor)) return celulaTexto('', estilo);
    return '<Cell' + (estilo ? ' ss:StyleID="' + estilo + '"' : '') +
      '><Data ss:Type="Number">' + valor + '</Data></Cell>';
  }

  function celulaData(valor) {
    var d = U.parseLocal(valor);
    if (!d) return celulaTexto('');
    var iso = d.getFullYear() + '-' + U.pad2(d.getMonth() + 1) + '-' + U.pad2(d.getDate()) +
      'T' + U.pad2(d.getHours()) + ':' + U.pad2(d.getMinutes()) + ':00.000';
    return '<Cell ss:StyleID="sData"><Data ss:Type="DateTime">' + iso + '</Data></Cell>';
  }

  function planilha(nome, colunas, linhas) {
    var larguras = colunas.map(function (c) {
      return '<Column ss:AutoFitWidth="0" ss:Width="' + (c.largura || 90) + '"/>';
    }).join('');
    var cabecalho = '<Row ss:StyleID="sCab">' + colunas.map(function (c) {
      return celulaTexto(c.titulo);
    }).join('') + '</Row>';
    return '<Worksheet ss:Name="' + escXml(nome) + '"><Table>' + larguras +
      cabecalho + linhas.join('') + '</Table>' +
      '<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">' +
      '<FreezePanes/><FrozenNoSplit/><SplitHorizontal>1</SplitHorizontal>' +
      '<TopRowBottomPane>1</TopRowBottomPane><ActivePane>2</ActivePane>' +
      '</WorksheetOptions></Worksheet>';
  }

  Export.paraXLS = function (atividades, meta) {
    meta = meta || {};

    var colAtv = [
      { titulo: 'Atividade', largura: 220 },
      { titulo: 'Responsável', largura: 120 },
      { titulo: 'Categoria', largura: 90 },
      { titulo: 'Local', largura: 110 },
      { titulo: 'Situação', largura: 85 },
      { titulo: 'Progresso (%)', largura: 80 },
      { titulo: 'Início previsto', largura: 110 },
      { titulo: 'Término previsto', largura: 110 },
      { titulo: 'Início real', largura: 110 },
      { titulo: 'Término real', largura: 110 },
      { titulo: 'Duração prevista (min)', largura: 110 },
      { titulo: 'Duração realizada (min)', largura: 115 },
      { titulo: 'Desvio (min)', largura: 85 },
      { titulo: 'Descrição', largura: 260 }
    ];

    var linhasAtv = atividades.map(function (a) {
      var desvio = Store.desvio(a);
      return '<Row>' +
        celulaTexto(a.titulo) +
        celulaTexto(a.responsavel) +
        celulaTexto(a.categoria) +
        celulaTexto(a.local) +
        celulaTexto(rotuloStatus(Store.statusAtividade(a))) +
        celulaNumero(Store.progresso(a)) +
        celulaData(a.inicioPrevisto) +
        celulaData(a.fimPrevisto) +
        celulaData(a.inicioReal) +
        celulaData(a.fimReal) +
        celulaNumero(Store.duracaoPrevista(a)) +
        celulaNumero(Store.duracaoRealizada(a) || '') +
        celulaNumero(desvio == null ? '' : desvio) +
        celulaTexto(a.descricao) +
        '</Row>';
    });

    var colFases = [
      { titulo: 'Atividade', largura: 220 },
      { titulo: 'Nº', largura: 35 },
      { titulo: 'Fase', largura: 180 },
      { titulo: 'Peso (%)', largura: 60 },
      { titulo: 'Duração prevista (min)', largura: 110 },
      { titulo: 'Início previsto', largura: 110 },
      { titulo: 'Término previsto', largura: 110 },
      { titulo: 'Início real', largura: 110 },
      { titulo: 'Término real', largura: 110 },
      { titulo: 'Duração real (min)', largura: 100 },
      { titulo: 'Situação', largura: 85 },
      { titulo: 'Observação', largura: 240 }
    ];

    var linhasFases = [];
    atividades.forEach(function (a) {
      (a.fases || []).forEach(function (f, i) {
        var real = (f.inicioReal && f.fimReal) ? U.diffMin(f.inicioReal, f.fimReal) : '';
        linhasFases.push('<Row>' +
          celulaTexto(a.titulo) +
          celulaNumero(i + 1) +
          celulaTexto(f.nome) +
          celulaNumero(f.peso || '') +
          celulaNumero(f.duracaoMin || '') +
          celulaData(f.inicioPrevisto) +
          celulaData(f.fimPrevisto) +
          celulaData(f.inicioReal) +
          celulaData(f.fimReal) +
          celulaNumero(real === null ? '' : real) +
          celulaTexto(rotuloStatus(Store.statusFase(f))) +
          celulaTexto(f.observacao) +
          '</Row>');
      });
    });

    var infoLinhas = [
      '<Row>' + celulaTexto('Organização', 'sNegrito') + celulaTexto(meta.organizacao || 'CECAPE') + '</Row>',
      '<Row>' + celulaTexto('Relatório', 'sNegrito') + celulaTexto(meta.subtitulo || 'Diário de Bordo') + '</Row>',
      '<Row>' + celulaTexto('Gerado em', 'sNegrito') + celulaTexto(U.fmtDataHora(new Date())) + '</Row>',
      '<Row>' + celulaTexto('Filtros', 'sNegrito') + celulaTexto(meta.filtros || 'Todos os registros') + '</Row>',
      '<Row>' + celulaTexto('Total de atividades', 'sNegrito') + celulaNumero(atividades.length) + '</Row>',
      '<Row>' + celulaTexto('Total de fases', 'sNegrito') + celulaNumero(linhasFases.length) + '</Row>'
    ];

    var xml = '<?xml version="1.0" encoding="UTF-8"?>\n' +
      '<?mso-application progid="Excel.Sheet"?>\n' +
      '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' +
      ' xmlns:o="urn:schemas-microsoft-com:office:office"' +
      ' xmlns:x="urn:schemas-microsoft-com:office:excel"' +
      ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' +
      ' xmlns:html="http://www.w3.org/TR/REC-html40">' +
      '<Styles>' +
      '<Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Top"/>' +
      '<Font ss:FontName="Calibri" ss:Size="11"/></Style>' +
      '<Style ss:ID="sCab"><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/>' +
      '<Interior ss:Color="#1F3A5F" ss:Pattern="Solid"/>' +
      '<Alignment ss:Vertical="Center" ss:WrapText="1"/></Style>' +
      '<Style ss:ID="sNegrito"><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1"/></Style>' +
      '<Style ss:ID="sData"><NumberFormat ss:Format="dd/mm/yyyy\\ hh:mm"/></Style>' +
      '</Styles>' +
      planilha('Atividades', colAtv, linhasAtv) +
      planilha('Fases', colFases, linhasFases) +
      planilha('Informações', [{ titulo: 'Campo', largura: 150 }, { titulo: 'Valor', largura: 320 }], infoLinhas) +
      '</Workbook>';

    // BOM para o Excel reconhecer os acentos corretamente
    var blob = new Blob(['﻿', xml], { type: 'application/vnd.ms-excel;charset=utf-8' });
    U.baixarArquivo(blob, nomeArquivo('xls'));
  };

  /* ------------------------------------------------------------------ PDF */

  var CINZA_TEXTO = [31, 41, 55];
  var CINZA_SUAVE = [107, 114, 128];
  var AZUL = [31, 58, 95];
  var LINHA = [222, 226, 232];
  var FUNDO_GRUPO = [241, 245, 249];

  Export.paraPDF = function (atividades, meta) {
    meta = meta || {};
    var doc = global.PDF.criar({ orientacao: 'paisagem', margem: 34 });
    var M = doc.margem;
    var larguraUtil = doc.largura - M * 2;
    var y = M;

    var colunas = [
      { titulo: 'Fase', p: 0.26 },
      { titulo: 'Início previsto', p: 0.135 },
      { titulo: 'Término previsto', p: 0.135 },
      { titulo: 'Início real', p: 0.135 },
      { titulo: 'Término real', p: 0.135 },
      { titulo: 'Duração', p: 0.085 },
      { titulo: 'Situação', p: 0.115 }
    ];
    var x0 = M;
    colunas.forEach(function (c) { c.largura = larguraUtil * c.p; });

    function desenharCabecalhoPagina() {
      doc.retangulo(0, 0, doc.largura, 54, AZUL);
      doc.texto(meta.organizacao || 'CECAPE', M, 20, { tamanho: 14, negrito: true, cor: [255, 255, 255] });
      doc.texto(meta.subtitulo || 'Diário de Bordo — Registro de Atividades', M, 38,
        { tamanho: 9.5, cor: [203, 213, 225] });
      doc.texto('Gerado em ' + U.fmtDataHora(new Date()), M, 38,
        { tamanho: 9, cor: [203, 213, 225], largura: larguraUtil, alinhamento: 'direita' });
      y = 70;
      doc.texto('Filtros: ' + (meta.filtros || 'Todos os registros'), M, y,
        { tamanho: 8.5, cor: CINZA_SUAVE });
      y += 16;
    }

    function desenharCabecalhoTabela() {
      doc.retangulo(M, y, larguraUtil, 18, [237, 242, 247]);
      var x = x0;
      colunas.forEach(function (c) {
        doc.texto(c.titulo, x + 5, y + 13, { tamanho: 8, negrito: true, cor: AZUL, largura: c.largura - 8 });
        x += c.largura;
      });
      y += 18;
    }

    function espacoRestante() { return doc.altura - M - 16 - y; }

    function novaPagina() {
      doc.novaPagina();
      desenharCabecalhoPagina();
    }

    desenharCabecalhoPagina();

    if (!atividades.length) {
      doc.texto('Nenhum registro encontrado para os filtros selecionados.', M, y + 14,
        { tamanho: 10, cor: CINZA_SUAVE });
    }

    atividades.forEach(function (a, idx) {
      var fases = a.fases || [];
      var alturaBloco = 34 + 18 + fases.length * 16 + 14;
      if (idx > 0 && espacoRestante() < Math.min(alturaBloco, 120)) novaPagina();

      // ---- bloco da atividade
      doc.retangulo(M, y, larguraUtil, 34, FUNDO_GRUPO);
      doc.texto(a.titulo || '(sem título)', M + 6, y + 14,
        { tamanho: 10.5, negrito: true, cor: CINZA_TEXTO, largura: larguraUtil * 0.62 });

      var status = Store.statusAtividade(a);
      doc.texto(rotuloStatus(status) + ' · ' + Store.progresso(a) + '% concluído',
        M, y + 14, { tamanho: 9, negrito: true, cor: AZUL, largura: larguraUtil - 6, alinhamento: 'direita' });

      var meta1 = [
        a.responsavel ? 'Responsável: ' + a.responsavel : null,
        a.categoria ? 'Categoria: ' + a.categoria : null,
        a.local ? 'Local: ' + a.local : null
      ].filter(Boolean).join('   |   ');
      doc.texto(meta1 || '—', M + 6, y + 28, { tamanho: 8, cor: CINZA_SUAVE, largura: larguraUtil * 0.62 });

      var periodo = U.fmtDataHora(a.inicioPrevisto) + '  a  ' + U.fmtDataHora(a.fimPrevisto) +
        '   ·   Previsto: ' + U.fmtDuracao(Store.duracaoPrevista(a));
      var real = Store.duracaoRealizada(a);
      if (real) periodo += '   ·   Realizado: ' + U.fmtDuracao(real);
      doc.texto(periodo, M, y + 28,
        { tamanho: 8, cor: CINZA_SUAVE, largura: larguraUtil - 6, alinhamento: 'direita' });
      y += 34;

      desenharCabecalhoTabela();

      if (!fases.length) {
        doc.texto('Sem fases cadastradas.', M + 6, y + 12, { tamanho: 8.5, cor: CINZA_SUAVE });
        y += 18;
      }

      fases.forEach(function (f, i) {
        if (espacoRestante() < 24) {
          novaPagina();
          doc.texto((a.titulo || '') + ' (continuação)', M, y + 10,
            { tamanho: 9, negrito: true, cor: CINZA_SUAVE, largura: larguraUtil });
          y += 18;
          desenharCabecalhoTabela();
        }
        if (i % 2 === 1) doc.retangulo(M, y, larguraUtil, 16, [250, 251, 252]);

        var dur = (f.inicioReal && f.fimReal) ? U.diffMin(f.inicioReal, f.fimReal) : f.duracaoMin;
        var valores = [
          (i + 1) + '. ' + (f.nome || ''),
          U.fmtDataHora(f.inicioPrevisto),
          U.fmtDataHora(f.fimPrevisto),
          f.inicioReal ? U.fmtDataHora(f.inicioReal) : '—',
          f.fimReal ? U.fmtDataHora(f.fimReal) : '—',
          U.fmtDuracao(dur),
          rotuloStatus(Store.statusFase(f))
        ];
        var x = x0;
        valores.forEach(function (v, ci) {
          doc.texto(v, x + 5, y + 11.5, {
            tamanho: 8.2,
            cor: ci === 0 ? CINZA_TEXTO : CINZA_SUAVE,
            negrito: ci === 0,
            largura: colunas[ci].largura - 8
          });
          x += colunas[ci].largura;
        });
        doc.linha(M, y + 16, M + larguraUtil, y + 16, LINHA, 0.4);
        y += 16;
      });

      if (a.descricao) {
        var linhas = global.PDF.quebrarLinhas(a.descricao, larguraUtil - 12, 8, false).slice(0, 3);
        y += 4;
        linhas.forEach(function (ln, i) {
          if (espacoRestante() < 16) novaPagina();
          doc.texto((i === 0 ? 'Observações: ' : '') + ln, M + 6, y + 9,
            { tamanho: 8, cor: CINZA_SUAVE, largura: larguraUtil - 12 });
          y += 11;
        });
      }
      y += 14;
    });

    // rodapé com numeração em todas as páginas
    var total = doc.totalPaginas();
    for (var p = 0; p < total; p++) {
      (function (pagina) {
        doc.naPagina(pagina, function () {
          var yRodape = doc.altura - 20;
          doc.linha(M, yRodape - 8, doc.largura - M, yRodape - 8, LINHA, 0.5);
          doc.texto((meta.organizacao || 'CECAPE') + ' · Diário de Bordo', M, yRodape,
            { tamanho: 7.5, cor: CINZA_SUAVE });
          doc.texto('Página ' + (pagina + 1) + ' de ' + total, M, yRodape,
            { tamanho: 7.5, cor: CINZA_SUAVE, largura: larguraUtil, alinhamento: 'direita' });
        });
      })(p);
    }

    U.baixarArquivo(doc.blob(), nomeArquivo('pdf'));
  };

  /* ------------------------------------------------------------ impressão */

  Export.paraHTMLImpressao = function (atividades, meta) {
    meta = meta || {};
    var e = U.escapeHtml;

    var blocos = atividades.map(function (a) {
      var fases = (a.fases || []).map(function (f, i) {
        var dur = (f.inicioReal && f.fimReal) ? U.diffMin(f.inicioReal, f.fimReal) : f.duracaoMin;
        return '<tr>' +
          '<td class="num">' + (i + 1) + '</td>' +
          '<td class="forte">' + e(f.nome) + '</td>' +
          '<td>' + e(U.fmtDataHora(f.inicioPrevisto)) + '</td>' +
          '<td>' + e(U.fmtDataHora(f.fimPrevisto)) + '</td>' +
          '<td>' + e(f.inicioReal ? U.fmtDataHora(f.inicioReal) : '—') + '</td>' +
          '<td>' + e(f.fimReal ? U.fmtDataHora(f.fimReal) : '—') + '</td>' +
          '<td>' + e(U.fmtDuracao(dur)) + '</td>' +
          '<td>' + e(rotuloStatus(Store.statusFase(f))) + '</td>' +
          '</tr>';
      }).join('');

      var real = Store.duracaoRealizada(a);
      return '<section class="atv">' +
        '<div class="atv-cab">' +
        '<h2>' + e(a.titulo || '(sem título)') + '</h2>' +
        '<span class="situacao">' + e(rotuloStatus(Store.statusAtividade(a))) +
        ' · ' + Store.progresso(a) + '%</span>' +
        '</div>' +
        '<div class="atv-meta">' +
        (a.responsavel ? '<span><b>Responsável:</b> ' + e(a.responsavel) + '</span>' : '') +
        (a.categoria ? '<span><b>Categoria:</b> ' + e(a.categoria) + '</span>' : '') +
        (a.local ? '<span><b>Local:</b> ' + e(a.local) + '</span>' : '') +
        '<span><b>Previsto:</b> ' + e(U.fmtDataHora(a.inicioPrevisto)) + ' a ' +
        e(U.fmtDataHora(a.fimPrevisto)) + ' (' + e(U.fmtDuracao(Store.duracaoPrevista(a))) + ')</span>' +
        (real ? '<span><b>Realizado:</b> ' + e(U.fmtDuracao(real)) + '</span>' : '') +
        '</div>' +
        (a.descricao ? '<p class="desc">' + e(a.descricao) + '</p>' : '') +
        '<table><thead><tr><th class="num">#</th><th>Fase</th><th>Início previsto</th>' +
        '<th>Término previsto</th><th>Início real</th><th>Término real</th>' +
        '<th>Duração</th><th>Situação</th></tr></thead><tbody>' +
        (fases || '<tr><td colspan="8" class="vazio">Sem fases cadastradas.</td></tr>') +
        '</tbody></table>' +
        '</section>';
    }).join('');

    return '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">' +
      '<title>' + e(meta.subtitulo || 'Diário de Bordo') + '</title><style>' +
      '@page{size:A4 landscape;margin:12mm}' +
      '*{box-sizing:border-box}' +
      'body{font:11px/1.45 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;' +
      'color:#1f2937;margin:0;padding:0}' +
      'header.doc{border-bottom:2px solid #1f3a5f;padding-bottom:10px;margin-bottom:14px}' +
      'header.doc h1{font-size:18px;margin:0 0 2px;color:#1f3a5f;letter-spacing:-.2px}' +
      'header.doc .sub{color:#6b7280;font-size:11px}' +
      'header.doc .info{margin-top:6px;color:#6b7280;font-size:10px;display:flex;' +
      'justify-content:space-between;gap:12px}' +
      '.atv{margin:0 0 16px;page-break-inside:avoid;break-inside:avoid}' +
      '.atv-cab{display:flex;justify-content:space-between;align-items:baseline;gap:12px;' +
      'background:#f1f5f9;padding:7px 10px;border-left:3px solid #1f3a5f}' +
      '.atv-cab h2{font-size:13px;margin:0}' +
      '.situacao{font-size:10px;font-weight:700;color:#1f3a5f;white-space:nowrap}' +
      '.atv-meta{display:flex;flex-wrap:wrap;gap:4px 18px;padding:6px 10px;color:#4b5563;font-size:10px}' +
      '.desc{margin:0 10px 6px;color:#4b5563;font-size:10px}' +
      'table{width:100%;border-collapse:collapse;margin-top:2px}' +
      'th{background:#eef2f7;color:#1f3a5f;font-size:9.5px;text-align:left;padding:5px 6px;' +
      'border-bottom:1px solid #cbd5e1;text-transform:uppercase;letter-spacing:.3px}' +
      'td{padding:5px 6px;border-bottom:1px solid #eceff3;font-size:10px;color:#4b5563}' +
      'td.forte{color:#1f2937;font-weight:600}' +
      '.num{width:24px;text-align:center}' +
      '.vazio{color:#9ca3af;font-style:italic;text-align:center}' +
      'footer.doc{margin-top:10px;border-top:1px solid #e5e7eb;padding-top:6px;' +
      'color:#9ca3af;font-size:9px;display:flex;justify-content:space-between}' +
      '@media print{.no-print{display:none}}' +
      '</style></head><body>' +
      '<header class="doc"><h1>' + e(meta.organizacao || 'CECAPE') + '</h1>' +
      '<div class="sub">' + e(meta.subtitulo || 'Diário de Bordo — Registro de Atividades') + '</div>' +
      '<div class="info"><span>Filtros: ' + e(meta.filtros || 'Todos os registros') + '</span>' +
      '<span>Gerado em ' + e(U.fmtDataHora(new Date())) + ' · ' + atividades.length +
      ' atividade(s)</span></div></header>' +
      (blocos || '<p class="vazio">Nenhum registro encontrado para os filtros selecionados.</p>') +
      '<footer class="doc"><span>' + e(meta.organizacao || 'CECAPE') +
      ' · Diário de Bordo</span><span>Documento gerado automaticamente</span></footer>' +
      '</body></html>';
  };

  Export.imprimir = function (atividades, meta) {
    var html = Export.paraHTMLImpressao(atividades, meta);
    var janela = window.open('', '_blank');
    if (!janela) {
      alert('O navegador bloqueou a janela de impressão. Permita pop-ups para este site e tente novamente.');
      return;
    }
    janela.document.open();
    janela.document.write(html);
    janela.document.close();
    janela.focus();
    setTimeout(function () {
      janela.print();
    }, 350);
  };

  global.Export = Export;
})(window);
