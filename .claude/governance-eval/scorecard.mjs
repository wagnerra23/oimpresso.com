#!/usr/bin/env node
// RUNNER UNIVERSAL DE SCORECARD — ADR 0236 (Scorecard Universal / blueprint pattern).
//
// Um motor só lê QUALQUER scorecard score-as-code por --scope <kind>/<nome> e:
//   - pontua cada unidade 0-100 vs best-of-class (etapas 1-3 do método ADR 0230);
//   - ANCORA a nota em evidência objetiva do repo (anti-gaming), quando o scope tem checks;
//   - aplica Invariante A (ratchet): nenhuma unidade cai abaixo do baseline + cita o porquê;
//   - aplica Invariante B (RTM): cada unidade imprime o `origin` = a memória que a originou;
//   - aplica paired indicators (cap anti-gaming);
//   - imprime o veredito.
//
// Substitui o grade-design.mjs (que era hardcoded num único scope). Os 3 kinds da ADR 0236:
//   themes/<tema>   → ThemeScorecard (entidade virtual; checks de evidência de path)
//   modules/<bucket>→ ModuleScorecard (entidade física; futuro: AST/SQL)
//   governance      → RuleScorecard (regras R1-R12; futuro: casos × hooks — ver grade.mjs)
//
// Uso (da raiz do repo):
//   node .claude/governance-eval/scorecard.mjs --scope themes/claude-design
// Exit 1 se houver regressão vs baseline (ratchet) ou evidência abaixo do esperado.

import { readFileSync, existsSync, readdirSync, statSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { parse } from 'yaml';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');

// --- argv: --scope <kind>/<nome> ------------------------------------------
function parseScope() {
  const i = process.argv.indexOf('--scope');
  if (i >= 0 && process.argv[i + 1]) return process.argv[i + 1];
  // fallback: 1º arg não-flag
  const a = process.argv.slice(2).find(x => !x.startsWith('--'));
  return a || null;
}
const scope = parseScope();
if (!scope) {
  console.error('uso: node .claude/governance-eval/scorecard.mjs --scope <themes|modules|governance>/<nome>');
  console.error('ex:  node .claude/governance-eval/scorecard.mjs --scope themes/claude-design');
  process.exit(2);
}
const YAML_PATH = join(ROOT, 'memory', 'scorecards', `${scope}.yaml`);
if (!existsSync(YAML_PATH)) {
  console.error(`scorecard não encontrado: memory/scorecards/${scope}.yaml`);
  process.exit(2);
}
const doc = parse(readFileSync(YAML_PATH, 'utf8'));
const U = doc.unidades || {};

// --- helpers de evidência objetiva (ancoragem anti-gaming) ----------------
const rel = (...p) => join(ROOT, ...p);
function safe(fn, fallback = 0) { try { return fn(); } catch { return fallback; } }

function countPersonas() {
  return safe(() => {
    const base = rel('memory', 'clientes');
    if (!existsSync(base)) return 0;
    let n = 0;
    for (const cliente of readdirSync(base)) {
      const pdir = join(base, cliente, 'personas');
      if (existsSync(pdir) && statSync(pdir).isDirectory()) {
        n += readdirSync(pdir).filter(f => f.endsWith('.yml') && !f.startsWith('_proposta')).length;
      }
    }
    return n;
  });
}
function countCharters() {
  return safe(() => {
    const base = rel('resources', 'js');
    if (!existsSync(base)) return 0;
    let n = 0;
    const walk = (dir, depth) => {
      if (depth > 6) return;
      for (const e of readdirSync(dir)) {
        const p = join(dir, e);
        const st = safe(() => statSync(p), null);
        if (!st) continue;
        if (st.isDirectory()) walk(p, depth + 1);
        else if (e.endsWith('.charter.md')) n++;
      }
    };
    walk(base, 0);
    return n;
  });
}
function countAntiPatterns() {
  return safe(() => {
    const f = rel('memory', 'requisitos', '_DesignSystem', 'PRE-MERGE-UI.md');
    if (!existsSync(f)) return 0;
    const m = readFileSync(f, 'utf8').match(/\*\*AP\d+\*\*/g);
    return m ? new Set(m).size : 0;
  });
}

// Registro de evidence-checks por scope (anti-gaming). Scope sem registro = sem ancoragem.
// (Evolução futura — ADR 0236 onda 5: mover os checks pro próprio YAML, score-as-code puro.)
const EVIDENCE_CHECKS = {
  'themes/claude-design': {
    CD1_compreensao_cliente: { fn: countPersonas, expect: 4, label: 'personas de cliente real (.yml)' },
    CD3_rubrica_pontuada: { fn: () => existsSync(rel('memory', 'requisitos', '_DesignSystem', 'framework-15-dimensoes.md')) ? 1 : 0, expect: 1, label: 'framework-15-dimensoes.md existe' },
    CD6_anti_regressao_design: { fn: countAntiPatterns, expect: 8, label: 'anti-padrões AP catalogados (PRE-MERGE-UI)' },
    CD7_rastreabilidade_rtm: { fn: countCharters, expect: 1, label: 'charters (*.charter.md) no repo' },
    CD9_metodo_executavel: { fn: () => (existsSync(YAML_PATH) ? 1 : 0) + (existsSync(rel('.claude', 'governance-eval', 'scorecard.mjs')) ? 1 : 0), expect: 2, label: 'scorecard YAML + runner scorecard.mjs presentes' },
  },
};
const checks = EVIDENCE_CHECKS[scope] || {};

const levelOf = (n) => (n >= 80 ? 'GOLD' : n >= 50 ? 'SILVER' : 'BRONZE');
const firstLine = (s) => String(s || '').split('\n').map(x => x.trim()).filter(Boolean)[0] || '';

// --- aplica paired indicators (cap) antes de agregar -----------------------
const maturities = {};
for (const [k, u] of Object.entries(U)) maturities[k] = u.maturity;
const caps = [];
for (const pi of doc.paired_indicators || []) {
  const m = /se .+ >= (\d+) mas .+ < (\d+), .+ cap em (\d+)/.exec(pi.rule || '');
  if (!m) continue;
  const [X, Y, Z] = [Number(m[1]), Number(m[2]), Number(m[3])];
  if (maturities[pi.velocidade] >= X && maturities[pi.qualidade] < Y) {
    const antes = maturities[pi.velocidade];
    maturities[pi.velocidade] = Math.min(antes, Z);
    caps.push(`CAP: ${pi.velocidade} ${antes}→${maturities[pi.velocidade]} (${pi.racional})`);
  }
}

// --- imprime scorecard -----------------------------------------------------
const out = [];
let regressions = 0, evidenceFails = 0, somaPond = 0, somaPeso = 0;

for (const [k, u] of Object.entries(U)) {
  const mat = maturities[k];
  const lvl = levelOf(mat);
  const regrediu = mat < u.baseline;
  if (regrediu) regressions++;

  out.push(`\n[${k.split('_')[0]}] ${u.title}  —  ${lvl}`);
  out.push(`    eixo: ${u.eixo}`);
  out.push(`    maturidade vs melhores: ${mat}/100 (${u.rec})  ·  baseline: ${u.baseline}  ${regrediu ? '⛔ REGREDIU' : '✓ ok'}`);
  out.push(`    ⚖ anti-regressão (A): ${firstLine(u.justification)}`);
  out.push(`    ↳ origin (B): ${u.origin}`);

  const chk = checks[k];
  if (chk) {
    const val = chk.fn();
    const ok = val >= chk.expect;
    if (!ok) evidenceFails++;
    out.push(`    ${ok ? '✓' : '⚠ EVIDÊNCIA ABAIXO'} evidência: ${val}/${chk.expect} ${chk.label}`);
  } else {
    out.push(`    · evidência: documental (${(u.evidence || [])[0] || '—'})`);
  }
  if (u.gaps && u.gaps.length) out.push(`    gap: ${u.gaps[0]}`);

  somaPond += mat * (u.peso || 1);
  somaPeso += (u.peso || 1);
}

const agregado = Math.round(somaPond / somaPeso);
const metaGold = (doc.calculo && doc.calculo.meta_gold) || 80;
const antes = (doc.calculo && doc.calculo.agregado_antes);

console.log('================================================================');
console.log(` SCORECARD: ${scope}  (kind: ${doc.kind || '?'})  —  ADR 0236 runner universal`);
if (doc.metadata && doc.metadata.pergunta) console.log(` Pergunta: "${doc.metadata.pergunta}"`);
console.log('================================================================');
console.log(out.join('\n'));

console.log(`\n----------------------------------------------------------------`);
console.log(' PAIRED INDICATORS (anti-gaming):');
if (caps.length) caps.forEach(c => console.log(`   ⚠ ${c}`));
else console.log('   ✓ todas as parelhas batem (nenhum cap aplicado)');

console.log(`----------------------------------------------------------------`);
console.log(` AGREGADO: ${agregado}/100 (${levelOf(agregado)})  ·  meta GOLD: ${metaGold}${antes != null ? `  ·  antes: ${antes}` : ''}`);
console.log(` Regressões (ratchet A): ${regressions}  ·  Evidências abaixo do esperado: ${evidenceFails}`);
console.log(` Invariante A (ratchet): nenhuma unidade pode cair abaixo do baseline.`);
console.log(` Invariante B (RTM): toda unidade cita origin = a memória do porquê.`);
console.log(`----------------------------------------------------------------`);
if (doc.veredito && doc.veredito.resposta_curta) {
  console.log(` VEREDITO: ${doc.veredito.resposta_curta}`);
  console.log(`----------------------------------------------------------------`);
}

process.exit(regressions > 0 || evidenceFails > 0 ? 1 : 0);
