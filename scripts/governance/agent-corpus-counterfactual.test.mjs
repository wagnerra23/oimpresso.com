#!/usr/bin/env node
// @ts-check
// SELF-TEST de agent-corpus-counterfactual.mjs — defende a ARITMÉTICA DE VIABILIDADE
// (o único produto do arquivo após a poda de 2026-07-17).
//
// A promessa do `nNeededFor` é "80% de poder". Poder DE QUAL TESTE? Esse é o buraco por
// onde uma fórmula errada passaria batido: ela é analítica, foi escrita de cabeça, e um
// erro nela SUBESTIMARIA o custo do experimento — exatamente o número que [W] usa pra
// decidir se gasta ou não. Por isso este self-test não confere a fórmula contra si mesma
// (tautologia — lápide §5 2026-06-05): ele SIMULA.
//
// MONTE CARLO DETERMINÍSTICO: PRNG semeado (mulberry32). Mesma semente → mesmo resultado
// → nunca flaka no CI, e ainda assim é medição empírica de verdade. Nasceu como check
// adversarial rodado 1× à mão (2026-07-17); virou guarda permanente porque a propriedade
// que ele prova é a que decide gasto.
//
// Hermético: zero rede/git/FS. Rodar: node scripts/governance/agent-corpus-counterfactual.test.mjs

import {
  wilsonCI, newcombeDiffCI, minDetectableEffect, nNeededFor, renderPower,
  ETH_EFEITO_LLM_PP, ETH_CUSTO_OVERHEAD_PCT, Z_ALPHA_95, Z_POWER_80,
} from './agent-corpus-counterfactual.mjs';

let fails = 0;
const check = (name, cond) => { console.log(`${cond ? '[OK]  ' : '[FAIL]'} ${name}`); if (!cond) fails++; };
const perto = (a, b, tol = 0.01) => a != null && Math.abs(a - b) <= tol;

// ── PRNG semeado (mulberry32) — determinismo do Monte Carlo ──────────────────
function prng(seed) {
  let s = seed >>> 0;
  return () => {
    s = (s + 0x6D2B79F5) >>> 0;
    let t = Math.imul(s ^ (s >>> 15), 1 | s);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

// ── Wilson: conferido contra tabela publicada ────────────────────────────────
const w55 = wilsonCI(5, 5);
check('wilson 5/5 = [0.566, 1.0] (valor de tabela publicada)', perto(w55.lo, 0.5655, 0.002) && perto(w55.hi, 1.0));
const w35 = wilsonCI(3, 5);
check('wilson 3/5 = [0.231, 0.882]', perto(w35.lo, 0.2307, 0.002) && perto(w35.hi, 0.8824, 0.002));
check('wilson 0/5 NÃO colapsa em [0,0] (o motivo de não usar Wald)', wilsonCI(0, 5).hi > 0.4);
check('wilson n=0 → null (sem dado, sem invenção)', wilsonCI(0, 0) === null);
check('wilson nunca sai de [0,1]', wilsonCI(5, 5).hi <= 1 && wilsonCI(0, 5).lo >= 0);

// ── Newcombe: o IC da diferença ─────────────────────────────────────────────
const dGrande = newcombeDiffCI(90, 100, 40, 100);
check('newcombe 90/100 vs 40/100 → IC [37.7, 60.1]pp exclui 0', perto(dGrande.lo, 0.377, 0.005) && perto(dGrande.hi, 0.601, 0.005) && dGrande.lo > 0);
const dPequeno = newcombeDiffCI(5, 5, 3, 5);
check('newcombe 5/5 vs 3/5 (n=5) → IC contém 0 apesar de Δ=+40pp', dPequeno.lo < 0 && dPequeno.hi > 0);
check('newcombe braço vazio → null', newcombeDiffCI(0, 0, 3, 5) === null);

// ── a aritmética (contratos analíticos) ─────────────────────────────────────
check('MDE(5) ≈ 88.6pp — n=5 não vê quase nada', perto(minDetectableEffect(5), 88.6, 0.2));
check('MDE(10) ≈ 62.6pp — piloto pequeno não decide', perto(minDetectableEffect(10), 62.6, 0.2));
check('MDE(100) ≈ 19.8pp — só efeito grande', perto(minDetectableEffect(100), 19.8, 0.2));
check('MDE cai quando n sobe (monotônico)', minDetectableEffect(500) < minDetectableEffect(100));
check('MDE(0)/MDE(-1) → null (não inventa)', minDetectableEffect(0) === null && minDetectableEffect(-1) === null);
check(`nNeededFor(${ETH_EFEITO_LLM_PP.max}pp = topo do paper) > 9k/braço — replicar é inviável`, nNeededFor(ETH_EFEITO_LLM_PP.max) > 9000);
check(`nNeededFor(${ETH_EFEITO_LLM_PP.min}pp = piso do paper) > 150k/braço — absurdo`, nNeededFor(ETH_EFEITO_LLM_PP.min) > 150000);
check('nNeededFor(20pp) ≈ 99/braço — o efeito de poda CABE', nNeededFor(20) > 90 && nNeededFor(20) < 110);
check('n cresce com 1/δ²: dobrar δ divide n por ~4', Math.abs(nNeededFor(10) / nNeededFor(20) - 4) < 0.15);
check('round-trip nNeededFor(MDE(n)) ≈ n', Math.abs(nNeededFor(minDetectableEffect(200)) - 200) <= 3);
check('nNeededFor(0) → null (não inventa)', nNeededFor(0) === null);

// ══════════════════════════════════════════════════════════════════════════════
//  MONTE CARLO — a fórmula analítica CUMPRE o poder que promete?
//  Se vier ABAIXO de 80%, a fórmula MENTE e o custo do experimento está SUBESTIMADO
//  (erro perigoso: [W] gastaria achando que veria, e não veria). Acima = conservadora
//  (erro seguro). Determinístico por semente fixa.
// ══════════════════════════════════════════════════════════════════════════════
const REPS = 2000;
const binom = (rnd, n, p) => { let k = 0; for (let i = 0; i < n; i++) if (rnd() < p) k++; return k; };
const detecta = (d) => d.lo > 0 || d.hi < 0;

function poderEmpirico(seed, n, p1, p2) {
  const rnd = prng(seed);
  let hits = 0;
  for (let r = 0; r < REPS; r++) if (detecta(newcombeDiffCI(binom(rnd, n, p1), n, binom(rnd, n, p2), n))) hits++;
  return (hits / REPS) * 100;
}

console.log(`\n  ── Monte Carlo determinístico (${REPS} réplicas/célula, semente fixa) ──`);

for (const [seed, delta] of [[1001, 10], [1002, 20], [1003, 30]]) {
  const n = nNeededFor(delta);
  const pe = poderEmpirico(seed, n, 0.5 + delta / 100, 0.5);
  check(`poder de nNeededFor(${delta}pp)=${n}/braço: empírico ${pe.toFixed(1)}% ≥ 75% (prometido 80)`, pe >= 75);
}

// O PECADO CAPITAL: sob H0 (zero diferença real), com que frequência ele grita vencedor?
// Tem que ficar ≈ α=5%. Se estourar, o veredito seria ruído com cara de ciência — o
// motivo de este arquivo existir. Testado inclusive em n=5, onde a tentação é máxima.
for (const [seed, n, p] of [[2001, 5, 0.5], [2002, 10, 0.5], [2003, 99, 0.5], [2004, 99, 0.8], [2005, 500, 0.5]]) {
  const rnd = prng(seed);
  let grita = 0;
  for (let r = 0; r < REPS; r++) if (detecta(newcombeDiffCI(binom(rnd, n, p), n, binom(rnd, n, p), n))) grita++;
  const fp = (grita / REPS) * 100;
  check(`H0 n=${n} p=${p}: grita vencedor ${fp.toFixed(1)}% ≤ 7% (α=5%+ruído) — não inventa vencedor no ruído`, fp <= 7);
}

// O cenário do paper: 2pp real com n=100 tem que ser INVISÍVEL (MDE diz 19.8pp).
// Prova que a aritmética não finge poder que não tem.
const pePaper = poderEmpirico(3001, 100, 0.5 - ETH_EFEITO_LLM_PP.max / 100, 0.5);
check(`efeito REAL do paper (${ETH_EFEITO_LLM_PP.max}pp) com n=100: poder ${pePaper.toFixed(1)}% ≤ 15% — invisível, como o MDE (${minDetectableEffect(100)}pp) avisa`, pePaper <= 15);

// MDE conferido por simulação: no próprio MDE, o poder tem que bater ~80%.
for (const [seed, n] of [[4001, 50], [4002, 100], [4003, 200]]) {
  const mde = minDetectableEffect(n);
  const pe = poderEmpirico(seed, n, 0.5 + mde / 100, 0.5);
  check(`MDE(${n})=${mde}pp confere por simulação: poder ${pe.toFixed(1)}% ≥ 75%`, pe >= 75);
}

// determinismo: mesma semente, mesmo número (senão o CI flaka e o guarda vira ruído)
check('Monte Carlo é DETERMINÍSTICO (mesma semente → mesmo poder) — não flaka no CI',
  poderEmpirico(9999, 99, 0.7, 0.5) === poderEmpirico(9999, 99, 0.7, 0.5));

// ── render: o produto não pode mentir sobre si ──────────────────────────────
const power = renderPower();
check('--power cita o custo de replicar o paper (dezenas de milhares)', new RegExp(String(nNeededFor(ETH_EFEITO_LLM_PP.max))).test(power));
check('--power avisa que piloto pequeno não responde nada', /ausência de evidência/i.test(power));
check('--power carrega a régua do paper (+20% de custo)', new RegExp(`\\+${ETH_CUSTO_OVERHEAD_PCT}% de custo`).test(power));
check('--power carimba ADVISORY (não vira gate por descuido)', /ADVISORY/.test(power));
check('--power deriva os δ das constantes do paper (não restateia à mão)',
  power.includes(`${ETH_EFEITO_LLM_PP.min}pp`) && power.includes(`${ETH_EFEITO_LLM_PP.max}pp`));

// ── z's: se alguém mexer, a tabela inteira muda em silêncio ─────────────────
check('Z_ALPHA_95/Z_POWER_80 são os canônicos (α=5% bilateral, poder 80%)',
  perto(Z_ALPHA_95, 1.96, 0.001) && perto(Z_POWER_80, 0.8416, 0.001));

console.log(fails
  ? `\nSELFTEST FALHOU (${fails})`
  : '\nSELFTEST OK — a aritmética de viabilidade CUMPRE o poder que promete (Monte Carlo determinístico), NÃO grita vencedor sob H0 (nem em n=5), e não finge ver o efeito real do paper.');
process.exit(fails ? 1 : 0);
