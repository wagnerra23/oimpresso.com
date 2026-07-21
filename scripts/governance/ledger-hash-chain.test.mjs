#!/usr/bin/env node
// @ts-check
// ledger-hash-chain.test.mjs — selftest hermético do transparency-log do ledger.
// Prova que a corrente MORDE: adulterar uma entry selada, o conteúdo de um checkpoint,
// a ordem da corrente, ou remover uma entry → --verify --check sai 1. Append legítimo na
// cauda NÃO falha (só o selado é tamper-evidente). Node puro, sem deps. Registrado em
// .github/workflows/governance-script-tests.yml (senão selftest-registry-check acusa órfão).

import { mkdtempSync, writeFileSync, readFileSync, rmSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import {
  canonical, entryHash, foldRoot, buildCheckpoint, checkpointHash, verify,
} from './ledger-hash-chain.mjs';

let fails = 0;
const check = (n, c, extra = '') => { console.log((c ? '[OK]   ' : '[FAIL] ') + n + (c ? '' : '  → ' + extra)); if (!c) fails++; };

const ME = fileURLToPath(new URL('./ledger-hash-chain.mjs', import.meta.url));
const entriesFixture = () => ([
  { pr: 1, lote_id: 'A', tipo: 'anchors', veredito: 'aprovado', error_rate_pct: 0 },
  { tipo: 'prosa', lote_id: 'B', pr: 2, veredito: 'aprovado', error_rate_pct: 1.5 }, // ordem de chaves DIFERENTE de propósito
  { pr: 3, lote_id: 'C', tipo: 'anchors', veredito: 'reprovado', error_rate_pct: 12.5 },
]);

// ── 1. canonical é ordem-independente ─────────────────────────────────────────
check('canonical ignora ordem de chaves', canonical({ a: 1, b: 2 }) === canonical({ b: 2, a: 1 }));
check('canonical difere por VALOR', canonical({ a: 1 }) !== canonical({ a: 2 }));
check('entryHash determinístico (mesmo objeto → mesma hash)', entryHash({ x: 1, y: 2 }) === entryHash({ y: 2, x: 1 }));

// ── 2. foldRoot ────────────────────────────────────────────────────────────────
const es = entriesFixture();
check('foldRoot(_, 0) = semente (nada foldado)', foldRoot(es, 0) === foldRoot([], 0));
check('foldRoot cresce com mais entries', foldRoot(es, 2) !== foldRoot(es, 3));

// ── 3. buildCheckpoint genesis + incremental ─────────────────────────────────
const cp0 = buildCheckpoint({ entries: es, checkpoints: [], data: '2026-07-21', critico: 'fable-5', rubricaRef: 'r.md', rubricaSha: 'abc', historico: true });
check('genesis: seq=0, cobre_de=0, cobre_ate=3, prev=""', cp0.seq === 0 && cp0.cobre_de === 0 && cp0.cobre_ate === 3 && cp0.prev_checkpoint_hash === '', JSON.stringify(cp0));
check('genesis: checkpoint_hash bate com recompute', cp0.checkpoint_hash === checkpointHash(cp0));
check('genesis: proveniência pinada (crítico + rubrica_sha)', cp0.provenancia.critico === 'fable-5' && cp0.provenancia.rubrica_sha256 === 'abc' && cp0.provenancia.historico === true);

const es2 = [...es, { pr: 4, lote_id: 'D', tipo: 'prosa', veredito: 'aprovado', error_rate_pct: 0 }];
const cp1 = buildCheckpoint({ entries: es2, checkpoints: [cp0], data: '2026-07-22', critico: 'gpt-5.6-sol', rubricaRef: 'r.md', rubricaSha: 'def' });
check('incremental: cobre_de=3, cobre_ate=4, prev=hash(cp0)', cp1.cobre_de === 3 && cp1.cobre_ate === 4 && cp1.prev_checkpoint_hash === cp0.checkpoint_hash, JSON.stringify(cp1));
check('buildCheckpoint sem entry nova → null', buildCheckpoint({ entries: es2, checkpoints: [cp0, cp1], data: '2026-07-23' }) === null);

// ── 4. verify: caso íntegro ──────────────────────────────────────────────────
const okDoc = { checkpoints: [cp0, cp1] };
let r = verify(es2, okDoc);
check('verify íntegro: ok=true, 0 problemas', r.ok && r.problems.length === 0, JSON.stringify(r.problems));
check('verify íntegro: sealed=4, unpinned=0', r.sealed === 4 && r.unpinned === 0);

// ── 5. verify MORDE cada vetor de adulteração ─────────────────────────────────
// (a) adulterar uma entry SELADA (muda o veredito de C)
const tampered = JSON.parse(JSON.stringify(es2));
tampered[2].veredito = 'aprovado';
r = verify(tampered, okDoc);
check('adulterar entry selada → entries_root morde', !r.ok && r.problems.some((p) => p.tipo === 'entries_root'), JSON.stringify(r.problems));

// (b) adulterar o CONTEÚDO de um checkpoint (muda a data sem re-hashear)
const badCp = JSON.parse(JSON.stringify(okDoc));
badCp.checkpoints[0].data = '2020-01-01';
r = verify(es2, badCp);
check('adulterar conteúdo do checkpoint → checkpoint_hash morde', !r.ok && r.problems.some((p) => p.tipo === 'checkpoint_hash'), JSON.stringify(r.problems));

// (c) quebrar a corrente (prev_checkpoint_hash errado no cp1)
const badChain = JSON.parse(JSON.stringify(okDoc));
badChain.checkpoints[1].prev_checkpoint_hash = 'deadbeef';
r = verify(es2, badChain);
check('quebrar a corrente → corrente morde', !r.ok && r.problems.some((p) => p.tipo === 'corrente'), JSON.stringify(r.problems));

// (d) remover uma entry selada (append-only violado)
r = verify(es2.slice(0, 2), okDoc);
check('remover entry selada → entries_faltando/entries_root morde', !r.ok && r.problems.some((p) => p.tipo === 'entries_faltando' || p.tipo === 'entries_root'), JSON.stringify(r.problems));

// ── 6. append legítimo na cauda NÃO falha (só o selado é tamper-evidente) ─────
const es3 = [...es2, { pr: 5, lote_id: 'E', tipo: 'anchors', veredito: 'aprovado', error_rate_pct: 0 }];
r = verify(es3, okDoc);
check('append na cauda: ok=true, unpinned=1 (Rekor: só selado é evidente)', r.ok && r.unpinned === 1, JSON.stringify(r));

// ── 7. rubrica_drift é ADVISORY (não vira problema) ──────────────────────────
r = verify(es2, okDoc, { rubricaCurrentSha: 'zzz-nova-versao' });
check('rubrica evoluída → drift reportado, NUNCA problema', r.ok && r.rubrica_drift.length === 2, JSON.stringify(r.rubrica_drift));

// ── 8. E2E via CLI (build genesis + verify exit 0; tamper + --check exit 1) ───
const tmp = mkdtempSync(join(tmpdir(), 'ledger-hc-'));
mkdirSync(join(tmp, 'governance'), { recursive: true });
const ledgerPath = join(tmp, 'governance', 'ledger.json');
const cpPath = join(tmp, 'governance', 'checkpoints.json');
const rubricaPath = join(tmp, 'rubrica.md');
writeFileSync(rubricaPath, '# rubrica v1\n');
writeFileSync(ledgerPath, JSON.stringify({ _meta: { x: 1 }, entries: entriesFixture() }, null, 2));
const run = (...a) => spawnSync(process.execPath, [ME, ...a], { encoding: 'utf8' });

let out = run('--build', '--data', '2026-07-21', '--genesis', '--critico', 'fable-5', '--rubrica', rubricaPath, '--ledger', ledgerPath, '--checkpoints', cpPath);
check('CLI --build genesis: exit 0', out.status === 0, out.stderr || out.stdout);
const built = JSON.parse(readFileSync(cpPath, 'utf8'));
check('CLI --build: gravou 1 checkpoint genesis historico', built.checkpoints.length === 1 && built.checkpoints[0].provenancia.historico === true);

out = run('--build', '--data', 'nao-e-data', '--ledger', ledgerPath, '--checkpoints', cpPath);
check('CLI --build sem data válida: exit 2 (proíbe data gerada)', out.status === 2, String(out.status));

out = run('--verify', '--check', '--ledger', ledgerPath, '--checkpoints', cpPath);
check('CLI --verify --check íntegro: exit 0', out.status === 0, out.stdout);

// tamper: reescreve o veredito de uma entry selada
const led = JSON.parse(readFileSync(ledgerPath, 'utf8'));
led.entries[2].veredito = 'aprovado';
writeFileSync(ledgerPath, JSON.stringify(led, null, 2));
out = run('--verify', '--check', '--ledger', ledgerPath, '--checkpoints', cpPath);
check('CLI --verify --check pós-tamper: exit 1 (morde)', out.status === 1, out.stdout);
out = run('--verify', '--ledger', ledgerPath, '--checkpoints', cpPath);
check('CLI --verify (advisory) pós-tamper: exit 0 (só reporta)', out.status === 0, String(out.status));

rmSync(tmp, { recursive: true, force: true });

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — transparency-log morde tamper retroativo, solta append legítimo (Rekor-style).');
process.exit(fails ? 1 : 0);
