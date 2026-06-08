#!/usr/bin/env node
// scripts/a11y-ratchet.mjs — acessibilidade como categoria DETERMINÍSTICA PROTEGIDA.
//
// POR QUE (auditoria 2026-06-06-arte-llm-judge-para-deterministico + benchmark vs SOTA):
// a11y é o ponto-baixo do ecossistema. As 159 violações `jsx-a11y/*` viviam ENTERRADAS no
// baseline geral de eslint (1073) — gateadas contra novas, mas SEM visibilidade, SEM redução-alvo,
// e ABSORVÍVEIS via `lint:baseline:write` (a porta de escape do baseline geral).
//
// Este ratchet trata a11y como categoria PROTEGIDA: a contagem `jsx-a11y/*` só DESCE, nunca sobe —
// nem mesmo via baseline:write. axe-core RUNTIME (contraste/ARIA/foco que o estático não vê) = Fase 2
// (vitest-axe). Este é o Fase 1: determinístico, infra existente (eslint-plugin-jsx-a11y já ligado),
// Node puro (lê o config/eslint-baseline.json commitado), custo zero.
//
// Espelha o padrão dos ratchets do projeto (ADR 0209 · reuse/no-mock/css-size).
//
// USO:
//   node scripts/a11y-ratchet.mjs            # gate: falha se jsx-a11y AUMENTOU vs baseline
//   node scripts/a11y-ratchet.mjs --report   # breakdown por regra (visibilidade)
//   node scripts/a11y-ratchet.mjs --write     # (re)grava config/a11y-baseline.json (só pra DESCER)

import { readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = process.cwd();
const ESLINT_BASELINE = 'config/eslint-baseline.json';
const A11Y_BASELINE = 'config/a11y-baseline.json';

// extrai a contagem jsx-a11y/* do baseline geral do eslint (fonte da verdade, commitada)
function currentA11y() {
  const bl = JSON.parse(readFileSync(join(ROOT, ESLINT_BASELINE), 'utf8'));
  const byRule = {};
  let total = 0;
  for (const [key, n] of Object.entries(bl.counts || {})) {
    const m = key.match(/\|(jsx-a11y\/[a-z-]+)$/);
    if (m) { byRule[m[1]] = (byRule[m[1]] || 0) + n; total += n; }
  }
  return { total, byRule };
}

const args = process.argv.slice(2);
const cur = currentA11y();

if (args.includes('--report')) {
  console.log(`# a11y (jsx-a11y) — ${cur.total} violações no baseline\n`);
  for (const [rule, n] of Object.entries(cur.byRule).sort((a, b) => b[1] - a[1])) {
    console.log(`  ${String(n).padStart(3)}  ${rule}`);
  }
  console.log(`\n(axe-core runtime = Fase 2. Este conta só o estático jsx-a11y.)`);
  process.exit(0);
}

if (args.includes('--write')) {
  const payload = { generated_by: 'scripts/a11y-ratchet.mjs --write', total: cur.total, by_rule: cur.byRule };
  writeFileSync(join(ROOT, A11Y_BASELINE), JSON.stringify(payload, null, 2) + '\n');
  console.log(`✓ a11y-baseline gravado: ${cur.total} violações jsx-a11y conhecidas (só pode descer daqui).`);
  process.exit(0);
}

// gate (default)
let baseline;
try { baseline = JSON.parse(readFileSync(join(ROOT, A11Y_BASELINE), 'utf8')); }
catch { console.error(`✗ a11y-baseline ausente. Rode: node scripts/a11y-ratchet.mjs --write`); process.exit(2); }

if (cur.total <= baseline.total) {
  const delta = baseline.total - cur.total;
  console.log(`✓ a11y ratchet OK — ${cur.total} violações jsx-a11y (baseline ${baseline.total}${delta > 0 ? `, ↓${delta} — rode --write pra travar o ganho` : ''}).`);
  process.exit(0);
}
console.error(`✗ a11y ratchet FALHOU — jsx-a11y SUBIU: ${baseline.total} → ${cur.total} (+${cur.total - baseline.total}).`);
console.error(`\nAcessibilidade é categoria PROTEGIDA (não absorve débito novo, nem via baseline:write).`);
console.error(`Corrija a violação a11y (não suprima): label, key-events, role, aria. Veja --report.`);
process.exit(1);
