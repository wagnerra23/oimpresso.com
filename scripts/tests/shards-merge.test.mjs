#!/usr/bin/env node
// Teste do shards-merge (SDD P04 · ADR 0279). Prova o DoD:
//   - crash de 1 shard PRESERVA a medição dos outros (união dos completos; noite não zera)
//   - shard morto → all_shards_measured=false + shards_missing (guard anti-mascaramento)
//   - shard vivo mas incoerente/{invalid} conta como morto (não infla a medição)
//   - todos completos → all_shards_measured=true; todos mortos → coherent=false
import { mergeShards, loadShardSummary } from './shards-merge.mjs';
import { mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

let fails = 0;
const ok = (c, m) => { if (c) console.log(`  ✓ ${m}`); else { console.error(`  ✗ ${m}`); fails++; } };

// summary de shard sintético (junit-summary/v1). failing[] = arquivos que falham nesse shard.
const shard = (failing, passed = 5, extra = {}) => ({
  coherent: true, n_testcases: passed + failing.length,
  totals: { passed, failed: failing.length, errors: 0, skipped: 0 },
  files: failing.map((f) => ({ file: f, tests: 1, passed: 0, failed: 1, errors: 0, skipped: 0 })),
  ...extra,
});

// 4 shards: 0→{A}, 1→{B}, 2→{C}, 3→{D}
const shards = { 0: shard(['A']), 1: shard(['B']), 2: shard(['C']), 3: shard(['D']) };

// TODOS completos
let m = mergeShards(4, (i) => shards[i]);
ok(m.all_shards_measured === true, 'todos completos → all_shards_measured=true');
ok(m.shards_missing.length === 0 && m.shards_completed.length === 4, '4/4 shards completos');
ok(m.files.map((f) => f.file).join(',') === 'A,B,C,D', 'união dos 4 shards = A,B,C,D');
ok(m.totals.failed === 4 && m.coherent === true, 'totais somados + coherent');

// CRASH do shard 2 (retorna null) — DoD: os outros 3 são PRESERVADOS
m = mergeShards(4, (i) => (i === 2 ? null : shards[i]));
ok(m.all_shards_measured === false, 'shard morto → all_shards_measured=false (PARCIAL)');
ok(m.shards_missing.length === 1 && m.shards_missing[0] === 2, 'shards_missing=[2]');
ok(m.files.map((f) => f.file).join(',') === 'A,B,D', 'medição dos shards vivos PRESERVADA (A,B,D; a noite NÃO zerou)');
ok(m.coherent === true, 'ainda coherent (≥1 shard vivo) → floor-compute considera a noite (parcial)');

// shard incoerente conta como morto (não infla)
m = mergeShards(2, (i) => (i === 0 ? shard(['A']) : shard(['Z'], 5, { coherent: false })));
ok(m.all_shards_measured === false && m.files.map((f) => f.file).join(',') === 'A', 'shard incoerente = morto (só A entra)');

// todos mortos → coherent=false, n_testcases=0 (noite realmente morta)
m = mergeShards(3, () => null);
ok(m.coherent === false && m.n_testcases === 0, 'todos mortos → coherent=false, n=0');

// loadShardSummary: ausente/malformado/{invalid}/0-testcase → null
const root = join(tmpdir(), `shards-merge-test-${process.pid}`); mkdirSync(root, { recursive: true });
try {
  ok(loadShardSummary(join(root, 'nope.json')) === null, 'summary ausente → null');
  writeFileSync(join(root, 'bad.json'), '{not json'); ok(loadShardSummary(join(root, 'bad.json')) === null, 'malformado → null');
  writeFileSync(join(root, 'inv.json'), JSON.stringify({ invalid: true, reason: 'xml_0_bytes' })); ok(loadShardSummary(join(root, 'inv.json')) === null, 'marcador {invalid} → null (FV-F4)');
  writeFileSync(join(root, 'ok.json'), JSON.stringify(shard(['A']))); ok(loadShardSummary(join(root, 'ok.json'))?.n_testcases === 6, 'summary válido → carregado');
} finally { rmSync(root, { recursive: true, force: true }); }

console.log(fails === 0 ? '\n  shards-merge (SDD P04): OK\n' : `\n  shards-merge: ${fails} FALHA(S)\n`);
process.exit(fails === 0 ? 0 : 1);
