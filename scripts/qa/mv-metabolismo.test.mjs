#!/usr/bin/env node
// Self-test mv-metabolismo — prova as regras vs o CONTRATO (arte 2026-07-05 §3.4/§3.6),
// NÃO vs a implementação. Contrato ancorado:
//   §3.6 regra 2: tela verde+fresca (nota≥80, casos+charter, não-stale) PULA o ciclo
//   §3.6 regra 3: gate humano pendente → não empilha batch novo
//   §3.4: batimento — dinheiro_fiscal re-entra em 1d, vertical 3d, resto 7d
//   budget: corta a fila, nunca ultrapassa
// Roda: node scripts/qa/mv-metabolismo.test.mjs
import { verdeFresca, estadoBatches, modDevido, selecionaBatch, acaoProposta, fmScalar, BATIMENTO_DIAS } from './mv-metabolismo.mjs';

let fails = 0;
const check = (n, c, extra = '') => { console.log(`${c ? '[OK]' : '[FAIL]'} ${n}${c ? '' : '  → ' + extra}`); if (!c) fails++; };

const tela = (over) => ({ screen: 'X/A', mod: 'X', classe: 'resto', nota: 50, stale: false, casos: false, charter: false, prioridade: 100, ...over });

// 1. Verde+fresca pula (§3.6 r2): nota 85 + casos + charter + fresca → fora da fila.
{
  check('nota85+casos+charter fresca → pula', verdeFresca(tela({ nota: 85, casos: true, charter: true })) === true);
  check('nota85 SEM casos → NÃO pula (contrato-first)', verdeFresca(tela({ nota: 85, casos: false, charter: true })) === false);
  check('nota85 completa mas STALE → NÃO pula (frescor manda)', verdeFresca(tela({ nota: 85, casos: true, charter: true, stale: true })) === false);
  check('nota 79 → NÃO pula', verdeFresca(tela({ nota: 79, casos: true, charter: true })) === false);
}

// 2. Gate humano pendente (§3.6 r3): batch proposto OU aprovado bloqueia; executado/rejeitado libera.
{
  const b = (status) => [{ file: 'x.md', date: '2026-07-04', status, modulos: ['Financeiro'] }];
  check('proposto → pendente', estadoBatches(b('proposto')).pendente !== null);
  check('aprovado (mergeado, não executado) → ainda pendente', estadoBatches(b('aprovado')).pendente !== null);
  check('executado → livre', estadoBatches(b('executado')).pendente === null);
  check('rejeitado → livre', estadoBatches(b('rejeitado')).pendente === null);
}

// 3. Batimento por classe (§3.4): dinheiro re-entra no dia seguinte; resto precisa de 7d.
{
  const uc = new Map([['Sells', '2026-07-04'], ['kb', '2026-07-04']]);
  check('dinheiro 1d depois → devido', modDevido('Sells', 'dinheiro_fiscal', uc, '2026-07-05') === true);
  check('resto 1d depois → NÃO devido', modDevido('kb', 'resto', uc, '2026-07-05') === false);
  check('resto 7d depois → devido', modDevido('kb', 'resto', uc, '2026-07-11') === true);
  check('módulo nunca ciclado → devido', modDevido('Novo', 'resto', new Map(), '2026-07-05') === true);
}

// 4. Budget corta a fila (§3.6 r1): 10 telas devidas, budget 3 → 3 selecionadas, na ordem da fila.
{
  const fila = Array.from({ length: 10 }, (_, i) => tela({ screen: `M/T${i}`, mod: `M${i}`, prioridade: 1000 - i }));
  const sel = selecionaBatch(fila, new Map(), '2026-07-05', 3);
  check('budget 3 → 3 telas, maiores prioridades primeiro', sel.length === 3 && sel[0].screen === 'M/T0' && sel[2].screen === 'M/T2', JSON.stringify(sel.map((t) => t.screen)));
}

// 5. Seleção composta: verde-fresca e fora-de-batimento saem; próxima da fila entra no lugar.
{
  const fila = [
    tela({ screen: 'A/verde', mod: 'A', nota: 90, casos: true, charter: true }),           // pula (verde+fresca)
    tela({ screen: 'B/recem', mod: 'B', classe: 'resto', prioridade: 500 }),               // fora de batimento (ciclado ontem)
    tela({ screen: 'C/entra', mod: 'C', prioridade: 400 }),
  ];
  const sel = selecionaBatch(fila, new Map([['B', '2026-07-04']]), '2026-07-05', 5);
  check('verde pula + fora-de-batimento pula → só C entra', sel.length === 1 && sel[0].screen === 'C/entra', JSON.stringify(sel.map((t) => t.screen)));
}

// 6. Ação proposta deriva do gap (contrato-first): sem prontuário → ciclo completo; sem casos → contrato primeiro.
{
  check('sem scorecard → ciclo completo', acaoProposta(tela({ nota: null })).includes('COMPLETO'));
  check('sem casos → propõe casos.md + Pest ancorado', acaoProposta(tela({ nota: 70, charter: true })).includes('casos.md'));
  check('stale → propõe re-grade', acaoProposta(tela({ nota: 82, casos: true, charter: true, stale: true })).includes('re-grade'));
}

// 7. fmScalar lê o frontmatter controlado (status/date/modulos).
{
  const md = '---\ndate: "2026-07-05"\nstatus: proposto\nmodulos: [Financeiro, Sells]\n---\n# x';
  check('frontmatter: date/status/modulos', fmScalar(md, 'date') === '2026-07-05' && fmScalar(md, 'status') === 'proposto' && fmScalar(md, 'modulos') === '[Financeiro, Sells]');
}

// 8. Sanidade do contrato §3.4: constantes de batimento são as da arte (mudança = decisão consciente).
{
  check('batimento = {dinheiro 1, vertical 3, resto 7}', BATIMENTO_DIAS.dinheiro_fiscal === 1 && BATIMENTO_DIAS.vertical_prod === 3 && BATIMENTO_DIAS.resto === 7);
}

console.log(fails ? `\n✗ ${fails} falha(s)` : '\n✓ contrato do metabolismo preservado');
process.exit(fails ? 1 : 0);
