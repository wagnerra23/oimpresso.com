#!/usr/bin/env node
// Self-test vital-signs — prova as regras vs o CONTRATO documentado (arte 2026-07-05
// §3.2 sinais vitais / §3.4 frescor por criticidade / §3.5 priorização), NÃO vs a implementação.
// Contrato ancorado:
//   - pior tela puxa: agregação expõe mín AO LADO da média (média não esconde)
//   - frescor: stale se idade > 30d (dinheiro_fiscal) / > 60d (resto); NUNCA medido = stale
//   - prioridade = peso × (100 − nota) × 1.5-se-stale; sem scorecard → nota 0 (pior caso)
// Roda: node scripts/qa/vital-signs.test.mjs
import { yamlScalar, idadeDias, isStale, prioridade, agregaModulo, classeDoModulo, PESO } from './vital-signs.mjs';

let fails = 0;
const check = (n, c, extra = '') => { console.log(`${c ? '[OK]' : '[FAIL]'} ${n}${c ? '' : '  → ' + extra}`); if (!c) fails++; };

// 1. Pior tela puxa: módulo com telas 90 e 40 → mín=40 (o sinal), média=65 (contexto).
{
  const m = agregaModulo([
    { screen: 'X/A', nota: 90, idade: 1, stale: false, charter: true, casos: true },
    { screen: 'X/B', nota: 40, idade: 1, stale: false, charter: true, casos: false },
  ]);
  check('mín=40 e pior_tela=X/B (média 65 não esconde)', m.nota_min === 40 && m.pior_tela === 'X/B' && m.nota_media === 65, JSON.stringify(m));
}

// 2. Frescor por criticidade: 45d é stale pra dinheiro_fiscal (>30d) mas fresco pra resto (≤60d).
{
  check('45d → stale em dinheiro_fiscal', isStale(45, 'dinheiro_fiscal') === true);
  check('45d → fresco em resto', isStale(45, 'resto') === false);
  check('61d → stale em resto', isStale(61, 'resto') === true);
}

// 3. Nunca medido = pior frescor (anti verde-stale): idade null → stale em qualquer classe.
{
  check('sem graded_at → stale (não medido ≠ bom)', isStale(null, 'resto') === true && isStale(null, 'dinheiro_fiscal') === true);
}

// 4. Prioridade §3.5: tela dinheiro nota 50 fresca = 4×50=200; mesma tela stale = 300 (×1.5).
{
  check('dinheiro nota 50 fresca → 200', prioridade(50, 'dinheiro_fiscal', false) === 200);
  check('dinheiro nota 50 stale → 300 (stale sobe na fila)', prioridade(50, 'dinheiro_fiscal', true) === 300);
}

// 5. Sem scorecard = nota 0 (pior caso honesto): prioridade máxima da classe, nunca "verde por omissão".
{
  const semScore = prioridade(null, 'dinheiro_fiscal', true);
  check('sem scorecard dinheiro stale → 600 (teto da classe)', semScore === PESO.dinheiro_fiscal * 100 * 1.5, String(semScore));
}

// 6. Tela dinheiro ruim vence tela comum péssima (criticidade pesa): Sells nota 60 fresca (160) > kb nota 10 fresca (90).
{
  check('Sells@60 > kb@10 na fila (criticidade × gap)', prioridade(60, 'dinheiro_fiscal', false) > prioridade(10, 'resto', false));
}

// 7. classeDoModulo cobre o mapa do arte §3.4.
{
  check('Financeiro → dinheiro_fiscal', classeDoModulo('Financeiro') === 'dinheiro_fiscal');
  check('Vestuario → vertical_prod', classeDoModulo('Vestuario') === 'vertical_prod');
  check('kb → resto', classeDoModulo('kb') === 'resto');
}

// 8. idadeDias determinístico com hoje injetado; graded_at ilegível → null (vira stale via regra 3).
{
  const hoje = new Date('2026-07-05T12:00:00Z');
  check('graded_at 2026-07-01 → 4 dias', idadeDias('2026-07-01', hoje) === 4);
  check('graded_at lixo → null', idadeDias('ontem', hoje) === null);
}

// 9. yamlScalar lê o formato controlado pelo seed (com aspas, com comentário inline).
{
  const y = 'screen: Admin/X\nnota: 73\nbaseline_anterior: 64   # ratchet\ngraded_at: "2026-07-05"';
  check('nota=73, graded_at sem aspas, baseline ignora comentário', yamlScalar(y, 'nota') === '73' && yamlScalar(y, 'graded_at') === '2026-07-05' && yamlScalar(y, 'baseline_anterior') === '64');
}

// 10. Módulo 100% sem scorecard: agregado honesto (mín/média null, sem_scorecard = telas).
{
  const m = agregaModulo([
    { screen: 'Ponto/A', nota: null, idade: null, stale: true, charter: false, casos: false },
    { screen: 'Ponto/B', nota: null, idade: null, stale: true, charter: false, casos: false },
  ]);
  check('sem prontuário → mín/média null + stale (nunca verde por omissão)', m.nota_min === null && m.nota_media === null && m.sem_scorecard === 2 && m.stale === true, JSON.stringify(m));
}

console.log(fails ? `\n✗ ${fails} falha(s)` : '\n✓ contrato dos sinais vitais preservado');
process.exit(fails ? 1 : 0);
