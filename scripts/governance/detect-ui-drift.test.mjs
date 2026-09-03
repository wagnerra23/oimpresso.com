#!/usr/bin/env node
// @ts-check
// SELF-TEST — prova que detect-ui-drift.mjs MORDE e LIBERA (contrato, não implementação):
//   (a) .tsx mudou + NADA declarado                     → FLAG          (morde)
//   (b) + divergence_from_blueprint com razão real      → CLEARED       (libera: desvio)
//   (c) + related_prototype mudou pra protótipo real    → CLEARED       (libera: ancoragem, não aplicação)
//   (d) + entrada SYNC_LOG citando a tela               → CLEARED       (libera: sync log)
//   (e) divergence "none" no diff                       → FLAG          (semântico: placeholder não conta)
//   (f) related_prototype n/a → n/a                     → FLAG          (semântico: n/a→n/a não é aplicar)
//   (g) linha VELHA de desvio (fora do diff)            → FLAG          (freshness: charterDiff vazio não limpa)
//   (h) SYNC_LOG cita OUTRA tela                        → FLAG          (não casa token)
//   (i) .tsx do NUCLEO entra na selecao                → selecionado  (nao-regressao)
//   (j) .tsx do MODULO DONO entra na selecao           → selecionado  (a cegueira que isto mata)
//   (k) .tsx fora de raiz de Pages / nao-.tsx          → ignorado     (controle negativo)
//   (l) tokens da tela = NAMESPACE nas 2 raizes        → casa SYNC_LOG (ponta a ponta)
//
// Puro: exercita classifyTela() com fixtures em memória (sem git, sem fs). Contrato ancorado
// em RESPEITAR-PROTOTIPO.md + proibicoes §5 (L-24 presença≠correção), NÃO na implementação.
// Rodar: node scripts/governance/detect-ui-drift.test.mjs — exit 0 = passa.

import { classifyTela, isNoneReason, isRealPrototype, selecionarPagesTsx, telaTokensFor } from './detect-ui-drift.mjs';

let fails = 0;
const check = (name, cond, extra = '') => {
  console.log(`${cond ? '[OK]' : '[FAIL]'} ${name}${cond ? '' : '  ← ' + extra}`);
  if (!cond) fails++;
};

const TOK = ['Sells/Index', 'Pages/Sells/Index'];

// (a) morde: mudança sem nada declarado
check('(a) tsx mudou, nada declarado → FLAG',
  classifyTela({ charterDiff: '', syncLogAdded: [], telaTokens: TOK }).estado === 'FLAG');

// (b) libera: desvio declarado com razão real
check('(b) +divergence_from_blueprint razão real → CLEARED',
  classifyTela({
    charterDiff: '+divergence_from_blueprint: "cliente pediu densidade maior na lista"',
    telaTokens: TOK,
  }).estado === 'CLEARED');

// (c) libera: related_prototype mudou pra protótipo real
const anchored = classifyTela({
    charterDiff: [
      '-related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)',
      '+related_prototype: prototipo-ui/cowork/vendas-page.jsx',
    ].join('\n'),
    telaTokens: TOK,
  });
check('(c) related_prototype n/a → vendas-page.jsx → CLEARED como ancoragem',
  anchored.estado === 'CLEARED' && /ancorada/.test(anchored.motivo) && !/protótipo aplicado/.test(anchored.motivo),
  anchored.motivo);

// (d) libera: SYNC_LOG cita a tela
check('(d) SYNC_LOG novo cita Sells/Index → CLEARED',
  classifyTela({
    charterDiff: '',
    syncLogAdded: ['+2026-07-12 10:00 [W2] merged PR #999 Sells/Index (mwart-from-cowork)'],
    telaTokens: TOK,
  }).estado === 'CLEARED');

// (e) semântico: divergence "none" NÃO limpa
check('(e) +divergence_from_blueprint: "none" → FLAG (placeholder não conta)',
  classifyTela({ charterDiff: '+divergence_from_blueprint: "none"', telaTokens: TOK }).estado === 'FLAG');

// (f) semântico: related_prototype n/a → n/a NÃO limpa
check('(f) related_prototype n/a → n/a → FLAG',
  classifyTela({
    charterDiff: [
      '-related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)',
      '+related_prototype: n/a (herda PT-02 Form-Drawer; segue o Padrão de Tela)',
    ].join('\n'),
    telaTokens: TOK,
  }).estado === 'FLAG');

// (g) freshness: linha velha (não tocada no PR = charterDiff vazio) NÃO limpa
check('(g) desvio velho fora do diff → FLAG (freshness)',
  classifyTela({ charterDiff: '', syncLogAdded: [], telaTokens: TOK }).estado === 'FLAG');

// (h) SYNC_LOG cita OUTRA tela → não casa
check('(h) SYNC_LOG cita Compras/Index (outra tela) → FLAG',
  classifyTela({
    charterDiff: '',
    syncLogAdded: ['+2026-07-12 10:00 [W2] merged PR #998 Compras/Index'],
    telaTokens: TOK,
  }).estado === 'FLAG');

// ── RAIZ: a tela mora no nucleo OU no modulo dono. O runner selecionava so a primeira,
//    entao um PR que so tocasse Modules/ saia "Nenhuma .tsx de tela tocada. OK." — verde
//    sem ter medido nada (proibicoes §5 · LC-11 presence/cegueira). Estes casos mordem a
//    funcao REAL do runner (selecionarPagesTsx / telaTokensFor), nunca uma copia local.
const NUCLEO = 'resources/js/Pages/Sells/Index.tsx';
const MODULO = 'Modules/Forja/Resources/js/Pages/Forja/Trabalho/Index.tsx';

// (i) nao-regressao: o nucleo continua entrando
check('(i) .tsx do nucleo entra na selecao',
  selecionarPagesTsx([NUCLEO]).length === 1);

// (j) BITE: o modulo dono passa a entrar — este e o caso que ficava invisivel
check('(j) .tsx do modulo dono entra na selecao (a cegueira)',
  selecionarPagesTsx([MODULO]).length === 1,
  JSON.stringify(selecionarPagesTsx([MODULO])));

// (k) controle negativo: fora de raiz de Pages, ou nao-.tsx, fica de fora
check('(k) fora de raiz de Pages / nao-.tsx → ignorado',
  selecionarPagesTsx([
    'resources/js/Components/ui/Button.tsx',
    'Modules/Forja/Http/Controllers/X.php',
    'Modules/Forja/Resources/js/Pages/Forja/Trabalho/Index.charter.md',
  ]).length === 0);

// (l) ponta a ponta: o token da tela e o NAMESPACE nas duas raizes, entao o SYNC_LOG
//     que cita "Forja/Trabalho/Index" limpa a tela do modulo. Antes, o token era o
//     caminho inteiro (Modules/Forja/Resources/...) e NUNCA casava — flag eterna.
const tokModulo = telaTokensFor(MODULO);
check('(l) token do modulo = namespace (nao o caminho inteiro)',
  tokModulo[0] === 'Forja/Trabalho/Index' && !tokModulo.some((t) => t.includes('Modules/')),
  JSON.stringify(tokModulo));
check('(l) SYNC_LOG citando o namespace limpa a tela do modulo → CLEARED',
  classifyTela({
    charterDiff: '',
    syncLogAdded: ['+2026-09-03 10:00 [C] merged PR #1 Forja/Trabalho/Index (paridade §11)'],
    telaTokens: tokModulo,
  }).estado === 'CLEARED');

// helpers semânticos (contrato de baixo nível)
check('isNoneReason("none") = true', isNoneReason('none') === true);
check('isNoneReason("n/a (herda PT-01)") = true', isNoneReason('n/a (herda PT-01)') === true);
check('isNoneReason("cliente pediu X") = false', isNoneReason('cliente pediu X') === false);
check('isRealPrototype("prototipo-ui/cowork/vendas-page.jsx") = true',
  isRealPrototype('prototipo-ui/cowork/vendas-page.jsx') === true);
check('isRealPrototype("n/a (herda PT-01)") = false', isRealPrototype('n/a (herda PT-01)') === false);

console.log('');
if (fails) { console.error(`✗ ${fails} caso(s) falharam.`); process.exit(1); }
console.log('✓ detect-ui-drift: morde e libera — todos os casos passaram.');
