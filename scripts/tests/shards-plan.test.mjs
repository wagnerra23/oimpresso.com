#!/usr/bin/env node
// Teste do shards-plan (SDD P04 · ADR 0279). Prova:
//   - discoverTestDirs acha só dirs com *Test.php direto (com contagem)
//   - planShards particiona DISJUNTO e cobre TUDO, e é DETERMINÍSTICO
//   - verifyPlan (universe-gate) morde quando um dir some (teste perdido) ou duplica
import { discoverTestDirs, planShards, buildPlan, verifyPlan } from './shards-plan.mjs';
import { mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

let fails = 0;
const ok = (c, m) => { if (c) console.log(`  ✓ ${m}`); else { console.error(`  ✗ ${m}`); fails++; } };

const root = join(tmpdir(), `shards-plan-test-${process.pid}`);
const mk = (d, files) => { const abs = join(root, d); mkdirSync(abs, { recursive: true }); for (const f of files) writeFileSync(join(abs, f), '<?php'); };

try {
  mk('tests/Feature', ['AlphaTest.php', 'BetaTest.php']);       // 2 arquivos
  mk('tests/Unit', ['GammaTest.php']);                          // 1
  mk('Modules/X/Tests/Feature', ['DeltaTest.php']);             // 1
  mk('tests/Feature/Sub', ['EpsilonTest.php']);                 // 1 (dir aninhado próprio)
  mk('tests/_helpers', ['NotATestHelper.php']);                 // sem *Test.php → ignorado

  const dirs = discoverTestDirs(['tests', 'Modules'], root);
  ok(dirs.length === 4, `4 dirs de teste descobertos (helper sem *Test.php ignorado) — got ${dirs.length}`);
  const feat = dirs.find((d) => d.dir === 'tests/Feature');
  ok(feat && feat.files === 2, 'tests/Feature conta 2 arquivos *Test.php diretos');

  const plan = buildPlan(['tests', 'Modules'], 3, root);
  const allInShards = plan.shards.flatMap((s) => s.dirs).sort();
  ok(allInShards.length === 4, 'partição cobre os 4 dirs (nenhum some)');
  ok(new Set(allInShards).size === 4, 'partição DISJUNTA (nenhum dir em 2 shards)');
  ok(JSON.stringify(allInShards) === JSON.stringify(plan.universe.slice().sort()), 'união dos shards == universo');

  // determinismo: mesmo input → mesmo plano
  const p2 = buildPlan(['tests', 'Modules'], 3, root);
  ok(JSON.stringify(plan.shards) === JSON.stringify(p2.shards), 'plano DETERMINÍSTICO (2 runs idênticos)');

  // universe-gate: plano íntegro passa
  ok(verifyPlan(plan, plan.universe).ok, 'verifyPlan OK no plano íntegro');
  // universe-gate: dropar um dir de um shard → missing (teste perdido no particionamento)
  const dropped = { ...plan, shards: plan.shards.map((s, i) => i === 0 ? { ...s, dirs: s.dirs.slice(1) } : s) };
  const dropList = plan.shards[0].dirs.slice(0, 1);
  const vDrop = verifyPlan(dropped, plan.universe);
  ok(!vDrop.ok && vDrop.missing.length >= 1 && vDrop.missing[0] === dropList[0], `verifyPlan MORDE dir perdido (${dropList[0]})`);
  // universe-gate: duplicar um dir → duplicated
  const dup = { ...plan, shards: plan.shards.map((s, i) => i === 1 ? { ...s, dirs: [...s.dirs, plan.universe[0]] } : s) };
  ok(!verifyPlan(dup, plan.universe).ok, 'verifyPlan MORDE dir duplicado');
} finally { rmSync(root, { recursive: true, force: true }); }

console.log(fails === 0 ? '\n  shards-plan (SDD P04): OK\n' : `\n  shards-plan: ${fails} FALHA(S)\n`);
process.exit(fails === 0 ? 0 : 1);
