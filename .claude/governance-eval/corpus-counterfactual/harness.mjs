#!/usr/bin/env node
// @ts-check
/**
 * harness.mjs — camada de EXECUÇÃO do contrafactual de corpus (o que foi PODADO).
 *
 * Contexto: a grade 2026-07-17 fechou o chip C1 medindo que replicar o paper custa
 * 29k–471k runs (aritmética inviável) → o harness de execução de 3 arms foi PODADO do
 * scripts/governance/agent-corpus-counterfactual.mjs por ZERO invocador. Em 2026-07-17
 * [W] escolheu "gastar" (rodar o experimento no que cabe: efeito ≥20pp, ~297 runs).
 * Isso dá ao harness um invocador → ele volta a existir, agora AQUI (scaffold de
 * experimento, separado do script de governança enxuto).
 *
 * ⚠️ CORREÇÃO DE FATO: o comentário do script mergeado dizia "o código vive no git,
 * recuperar é git log --diff-filter=D". FALSO — apurado 2026-07-17: `git log -S
 * "export function armStats"` volta VAZIO. A função NUNCA foi commitada (só existiu no
 * working tree, sobrescrita pelo Write da poda). Este arquivo é RECONSTRUÇÃO fiel do
 * conteúdo (que vivia no histórico da conversa), não recuperação. Follow-up: corrigir
 * o comentário mentiroso no script de governança.
 *
 * NÃO DUPLICA (proibicoes §5): importa TODAS as primitivas do que já está commitado —
 * stats de agent-corpus-counterfactual, custo de agent-cost-per-pr, median de
 * agent-pr-outcomes. Só re-adiciona a camada que os COMPÕE (armStats/contraste/report).
 *
 * VEREDITO QUE NÃO MENTE (a regra dura do chip): só declara melhor/pior quando o IC 95%
 * da DIFERENÇA exclui 0. n pequeno com Δ aparente → `indistinguivel` + o MDE. Fail-safe:
 * `pass` não-booleano NUNCA vira crédito pro corpus (entra em `invalidos`).
 */
import { wilsonCI, newcombeDiffCI, minDetectableEffect } from '../../../scripts/governance/agent-corpus-counterfactual.mjs';
import { parseUsageLine, custoUSD, aggregatePorModelo } from '../../../scripts/governance/agent-cost-per-pr.mjs';
import { median } from '../../../scripts/governance/agent-pr-outcomes.mjs';

/** os braços. `sem` é o CONTROLE (baseline dos contrastes). */
export const ARMS = /** @type {const} */ (['sem', 'atual', 'wagner']);
export const ARM_CONTROLE = 'sem';

const round1 = (v) => (v == null ? null : Math.round(v * 10) / 10);
const round2 = (v) => (v == null ? null : Math.round(v * 100) / 100);

/** normaliza `usage` (linhas JSONL cruas OU entries já parseadas) → entries. */
function normalizarUsage(usage) {
  if (!Array.isArray(usage)) return [];
  return usage.map((u) => (typeof u === 'string' ? parseUsageLine(u) : u)).filter(Boolean);
}

/**
 * armStats — agrega os runs de UM braço.
 * `pass` só conta se for booleano `true`. Qualquer outra coisa (undefined, 'ok', run
 * que morreu) entra em `invalidos` — fail-safe: ambiguidade nunca vira crédito.
 */
export function armStats(runs, arm) {
  const meus = runs.filter((r) => r && r.arm === arm);
  const validos = meus.filter((r) => typeof r.pass === 'boolean');
  const invalidos = meus.length - validos.length;
  const k = validos.filter((r) => r.pass === true).length;
  const n = validos.length;
  const ci = wilsonCI(k, n);

  let usd = 0, incompleto = false;
  const usdPorRun = [];
  for (const r of validos) {
    const entries = normalizarUsage(r.usage);
    if (!entries.length) continue;
    let usdRun = 0;
    for (const [model, sums] of aggregatePorModelo(entries)) {
      const c = custoUSD(sums, model);
      if (c == null) incompleto = true; else usdRun += c;
    }
    usd += usdRun;
    usdPorRun.push(usdRun);
  }

  return {
    arm, n, pass: k, invalidos,
    first_pass_pct: n ? round1((k / n) * 100) : null,
    ic95_pp: ci ? { lo: round1(ci.lo * 100), hi: round1(ci.hi * 100) } : null,
    usd_total: usdPorRun.length ? round2(usd) : null,
    usd_mediana: usdPorRun.length ? round2(median(usdPorRun)) : null,
    usd_incompleto: incompleto || undefined,
  };
}

/**
 * classificarContraste — O PORTÃO CONTRA O VEREDITO QUE MENTE.
 * Só devolve 'melhor'/'pior' quando o IC 95% da diferença EXCLUI 0.
 */
export function classificarContraste(statsA, statsB) {
  const base = { arm: statsA.arm, vs: statsB.arm };
  if (!statsA.n || !statsB.n) {
    return { ...base, veredito: 'sem_dados', delta_pp: null, ic95_pp: null, mde_pp: null,
      motivo: 'algum braço sem run válido' };
  }
  const d = newcombeDiffCI(statsA.pass, statsA.n, statsB.pass, statsB.n);
  const nEfetivo = Math.min(statsA.n, statsB.n);
  const mde = minDetectableEffect(nEfetivo);
  const lo = round1(d.lo * 100), hi = round1(d.hi * 100), delta = round1(d.delta * 100);
  if (d.lo <= 0 && d.hi >= 0) {
    return { ...base, veredito: 'indistinguivel', delta_pp: delta, ic95_pp: { lo, hi }, mde_pp: mde,
      motivo: `IC95 da diferença [${lo}, ${hi}]pp contém 0 — com n=${nEfetivo}/braço só daria pra ver efeito ≥${mde}pp. Ausência de evidência ≠ evidência de ausência.` };
  }
  return { ...base, veredito: d.delta > 0 ? 'melhor' : 'pior', delta_pp: delta, ic95_pp: { lo, hi }, mde_pp: mde,
    motivo: `IC95 da diferença [${lo}, ${hi}]pp exclui 0 (n=${nEfetivo}/braço).` };
}

/** buildReport — contrastes SEMPRE contra o braço de controle `sem`. Puro. */
export function buildReport({ runs, skill = null, generated } = {}) {
  const lista = Array.isArray(runs) ? runs : [];
  const armsPresentes = ARMS.filter((a) => lista.some((r) => r && r.arm === a));
  const arms = armsPresentes.map((a) => armStats(lista, a));
  const porArm = Object.fromEntries(arms.map((s) => [s.arm, s]));
  const controle = porArm[ARM_CONTROLE];

  const contrastes = controle
    ? armsPresentes.filter((a) => a !== ARM_CONTROLE).map((a) => classificarContraste(porArm[a], controle))
    : [];

  const custoBase = controle ? controle.usd_total : null;
  for (const s of arms) {
    s.custo_overhead_pct = (s.arm !== ARM_CONTROLE && custoBase && s.usd_total != null)
      ? round1(((s.usd_total - custoBase) / custoBase) * 100) : null;
  }

  const tarefas = new Set(lista.map((r) => r && r.tarefa).filter(Boolean));
  return {
    ok: true,
    generated: generated || new Date().toISOString().slice(0, 10),
    skill,
    cobertura: { tarefas: tarefas.size, runs: lista.length, por_arm: Object.fromEntries(arms.map((s) => [s.arm, s.n])) },
    arms, contrastes,
    confianca: 'experimento (arm atribuído; veredito só com IC que exclui 0)',
  };
}

const pp = (v) => (v == null ? 'n/d' : `${v}pp`);
const pctv = (v) => (v == null ? 'n/d' : `${v}%`);
const usdv = (v) => (v == null ? 'n/d' : `$${v.toFixed(2)}`);
const ICONE = { melhor: '🟢', pior: '🔴', indistinguivel: '⚪', sem_dados: '—' };

export function renderHuman(r) {
  const L = [];
  L.push('═══════════════════════════════════════════════════════════════');
  L.push(` CONTRAFACTUAL DE CORPUS — skill "${r.skill || '?'}" · ${r.generated}`);
  L.push(` cobertura: ${r.cobertura.tarefas} tarefa(s) · ${r.cobertura.runs} run(s)`);
  L.push('═══════════════════════════════════════════════════════════════');
  L.push('');
  L.push(`  ${'BRAÇO'.padEnd(9)} ${'n'.padEnd(4)} ${'first-pass'.padEnd(12)} ${'IC95'.padEnd(18)} ${'custo'.padEnd(9)} overhead`);
  for (const s of r.arms) {
    const ic = s.ic95_pp ? `[${s.ic95_pp.lo}, ${s.ic95_pp.hi}]pp` : 'n/d';
    L.push(`  ${s.arm.padEnd(9)} ${String(s.n).padEnd(4)} ${String(pctv(s.first_pass_pct)).padEnd(12)} ${ic.padEnd(18)} ${usdv(s.usd_total).padEnd(9)} ${s.custo_overhead_pct == null ? '—' : pctv(s.custo_overhead_pct)}`);
    if (s.invalidos) L.push(`  ${''.padEnd(9)} ↳ ${s.invalidos} run(s) inválido(s) — NÃO contam como passe (fail-safe)`);
  }
  L.push('');
  L.push('▸ VEREDITO (só declara vencedor se o IC da diferença excluir 0)');
  for (const c of r.contrastes) {
    L.push(`  ${ICONE[c.veredito]} ${c.arm} vs ${c.vs}: ${c.veredito.toUpperCase()}  Δ ${pp(c.delta_pp)}`);
    L.push(`      ${c.motivo}`);
  }
  if (!r.contrastes.length) L.push('  (sem braço de controle `sem` nos dados — nada a contrastar)');
  L.push('═══════════════════════════════════════════════════════════════');
  return L.join('\n');
}

// ── entry-point: reporta a partir de um runs.json ────────────────────────────
import { readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
if (import.meta.url === pathToFileURL(process.argv[1] || '').href) {
  const argv = process.argv.slice(2);
  const runsFile = argv.find((a) => !a.startsWith('--'));
  if (!runsFile) { console.error('uso: node harness.mjs <runs.json> [--json]'); process.exit(1); }
  const f = JSON.parse(readFileSync(runsFile, 'utf8'));
  const r = buildReport({ runs: Array.isArray(f) ? f : f.runs, skill: f.skill });
  process.stdout.write((argv.includes('--json') ? JSON.stringify(r, null, 2) : renderHuman(r)) + '\n');
  process.exit(0);
}
