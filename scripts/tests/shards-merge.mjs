#!/usr/bin/env node
// shards-merge.mjs — funde os summaries junit POR SHARD numa medição da noite (SDD P04
// · ADR 0279). A noite = UNIÃO dos shards que COMPLETARAM. Shard morto (summary ausente/
// {invalid}/incoerente) perde SÓ ele, NÃO a noite — cura do bug FV-F1 "junit 0b zera a
// noite". Parte NODE (CI, puro fs); o bash que roda cada shard + junit-summary é OUTRO chip.
//
// GUARD ANTI-MASCARAMENTO: se algum shard não completou, all_shards_measured=false. Medição
// parcial NÃO pode ser vendida como completa — senão o floor derivado dela vira burn-down
// FAKE (shard morto derruba os arquivos-que-falham dele → floor parece menor → falso
// progresso). floor-compute.mjs v2 lê o flag e trata janela parcial como not_yet_measured.
//
// Uso: node scripts/tests/shards-merge.mjs --shards-dir <dir> \
//        (--plan <plan.json> | --shards-total N) [--out <merged-summary.json>]
//   espera <shards-dir>/shard-<i>.summary.json (junit-summary/v1) por shard i em [0,N).
import { readFileSync, writeFileSync, existsSync, realpathSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const arg = (k, d) => { const i = process.argv.indexOf(k); return i >= 0 ? process.argv[i + 1] : d; };

// shard vivo = summary coerente com testes (não ausente/morto/{invalid}/incoerente).
export function isLiveShard(s) {
  return !!s && !s.invalid && s.coherent && !!s.n_testcases && Array.isArray(s.files);
}

// carrega 1 summary de shard; retorna null se morto/ausente/incoerente (= não completou).
export function loadShardSummary(p) {
  if (!existsSync(p)) return null;
  let s; try { s = JSON.parse(readFileSync(p, 'utf8')); } catch { return null; }
  return isLiveShard(s) ? s : null;
}

// funde shard-0..N-1 → medição da noite. `load(i)` retorna o summary do shard i ou null.
// Re-valida cada shard com isLiveShard (NÃO confia no loader): shard incoerente jamais
// infla a medição — senão um flush parcial vira floor mascarado.
export function mergeShards(shardsTotal, load) {
  const byFile = new Map();
  const totals = { passed: 0, failed: 0, errors: 0, skipped: 0 };
  const completed = [], missing = [];
  let n = 0;
  for (let i = 0; i < shardsTotal; i++) {
    const s = load(i);
    if (!isLiveShard(s)) { missing.push(i); continue; } // shard morto perde SÓ ele — a noite segue
    completed.push(i);
    n += s.n_testcases;
    for (const k of ['passed', 'failed', 'errors', 'skipped']) totals[k] += (s.totals?.[k] || 0);
    for (const f of s.files) {
      const e = byFile.get(f.file) || { file: f.file, tests: 0, passed: 0, failed: 0, errors: 0, skipped: 0 };
      for (const k of ['tests', 'passed', 'failed', 'errors', 'skipped']) e[k] += (f[k] || 0);
      byFile.set(f.file, e);
    }
  }
  return {
    schema: 'fullsuite-summary-sharded/v1',
    coherent: completed.length > 0, // ≥1 shard vivo → floor-compute considera a noite
    all_shards_measured: missing.length === 0,
    shards_total: shardsTotal,
    shards_completed: completed,
    shards_missing: missing,
    n_testcases: n,
    n_testcases_declared: n,
    totals,
    files: [...byFile.values()].sort((a, b) => a.file.localeCompare(b.file)),
  };
}

const isMain = (() => { try { return realpathSync(process.argv[1]) === fileURLToPath(import.meta.url); } catch { return false; } })();
if (isMain) {
  const dir = arg('--shards-dir', process.cwd());
  const planPath = arg('--plan', null);
  const total = planPath ? (JSON.parse(readFileSync(planPath, 'utf8')).n_shards || 0) : Math.max(0, parseInt(arg('--shards-total', '0'), 10) || 0);
  if (!total) { console.error('shards-merge: informe --plan <plan.json> ou --shards-total N (>0)'); process.exit(1); }
  const merged = mergeShards(total, (i) => loadShardSummary(join(dir, `shard-${i}.summary.json`)));
  const out = arg('--out', null);
  if (out) writeFileSync(out, JSON.stringify(merged, null, 2) + '\n');
  console.log(JSON.stringify(merged, null, 2));
  console.error(`shards-merge → completos=${merged.shards_completed.length}/${total} · all_shards_measured=${merged.all_shards_measured}${merged.shards_missing.length ? ` · FALTANDO shard(s) ${merged.shards_missing.join(',')} (medição PARCIAL)` : ''}`);
}
