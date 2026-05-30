#!/usr/bin/env node
// GRADE DO CLAUDE DESIGN — Método Governance Scorecard (ADR 0230) aplicado à dimensão DESIGN.
//
// Responde a pergunta do Wagner (2026-05-30): "O Claude Design já vai me entender?".
//
// Lê a rubrica score-as-code de memory/scorecards/design.yaml (fonte de verdade) e:
//   - pontua cada CAPACIDADE do Claude Design 0-100 vs best-of-class (etapas 1-3 do método);
//   - ANCORA a nota em evidência objetiva do repo (anti-gaming — conta personas, charters, etc);
//   - aplica Invariante A (ratchet): nenhuma unidade pode cair abaixo do baseline + cita o porquê;
//   - aplica Invariante B (RTM): cada unidade imprime o `origin` = a memória que a originou;
//   - aplica paired indicators (cap anti-gaming);
//   - imprime o veredito + roadmap pro topo (etapa 4).
//
// PROPOSTA — não altera comportamento (não é gate em CI ainda; Wagner aprova antes).
// Rodar (da raiz do repo):  node .claude/governance-eval/grade-design.mjs
// Exit 1 se houver regressão vs baseline (ratchet) ou evidência abaixo do esperado.

import { readFileSync, existsSync, readdirSync, statSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { parse } from 'yaml';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const YAML_PATH = join(ROOT, 'memory', 'scorecards', 'design.yaml');

const doc = parse(readFileSync(YAML_PATH, 'utf8'));
const U = doc.unidades;

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

// check por unidade: {fn, expect, label}. Unidade sem check = evidência documental.
const CHECKS = {
  CD1_compreensao_cliente: { fn: countPersonas, expect: 4, label: 'personas de cliente real (.yml)' },
  CD3_rubrica_pontuada: { fn: () => existsSync(rel('memory', 'requisitos', '_DesignSystem', 'framework-15-dimensoes.md')) ? 1 : 0, expect: 1, label: 'framework-15-dimensoes.md existe' },
  CD6_anti_regressao_design: { fn: countAntiPatterns, expect: 8, label: 'anti-padrões AP catalogados (PRE-MERGE-UI)' },
  CD7_rastreabilidade_rtm: { fn: countCharters, expect: 1, label: 'charters (*.charter.md) no repo' },
  CD9_metodo_executavel: { fn: () => (existsSync(YAML_PATH) ? 1 : 0) + (existsSync(rel('.claude', 'governance-eval', 'grade-design.mjs')) ? 1 : 0), expect: 2, label: 'design.yaml + grade-design.mjs presentes' },
};

const levelOf = (n) => (n >= 80 ? 'GOLD' : n >= 50 ? 'SILVER' : 'BRONZE');
const firstLine = (s) => String(s || '').split('\n').map(x => x.trim()).filter(Boolean)[0] || '';

// --- aplica paired indicators (cap) antes de agregar -----------------------
const maturities = {};
for (const [k, u] of Object.entries(U)) maturities[k] = u.maturity;
const caps = [];
for (const pi of doc.paired_indicators || []) {
  // rule no formato: 'se VEL >= X mas QUAL < Y, VEL cap em Z'
  const m = /se .+ >= (\d+) mas .+ < (\d+), .+ cap em (\d+)/.exec(pi.rule);
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

  const chk = CHECKS[k];
  if (chk) {
    const val = chk.fn();
    const ok = val >= chk.expect;
    if (!ok) evidenceFails++;
    out.push(`    ${ok ? '✓' : '⚠ EVIDÊNCIA ABAIXO'} evidência: ${val}/${chk.expect} ${chk.label}`);
  } else {
    out.push(`    · evidência: documental (${(u.evidence || [])[0] || '—'})`);
  }
  if (u.gaps && u.gaps.length) out.push(`    gap: ${u.gaps[0]}`);

  somaPond += mat * u.peso;
  somaPeso += u.peso;
}

const agregado = Math.round(somaPond / somaPeso);

console.log('================================================================');
console.log(' GRADE DO CLAUDE DESIGN — Método ADR 0230 aplicado a DESIGN');
console.log(' Pergunta: "O Claude Design já vai me entender?"');
console.log('================================================================');
console.log(out.join('\n'));

console.log(`\n----------------------------------------------------------------`);
console.log(' PAIRED INDICATORS (anti-gaming):');
if (caps.length) caps.forEach(c => console.log(`   ⚠ ${c}`));
else console.log('   ✓ todas as parelhas batem (nenhum cap aplicado)');

console.log(`----------------------------------------------------------------`);
console.log(` AGREGADO: ${agregado}/100 (${levelOf(agregado)})  ·  meta GOLD: ${doc.calculo.meta_gold}  ·  antes da sessão: ${doc.calculo.agregado_antes}`);
console.log(` Regressões (ratchet A): ${regressions}  ·  Evidências abaixo do esperado: ${evidenceFails}`);
console.log(` Invariante A (ratchet): nenhuma capacidade pode cair abaixo do baseline.`);
console.log(` Invariante B (RTM): toda capacidade cita origin = a memória do porquê.`);
console.log(`----------------------------------------------------------------`);
console.log(` VEREDITO: ${doc.veredito.resposta_curta}`);
console.log(`----------------------------------------------------------------`);

process.exit(regressions > 0 || evidenceFails > 0 ? 1 : 0);
