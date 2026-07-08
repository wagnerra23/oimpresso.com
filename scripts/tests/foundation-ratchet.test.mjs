#!/usr/bin/env node
// TESTE DE REGRESSÃO — prova que a catraca da fundação MORDE (plano SDD §2: "quem vigia os vigias").
// Fixtures boa/ruim exercitam o MESMO code path do gate real (--root/--baseline). Sem rede, sem MySQL.
// Rodar: node scripts/tests/foundation-ratchet.test.mjs — exit 0 = todos passam, exit 1 = regressão.

import { spawnSync } from 'node:child_process';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(__dirname, 'foundation-ratchet.mjs');
const FIX = join(__dirname, 'fixtures', 'foundation-ratchet');

let fails = 0;
let ran = 0;
const check = (name, cond) => { ran++; console.log(`${cond ? '[OK]' : '[FAIL]'} ${name}`); if (!cond) fails++; };
const exec = (a, env = {}) => spawnSync('node', [SCRIPT, ...a], { encoding: 'utf8', env: { ...process.env, GITHUB_STEP_SUMMARY: '', ...env } });
const run = (root, extra = [], env = {}) => exec(['--root', join(FIX, root), '--baseline', join(FIX, root, 'baseline.json'), ...extra], env);

// 1. Fixture BOA: quarentena com razão, contadores == baseline → verde.
check('boa → exit 0', run('good').status === 0);

// 2. Fixture RUIM: 3 contadores acima do baseline + quarentena sem razão → vermelho acusando certo.
const bad = run('bad');
const out = (bad.stdout || '') + (bad.stderr || '');
check('ruim → exit 1', bad.status === 1);
check('ruim → acusa n_refresh_database 0→1', /n_refresh_database 0→1/.test(out));
check('ruim → acusa n_business_first 0→1', /n_business_first 0→1/.test(out));
check('ruim → acusa quarentena SEM quarantine-reason', /SEM quarantine-reason/.test(out));

// 3. --json parseável e fiel à medição da fixture boa.
const j = JSON.parse(run('good', ['--json']).stdout);
check('--json: boa mede 1/1/1', j.counters.n_quarantine === 1 && j.counters.n_refresh_database === 1 && j.counters.n_business_first === 1);

// 4. Catraca de ESCRITA: --write recusa SUBIR sem --force (baseline intacto); --force grava.
const tmp = mkdtempSync(join(tmpdir(), 'fr-'));
const tmpBl = join(tmp, 'baseline.json');
const zeros = { counters: { n_quarantine: 0, n_refresh_database: 0, n_business_first: 0 } };
writeFileSync(tmpBl, JSON.stringify(zeros));
const refuse = exec(['--root', join(FIX, 'good'), '--baseline', tmpBl, '--write']);
check('--write que sobe → recusado (exit 1)', refuse.status === 1 && /recusado/.test(refuse.stderr));
check('--write recusado → baseline intacto', JSON.parse(readFileSync(tmpBl, 'utf8')).counters.n_quarantine === 0);
const forced = exec(['--root', join(FIX, 'good'), '--baseline', tmpBl, '--write', '--force']);
check('--write --force → grava (exit 0)', forced.status === 0 && JSON.parse(readFileSync(tmpBl, 'utf8')).counters.n_quarantine === 1);

// 5. MELHORA (contador < baseline) → verde + convite pra travar o ganho via --write.
writeFileSync(tmpBl, JSON.stringify({ counters: { n_quarantine: 2, n_refresh_database: 2, n_business_first: 2 } }));
const melhora = exec(['--root', join(FIX, 'good'), '--baseline', tmpBl]);
check('melhora → exit 0 + convite --write', melhora.status === 0 && /--write pra travar/.test(melhora.stdout));

// 6. Job summary: contadores aparecem em tabela quando GITHUB_STEP_SUMMARY existe.
const sum = join(tmp, 'summary.md');
writeFileSync(sum, '');
run('bad', [], { GITHUB_STEP_SUMMARY: sum });
const md = readFileSync(sum, 'utf8');
check('summary → tabela com contadores + sem-razão', /n_quarantine/.test(md) && /quarantine-reason/.test(md));

// 7. Conserto raiz FV-Q1 (ADR 0275): MENÇÃO da palavra (comentário/docstring/string de skip)
//    NÃO conta; só USO real do trait conta. A fixture tem 2 arquivos com a palavra, 1 aplica o
//    trait via `uses(RefreshDatabase::class)` e o outro só menciona → contagem honesta = 1.
const cvu = run('comment-vs-uso');
check('comment-vs-uso → exit 0 (uso real == baseline == 1)', cvu.status === 0);
const jc = JSON.parse(run('comment-vs-uso', ['--json']).stdout);
check('comment-vs-uso → mede 1 (ignora a menção, conta o uso Pest)', jc.counters.n_refresh_database === 1);

// 8. MESMO conserto pro marker @group legacy-quarantine (fix 2026-07-08): a MENÇÃO em docstring
//    ("…NÃO o OutroTest, que é @group legacy-quarantine") NÃO conta; só a ANOTAÇÃO real (`* @group …`
//    em posição de tag) conta. A fixture tem 2 arquivos: 1 anotação real + 1 menção → honesto = 1.
const qm = run('quarantine-mention');
check('quarantine-mention → exit 0 (só a anotação real conta == baseline == 1)', qm.status === 0);
const jq = JSON.parse(run('quarantine-mention', ['--json']).stdout);
check('quarantine-mention → mede 1 (ignora a menção em prosa, conta a anotação)', jq.counters.n_quarantine === 1);

console.log('');
if (fails === 0) { console.log(`[PASS] catraca MORDE — subir = vermelho, sem razão = vermelho, --write só desce, menção ≠ uso. (${ran}/${ran})`); process.exit(0); }
console.log(`[FAIL] ${fails} caso(s) — a catraca NÃO está garantida. NÃO mergear.`);
process.exit(1);
