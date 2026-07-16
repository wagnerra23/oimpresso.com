#!/usr/bin/env node
// floor-compute.mjs — write-side do floor (ADR 0279 Opção A · PR-2 · US-GOV-018).
//
// Lê os últimos N runs VÁLIDOS do nightly CT100 (summary.json coherent + n_testcases>0;
// run morto = junit 0 bytes = NÃO conta) e computa o FLOOR = INTERSEÇÃO dos
// arquivos-que-falham (failed>0 || errors>0) entre ≥2 runs (def US-GOV-018 — o número
// de 1 run é não-determinístico; só a interseção é estável). Emite governance/
// nightly-floor.json no schema do ADR 0279. <2 runs válidos → floor_count null →
// o read-side (sdd-scorecard.mjs) trata como not_yet_measured (nunca mente 0).
//
// Determinístico: para o MESMO conjunto de runs, mesmo output (sem timestamp no corpo).
//
// v2 SHARD-AWARE (SDD P04): quando o run é um summary shardeado (fullsuite-summary-
// sharded/v1, produzido por shards-merge.mjs), lê all_shards_measured. Se algum run da
// janela foi PARCIAL (shard morto), o floor NÃO é vendido — floor_count=null +
// all_shards_measured=false (guard anti-mascaramento: run parcial não vira burn-down
// fake). Summary legado sem o campo = completo (back-compat).
// Uso: node scripts/tests/floor-compute.mjs [--runs <dir>] [--window N] [--out <file>]
import { readdirSync, readFileSync, existsSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { createHash } from 'node:crypto';

const arg = (k, d) => { const i = process.argv.indexOf(k); return i >= 0 ? process.argv[i + 1] : d; };
const RUNS = arg('--runs', '/opt/oimpresso-fullsuite/runs');
const WINDOW = Math.max(2, parseInt(arg('--window', '3'), 10) || 3);
const OUT = arg('--out', join(process.cwd(), 'governance', 'nightly-floor.json'));

// runs válidos, em ordem cronológica (nome = YYYYMMDD-HHMMSS)
export function validRuns(runsDir = RUNS) {
  if (!existsSync(runsDir)) return [];
  const names = readdirSync(runsDir, { withFileTypes: true })
    .filter((e) => e.isDirectory() && /^\d{8}-\d{6}$/.test(e.name))
    .map((e) => e.name).sort();
  const out = [];
  for (const name of names) {
    const sp = join(runsDir, name, 'summary.json');
    if (!existsSync(sp)) continue; // run morto (junit 0b → sem summary) não conta
    let s; try { s = JSON.parse(readFileSync(sp, 'utf8')); } catch { continue; }
    if (s.invalid) continue; // marcador explicito FV-F4 (US-GOV-045) — run morto declarado
    if (!s.coherent || !s.n_testcases || !Array.isArray(s.files)) continue;
    const failingFiles = s.files.filter((f) => (f.failed || 0) > 0 || (f.errors || 0) > 0).map((f) => f.file);
    // v2 shard-aware (fullsuite-summary-sharded/v1): all_shards_measured=false ⇒ a noite
    // foi PARCIAL (algum shard morto). Ausente ⇒ summary não-shardeado legado = completo.
    const allShardsMeasured = s.all_shards_measured !== false;
    let sha = null;
    const lp = join(runsDir, name, 'run.log');
    if (existsSync(lp)) { const m = readFileSync(lp, 'utf8').match(/sha=([0-9a-f]{7,40})/); if (m) sha = m[1]; }
    out.push({ ts: name, sha, totals: s.totals || {}, failingFiles, allShardsMeasured });
  }
  return out;
}

// floor = arquivos que falham em TODOS os runs da janela (interseção)
export function computeFloor(runs, window = WINDOW) {
  const w = runs.slice(-window);
  const runsMeta = w.map((r) => ({ sha: r.sha, ts: r.ts, failed: r.totals.failed ?? null, errors: r.totals.errors ?? null, skipped: r.totals.skipped ?? null, all_shards_measured: r.allShardsMeasured !== false }));
  // GUARD ANTI-MASCARAMENTO (v2): se QUALQUER run da janela foi parcial (shard morto),
  // a interseção pode cair um arquivo que só faltou por o shard dele ter crashado →
  // floor falso-menor = burn-down FAKE. Nesse caso NÃO vendemos o número: floor_count
  // = null + all_shards_measured=false, tratado como not_yet_measured pelo read-side.
  const partial = w.filter((r) => r.allShardsMeasured === false).map((r) => r.ts);
  if (w.length < 2 || partial.length) {
    return {
      schema: 'nightly-floor/v1 (ADR 0279)', floor_count: null, floor_files_hash: null,
      all_shards_measured: partial.length === 0, partial_runs: partial,
      runs: runsMeta,
      computed_at: w.length ? w[w.length - 1].ts : null, intersection_of: w.length,
      note: partial.length
        ? `medicao PARCIAL: shard(s) faltando em ${partial.length} run(s) da janela — read-side trata como not_yet_measured (anti-mascaramento; floor nao pode ser vendido como completo)`
        : 'aguardando >=2 nightlies validos; read-side trata como not_yet_measured',
    };
  }
  let inter = new Set(w[0].failingFiles);
  for (const r of w.slice(1)) { const s = new Set(r.failingFiles); inter = new Set([...inter].filter((x) => s.has(x))); }
  const floorFiles = [...inter].sort();
  return {
    schema: 'nightly-floor/v1 (ADR 0279)',
    floor_count: floorFiles.length,
    floor_files_hash: createHash('sha256').update(floorFiles.join('\n')).digest('hex').slice(0, 16),
    all_shards_measured: true, partial_runs: [],
    runs: runsMeta,
    computed_at: w[w.length - 1].ts,
    intersection_of: w.length,
    note: `floor = intersecao de ${w.length} runs validos (arquivos com failed>0||errors>0 em TODOS)`,
  };
}

// CLI (só quando executado direto; importável p/ teste)
import { realpathSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
const isMain = (() => { try { return realpathSync(process.argv[1]) === fileURLToPath(import.meta.url); } catch { return false; } })();
if (isMain) {
  const floor = computeFloor(validRuns(RUNS), WINDOW);
  writeFileSync(OUT, JSON.stringify(floor, null, 2) + '\n', 'utf8');
  console.log(`floor-compute → ${OUT}: floor_count=${floor.floor_count} (intersection_of=${floor.intersection_of})`);
}
