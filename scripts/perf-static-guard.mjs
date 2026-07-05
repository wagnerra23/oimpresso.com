#!/usr/bin/env node
// scripts/perf-static-guard.mjs — catraca da Onda 4 lente 5b (AUDITORIA-PERFORMANCE-2026-07).
//
// POR QUE EXISTE
// O app web prod ainda não exporta OTel (gap §1.1 da auditoria) — então a única régua
// barata de tendência de performance é estática: contadores de padrões que a auditoria
// mapeou como custo real (N+1 e props caras eager). Este guard fotografa 3 contadores e
// FALHA se algum SUBIR (ratchet). Melhorou? `--write-baseline` regrava a foto.
//
// CONTADORES (heurísticos de TENDÊNCIA, não contagem exata de bugs):
//   paginate_sem_eager       paginate()/simplePaginate() sem with()/withCount()/select()/pluck()
//                            nas 40 linhas anteriores do mesmo arquivo.
//                            Falsos positivos catalogados (auditoria §4): SellController:1237
//                            (colunas no 2º arg), CaixaUnificadaController:478 (with() a >40
//                            linhas), ComprasController:139 (select no Service). Por isso o
//                            número serve de catraca (não pode subir), não de lista de bugs.
//   render_paginate_sem_defer  controllers com Inertia::render + paginate( e ZERO Inertia::defer
//   render_count_sem_defer     controllers com Inertia::render + ->count() e ZERO Inertia::defer
//
// Canon do padrão defer: memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md
// Uso:
//   node scripts/perf-static-guard.mjs                   # valida vs baseline (exit 1 se piorou)
//   node scripts/perf-static-guard.mjs --write-baseline  # (re)grava baseline
// Advisory (ADR 0314: required = só Tier-0). Gêmeo de domain-dict-guard.mjs.

import { readFileSync, writeFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { join, dirname, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const BASELINE_PATH = join(ROOT, 'scripts', 'perf-static-baseline.json');
const LOOKBACK = 40;

function phpFilesUnder(dir) {
  const abs = join(ROOT, dir);
  if (!existsSync(abs)) return [];
  const out = [];
  for (const entry of readdirSync(abs)) {
    const p = join(abs, entry);
    const st = statSync(p);
    if (st.isDirectory()) out.push(...phpFilesUnder(relative(ROOT, p)));
    else if (entry.endsWith('.php')) out.push(relative(ROOT, p).replace(/\\/g, '/'));
  }
  return out;
}

const controllerDirs = ['app/Http/Controllers'];
const modulesDir = join(ROOT, 'Modules');
if (existsSync(modulesDir)) {
  for (const mod of readdirSync(modulesDir)) {
    const d = join('Modules', mod, 'Http', 'Controllers');
    if (existsSync(join(ROOT, d))) controllerDirs.push(d);
  }
}
const files = controllerDirs.flatMap(phpFilesUnder);

const counts = { paginate_sem_eager: 0, render_paginate_sem_defer: 0, render_count_sem_defer: 0 };
const offenders = { paginate_sem_eager: [], render_paginate_sem_defer: [], render_count_sem_defer: [] };

for (const f of files) {
  const src = readFileSync(join(ROOT, f), 'utf8');
  const lines = src.split('\n');

  lines.forEach((line, i) => {
    if (!/->(simplePaginate|paginate)\(/.test(line)) return;
    const start = Math.max(0, i - LOOKBACK);
    const window = lines.slice(start, i + 1).join('\n');
    if (!/->with\(|->withCount\(|select\(|->pluck\(/.test(window)) {
      counts.paginate_sem_eager++;
      offenders.paginate_sem_eager.push(`${f}:${i + 1}`);
    }
  });

  const hasRender = src.includes('Inertia::render');
  const hasDefer = src.includes('Inertia::defer');
  if (hasRender && !hasDefer) {
    if (/->(simplePaginate|paginate)\(/.test(src)) {
      counts.render_paginate_sem_defer++;
      offenders.render_paginate_sem_defer.push(f);
    }
    if (/->count\(\)/.test(src)) {
      counts.render_count_sem_defer++;
      offenders.render_count_sem_defer.push(f);
    }
  }
}

const writeMode = process.argv.includes('--write-baseline');

if (writeMode) {
  const baseline = {
    _meta: {
      generated_at: new Date().toISOString(),
      gate: 'perf-static-guard (Onda 4 lente 5b — AUDITORIA-PERFORMANCE-2026-07, ratchet advisory)',
      nota: 'Contadores heurísticos de TENDÊNCIA (falsos positivos catalogados no header do guard). Falha só se algum contador SUBIR.',
      refs: ['memory/governance/AUDITORIA-PERFORMANCE-2026-07.md', 'memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md'],
    },
    counts,
  };
  writeFileSync(BASELINE_PATH, JSON.stringify(baseline, null, 2) + '\n');
  console.log('[perf-static-guard] baseline gravado:', counts);
  process.exit(0);
}

if (!existsSync(BASELINE_PATH)) {
  console.error('[perf-static-guard] baseline ausente — rode com --write-baseline primeiro.');
  process.exit(1);
}
const baseline = JSON.parse(readFileSync(BASELINE_PATH, 'utf8')).counts;
let failed = false;
for (const key of Object.keys(counts)) {
  const was = baseline[key] ?? Infinity;
  const now = counts[key];
  const status = now > was ? 'PIOROU ❌' : now < was ? 'melhorou ✅ (rode --write-baseline pra travar)' : 'ok';
  console.log(`[perf-static-guard] ${key}: baseline=${was} atual=${now} — ${status}`);
  if (now > was) {
    failed = true;
    console.log(`  ofensores atuais:\n    ${offenders[key].slice(-10).join('\n    ')}`);
  }
}
process.exit(failed ? 1 : 0);
