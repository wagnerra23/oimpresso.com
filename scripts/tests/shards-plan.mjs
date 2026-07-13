#!/usr/bin/env node
// shards-plan.mjs — particiona a suíte Pest em N shards POR DIRETÓRIO (determinístico).
//
// GARGALO-MÃE (SDD P04 · ADR 0279): um crash/OOM no meio da suíte zera o junit.xml
// INTEIRO da noite (0 bytes em 9/10 noites) → floor não-medível mesmo com 90% passando.
// Rodar em SHARDS isola o dano — o bash do CT100 roda cada shard com --log-junit próprio;
// crash em 1 shard perde SÓ ele (shards-merge funde a UNIÃO dos que completaram; a noite
// não zera). Este é a parte NODE (CI, puro fs): emite o PLANO (dirs por shard). O loop
// bash que roda os shards é OUTRO chip. Unidade de shard = dir com *Test.php direto.
//
// Uso:
//   node scripts/tests/shards-plan.mjs --shards 6 [--roots tests,Modules] [--exclude tests/Browser,...] [--out plan.json]
//   node scripts/tests/shards-plan.mjs --verify --roots <tree> [--exclude ...] [--plan <plan.json>]
//     ↑ universe-gate: prova que os shards cobrem EXATAMENTE o universo descoberto —
//       nenhum dir some (=teste sumido no particionamento) nem duplica. Exit 1 se violar.
//
// --exclude <prefixos-csv>: poda subárvores NÃO-rodáveis no nightly (dir cujo path
// relativo == prefixo OU começa com prefixo+'/'). O phpunit.xml (testsuites) é a fonte
// do que a suíte roda de fato; a descoberta por filesystem é MAIS ampla e pega dirs que
// o `pest` (sem args) nunca rodou — ex tests/Browser (Pest Browser exige Playwright, que
// a imagem do nightly não tem → PlaywrightNotInstalledException mata o shard antes do 1º
// teste) e tests/governance-fixtures (testes SINTÉTICOS bad/good que alimentam os gates,
// não são testes reais). Sem a poda, cada shard que herda um desses dir morre 0-byte
// (provado na 1ª nightly real sharded 2026-07-12: 3 shards mortos só por tests/Browser).
import { readdirSync, existsSync, writeFileSync, readFileSync, realpathSync } from 'node:fs';
import { join, relative, isAbsolute } from 'node:path';
import { fileURLToPath } from 'node:url';

const arg = (k, d) => { const i = process.argv.indexOf(k); return i >= 0 ? process.argv[i + 1] : d; };

// dirs que contêm ao menos um *Test.php DIRETO (unidade de shard), com contagem de
// arquivos pra balancear. Recursivo, mas conta só o arquivo direto de cada dir.
export function discoverTestDirs(roots, base = process.cwd(), exclude = []) {
  const found = [];
  const isExcluded = (rel) => exclude.some((p) => rel === p || rel.startsWith(p + '/'));
  const walk = (abs) => {
    const rel = relative(base, abs).replace(/\\/g, '/');
    if (rel && isExcluded(rel)) return; // poda a subárvore não-rodável inteira
    let entries; try { entries = readdirSync(abs, { withFileTypes: true }); } catch { return; }
    let n = 0;
    for (const e of entries) {
      if (e.isDirectory()) walk(join(abs, e.name));
      else if (/Test\.php$/.test(e.name)) n++;
    }
    if (n > 0) found.push({ dir: rel, files: n });
  };
  for (const r of roots) { const abs = isAbsolute(r) ? r : join(base, r); if (existsSync(abs)) walk(abs); }
  return found.sort((a, b) => a.dir.localeCompare(b.dir));
}

// LPT bin-packing: maior primeiro (desempate por nome → determinístico), cada dir vai
// pro shard menos carregado (desempate por índice). Partição DISJUNTA que cobre tudo.
export function planShards(dirs, nShards) {
  const shards = Array.from({ length: Math.max(1, nShards) }, (_, index) => ({ index, dirs: [], file_count: 0 }));
  const ordered = [...dirs].sort((a, b) => b.files - a.files || a.dir.localeCompare(b.dir));
  for (const d of ordered) {
    const t = shards.reduce((min, s) => (s.file_count < min.file_count ? s : min));
    t.dirs.push(d.dir); t.file_count += d.files;
  }
  for (const s of shards) s.dirs.sort();
  return shards;
}

export function buildPlan(roots, nShards, base = process.cwd(), exclude = []) {
  const dirs = discoverTestDirs(roots, base, exclude);
  return {
    schema: 'shards-plan/v1',
    n_shards: Math.max(1, nShards),
    total_dirs: dirs.length,
    total_files: dirs.reduce((a, d) => a + d.files, 0),
    excluded: exclude,
    universe: dirs.map((d) => d.dir),
    shards: planShards(dirs, nShards),
  };
}

// universe-gate: o conjunto de shards cobre EXATAMENTE `universe` (nenhum some, nenhum
// duplica, nenhum fantasma). missing = teste que sumiria no particionamento (o vetor).
export function verifyPlan(plan, universe) {
  const seen = new Map();
  for (const s of plan.shards) for (const d of s.dirs) seen.set(d, (seen.get(d) || 0) + 1);
  const uni = new Set(universe);
  const missing = universe.filter((d) => !seen.has(d));
  const duplicated = [...seen].filter(([, c]) => c > 1).map(([d]) => d);
  const extra = [...seen.keys()].filter((d) => !uni.has(d));
  return { ok: !missing.length && !duplicated.length && !extra.length, missing, duplicated, extra };
}

const isMain = (() => { try { return realpathSync(process.argv[1]) === fileURLToPath(import.meta.url); } catch { return false; } })();
if (isMain) {
  const roots = arg('--roots', 'tests,Modules').split(',').map((s) => s.trim()).filter(Boolean);
  const exclude = arg('--exclude', '').split(',').map((s) => s.trim()).filter(Boolean);
  const N = Math.max(1, parseInt(arg('--shards', '6'), 10) || 6);
  if (process.argv.includes('--verify')) {
    const universe = discoverTestDirs(roots, process.cwd(), exclude).map((d) => d.dir);
    const planPath = arg('--plan', null);
    const plan = planPath ? JSON.parse(readFileSync(planPath, 'utf8')) : buildPlan(roots, N, process.cwd(), exclude);
    const v = verifyPlan(plan, universe);
    if (!v.ok) {
      console.error(`universe-gate FALHOU — ${v.missing.length} dir(s) PERDIDO(s), ${v.duplicated.length} duplicado(s), ${v.extra.length} fantasma(s):`);
      for (const d of v.missing) console.error(`  💀 PERDIDO (no universo, fora do plano): ${d}`);
      for (const d of v.duplicated) console.error(`  ⚠️ DUPLICADO (em >1 shard): ${d}`);
      for (const d of v.extra) console.error(`  ⚠️ FANTASMA (no plano, fora do universo): ${d}`);
      process.exit(1);
    }
    console.log(`universe-gate OK — ${universe.length} dir(s) de teste cobertos por ${plan.shards.length} shard(s), 0 perdidos.`);
    process.exit(0);
  }
  const plan = buildPlan(roots, N, process.cwd(), exclude);
  const out = arg('--out', null);
  if (out) writeFileSync(out, JSON.stringify(plan, null, 2) + '\n');
  console.log(JSON.stringify(plan, null, 2));
}
