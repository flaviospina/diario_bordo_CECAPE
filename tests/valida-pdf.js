/* Validação estrutural e de layout do PDF gerado pelo assets/js/pdf.js */
const fs = require('fs');
const arquivo = process.argv[2];
const buf = fs.readFileSync(arquivo);
const txt = buf.toString('latin1');

let falhas = 0, total = 0;
const ok = (nome, cond, extra) => {
  total++;
  console.log((cond ? '  ✓ ' : '  ✗ ') + nome + (cond ? '' : '  → ' + extra));
  if (!cond) falhas++;
};

console.log('\n[estrutura do arquivo] ' + arquivo + '  (' + buf.length + ' bytes)');
ok('cabeçalho %PDF-1.4', txt.startsWith('%PDF-1.4'));
ok('termina com %%EOF', txt.trimEnd().endsWith('%%EOF'));

// --- xref: cada offset deve apontar exatamente para "N 0 obj"
const mStart = txt.match(/startxref\s+(\d+)/);
ok('startxref presente', !!mStart);
const inicioXref = +mStart[1];
ok('startxref aponta para a tabela xref', txt.slice(inicioXref, inicioXref + 4) === 'xref',
  JSON.stringify(txt.slice(inicioXref, inicioXref + 12)));

const bloco = txt.slice(inicioXref);
const cab = bloco.match(/xref\s+0\s+(\d+)/);
const nObjetos = +cab[1];
const entradas = bloco.match(/^(\d{10}) (\d{5}) ([nf])\s*$/gm) || [];
ok('xref declara ' + nObjetos + ' entradas e lista ' + entradas.length,
  entradas.length === nObjetos, entradas.length + ' != ' + nObjetos);

let offsetsOk = true, detalhe = '';
entradas.forEach((linha, i) => {
  if (i === 0) return;                       // objeto livre
  const off = parseInt(linha.slice(0, 10), 10);
  const esperado = i + ' 0 obj';
  if (txt.slice(off, off + esperado.length) !== esperado) {
    offsetsOk = false;
    detalhe += ' obj' + i + '@' + off + '="' + txt.slice(off, off + 12).replace(/\n/g, '\\n') + '"';
  }
});
ok('todos os offsets do xref apontam para o objeto correto', offsetsOk, detalhe);

const trailer = txt.match(/trailer\s*<<\s*\/Size (\d+) \/Root (\d+) 0 R/);
ok('trailer com Size e Root', !!trailer);
ok('Size do trailer bate com o xref', +trailer[1] === nObjetos, trailer[1] + ' != ' + nObjetos);

// --- páginas
const count = +txt.match(/\/Type \/Pages \/Count (\d+)/)[1];
const paginas = (txt.match(/\/Type \/Page[^s]/g) || []).length;
ok('/Count (' + count + ') igual ao número de objetos Page (' + paginas + ')', count === paginas);
const kids = txt.match(/\/Kids \[([^\]]+)\]/)[1].trim().split(/\s+0 R\s*/).filter(Boolean);
ok('Kids lista todas as páginas', kids.length === count, kids.length + ' != ' + count);

// --- streams: /Length deve bater com o conteúdo real
let streamsOk = true, det2 = '';
const re = /<< \/Length (\d+) >>\s*stream\n([\s\S]*?)\nendstream/g;
let m, nStreams = 0;
while ((m = re.exec(txt))) {
  nStreams++;
  if (m[2].length !== +m[1]) { streamsOk = false; det2 += ' stream' + nStreams + ': ' + m[2].length + '!=' + m[1]; }
}
ok('encontrou ' + nStreams + ' fluxos de conteúdo', nStreams === count);
ok('/Length de cada fluxo confere com o conteúdo', streamsOk, det2);

// --- fontes e recursos
ok('fontes Helvetica e Helvetica-Bold declaradas',
  txt.includes('/BaseFont /Helvetica ') && txt.includes('/BaseFont /Helvetica-Bold'));
ok('codificação WinAnsi (acentos)', (txt.match(/WinAnsiEncoding/g) || []).length === 2);
ok('MediaBox em paisagem A4', txt.includes('/MediaBox [0 0 841.89 595.28]'));

// --- layout: nenhum texto pode ser posicionado fora da página
const larg = 841.89, alt = 595.28;
let fora = [], textos = 0;
const reTm = /1 0 0 1 (-?[\d.]+) (-?[\d.]+) Tm \((.*?)\) Tj/g;
while ((m = reTm.exec(txt))) {
  textos++;
  const x = +m[1], y = +m[2];
  if (x < 0 || x > larg - 5 || y < 5 || y > alt) fora.push(m[3].slice(0, 24) + '@(' + x + ',' + y + ')');
}
ok('gerou ' + textos + ' blocos de texto', textos > 40, String(textos));
ok('nenhum texto posicionado fora da página', fora.length === 0, fora.slice(0, 5).join(' | '));

// --- conteúdo esperado
const conteudo = txt.replace(/\\\(/g, '(').replace(/\\\)/g, ')');
['CECAPE', 'Di\xe1rio de Bordo', 'Fase', 'In\xedcio previsto', 'T\xe9rmino previsto',
  'Situa\xe7\xe3o', 'Filtros:', 'P\xe1gina 1 de'].forEach(t => {
    ok('contém "' + t + '"', conteudo.includes(t));
  });
ok('acentuados preservados como bytes Latin-1 (não "?")',
  !/Manuten\?\?o|Reuni\?o/.test(conteudo));

console.log('\n' + (falhas ? '✗ ' + falhas + ' de ' + total + ' falharam'
  : '✓ todas as ' + total + ' verificações do PDF passaram') + '\n');
process.exit(falhas ? 1 : 0);
