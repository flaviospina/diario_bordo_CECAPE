/*
 * Testes do motor de previsão (util.js + schedule.js).
 * Não exige dependências: node tests/motor.test.js
 */
const fs = require('fs');
const path = require('path');
const base = path.join(__dirname, '..', 'assets', 'js') + path.sep;

global.window = global;
eval(fs.readFileSync(base + 'util.js', 'utf8'));
eval(fs.readFileSync(base + 'schedule.js', 'utf8'));

const U = global.Util, S = global.Schedule;
let falhas = 0, total = 0;
function ok(nome, cond, extra) {
  total++;
  if (cond) console.log('  ✓ ' + nome);
  else { falhas++; console.log('  ✗ ' + nome + (extra ? '  → ' + extra : '')); }
}

const J = S.JORNADA_PADRAO; // seg-sex 08-18 com pausa 12-13 (9h úteis/dia)

console.log('\n[janelas do dia]');
ok('segunda tem 2 janelas', S.janelasDoDia(new Date(2025, 4, 5), J).length === 2);
ok('domingo não é dia útil', S.janelasDoDia(new Date(2025, 4, 4), J).length === 0);
ok('modo contínuo cobre 24h',
  S.janelasDoDia(new Date(2025, 4, 4), { modo: 'continuo' })[0][1] === 1440);

console.log('\n[próximo instante útil]');
ok('07:00 de segunda → 08:00',
  U.toInput(S.proximoInstanteUtil(new Date(2025, 4, 5, 7, 0), J)) === '2025-05-05T08:00');
ok('12:30 (almoço) → 13:00',
  U.toInput(S.proximoInstanteUtil(new Date(2025, 4, 5, 12, 30), J)) === '2025-05-05T13:00');
ok('19:00 de sexta → 08:00 de segunda',
  U.toInput(S.proximoInstanteUtil(new Date(2025, 4, 9, 19, 0), J)) === '2025-05-12T08:00');
ok('sábado 10:00 → segunda 08:00',
  U.toInput(S.proximoInstanteUtil(new Date(2025, 4, 10, 10, 0), J)) === '2025-05-12T08:00');
ok('dentro do expediente não muda',
  U.toInput(S.proximoInstanteUtil(new Date(2025, 4, 5, 9, 30), J)) === '2025-05-05T09:30');

console.log('\n[soma de minutos úteis]');
ok('08:00 + 2h = 10:00',
  U.toInput(S.somarMinutosUteis(new Date(2025, 4, 5, 8, 0), 120, J)) === '2025-05-05T10:00');
ok('11:00 + 2h pula o almoço → 14:00',
  U.toInput(S.somarMinutosUteis(new Date(2025, 4, 5, 11, 0), 120, J)) === '2025-05-05T14:00');
ok('08:00 + 9h termina às 18:00 (dia cheio)',
  U.toInput(S.somarMinutosUteis(new Date(2025, 4, 5, 8, 0), 540, J)) === '2025-05-05T18:00');
ok('08:00 + 10h transborda para terça 09:00',
  U.toInput(S.somarMinutosUteis(new Date(2025, 4, 5, 8, 0), 600, J)) === '2025-05-06T09:00');
ok('sexta 17:00 + 2h → segunda 09:00',
  U.toInput(S.somarMinutosUteis(new Date(2025, 4, 9, 17, 0), 120, J)) === '2025-05-12T09:00');
ok('modo contínuo ignora expediente',
  U.toInput(S.somarMinutosUteis(new Date(2025, 4, 10, 23, 0), 120, { modo: 'continuo' }))
  === '2025-05-11T01:00');

console.log('\n[minutos úteis entre instantes]');
ok('um dia inteiro = 540 min',
  S.minutosUteisEntre(new Date(2025, 4, 5, 8, 0), new Date(2025, 4, 5, 18, 0), J) === 540,
  S.minutosUteisEntre(new Date(2025, 4, 5, 8, 0), new Date(2025, 4, 5, 18, 0), J));
ok('semana inteira = 2700 min',
  S.minutosUteisEntre(new Date(2025, 4, 5, 8, 0), new Date(2025, 4, 9, 18, 0), J) === 2700,
  S.minutosUteisEntre(new Date(2025, 4, 5, 8, 0), new Date(2025, 4, 9, 18, 0), J));
ok('fim anterior ao início = 0',
  S.minutosUteisEntre(new Date(2025, 4, 9), new Date(2025, 4, 5), J) === 0);
ok('ida e volta: soma → diferença',
  S.minutosUteisEntre(new Date(2025, 4, 5, 8, 0),
    S.somarMinutosUteis(new Date(2025, 4, 5, 8, 0), 733, J), J) === 733);

console.log('\n[planejamento de fases]');
const plano = S.planejar({
  inicio: new Date(2025, 4, 5, 8, 0),
  duracaoMin: 240,
  fases: [
    { nome: 'Planejamento', peso: 25 },
    { nome: 'Execução', peso: 50 },
    { nome: 'Fechamento', peso: 25 }
  ],
  distribuir: 'peso',
  jornada: J
});
ok('3 fases geradas', plano.fases.length === 3);
ok('soma das durações = total', plano.duracaoMin === 240);
ok('rateio 25/50/25 = 60/120/60',
  plano.fases.map(f => f.duracaoMin).join(',') === '60,120,60',
  plano.fases.map(f => f.duracaoMin).join(','));
ok('fase 1 começa às 08:00', plano.fases[0].inicioPrevisto === '2025-05-05T08:00');
ok('fases encadeadas sem lacuna',
  plano.fases.every((f, i) => i === 0 || f.inicioPrevisto === plano.fases[i - 1].fimPrevisto));
ok('4h a partir das 08:00 terminam às 12:00 (início da pausa)',
  plano.fases[2].fimPrevisto === '2025-05-05T12:00', plano.fases[2].fimPrevisto);

const atravessa = S.planejar({
  inicio: new Date(2025, 4, 5, 10, 0), duracaoMin: 240,
  fases: [{ nome: 'A', peso: 50 }, { nome: 'B', peso: 50 }],
  distribuir: 'peso', jornada: J
});
ok('atividade que cruza o almoço termina às 15:00',
  atravessa.fases[1].fimPrevisto === '2025-05-05T15:00', atravessa.fases[1].fimPrevisto);
ok('fase iniciada às 12:00 é empurrada para 13:00',
  atravessa.fases[1].inicioPrevisto === '2025-05-05T13:00', atravessa.fases[1].inicioPrevisto);

const arredonda = S.planejar({
  inicio: new Date(2025, 4, 5, 8, 0), duracaoMin: 100,
  fases: [{ nome: 'A', peso: 33 }, { nome: 'B', peso: 33 }, { nome: 'C', peso: 34 }],
  distribuir: 'peso', jornada: J
});
ok('arredondamento preserva o total (100 min)',
  arredonda.fases.reduce((s, f) => s + f.duracaoMin, 0) === 100,
  arredonda.fases.map(f => f.duracaoMin).join(','));

const fixa = S.planejar({
  inicio: new Date(2025, 4, 5, 11, 0),
  fases: [{ nome: 'A', duracaoMin: 90 }, { nome: 'B', duracaoMin: 30 }],
  distribuir: 'fixa', jornada: J
});
ok('duração fixa: A vai 11:00→13:30 (pulando almoço)',
  fixa.fases[0].fimPrevisto === '2025-05-05T13:30', fixa.fases[0].fimPrevisto);
ok('duração fixa: B termina 14:00', fixa.fases[1].fimPrevisto === '2025-05-05T14:00');

const semPeso = S.planejar({
  inicio: new Date(2025, 4, 5, 8, 0), duracaoMin: 120,
  fases: [{ nome: 'A', peso: 0 }, { nome: 'B', peso: 0 }],
  distribuir: 'peso', jornada: J
});
ok('sem pesos válidos divide igualmente',
  semPeso.fases[0].duracaoMin === 60 && semPeso.fases[1].duracaoMin === 60,
  semPeso.fases.map(f => f.duracaoMin).join(','));

console.log('\n[formatação]');
ok('fmtDuracao(90) = 1h 30min', U.fmtDuracao(90) === '1h 30min', U.fmtDuracao(90));
ok('fmtDuracao(0) = 0min', U.fmtDuracao(0) === '0min');
ok('fmtDuracao(1500) = 1d 1h', U.fmtDuracao(1500) === '1d 1h', U.fmtDuracao(1500));
ok('parseLocal usa fuso local',
  U.parseLocal('2025-05-05T08:30').getHours() === 8);
ok('normalizar remove acentos',
  U.normalizar('Manutenção Preventiva') === 'manutencao preventiva',
  U.normalizar('Manutenção Preventiva'));
ok('escapeHtml protege tags',
  U.escapeHtml('<b>"x"</b>') === '&lt;b&gt;&quot;x&quot;&lt;/b&gt;');

console.log('\n' + (falhas ? '✗ ' + falhas + ' de ' + total + ' falharam'
  : '✓ todos os ' + total + ' testes passaram') + '\n');
process.exit(falhas ? 1 : 0);
