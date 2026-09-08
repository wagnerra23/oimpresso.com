#!/usr/bin/env node
// @ts-check
/**
 * recibos-ci.mjs — grava recibo de TESTE (ADR 0384 D-5) em massa a partir de lanes de CI.
 *
 * Automatiza o que o PR #6898 fez à mão pro Fiscal: pra cada tela `applied`/`tested` sem
 * recibo válido pro alvo atual, acha a lane `*-pest.yml` que LISTA testes do módulo, o run
 * verde mais recente cujo step de Pest EXECUTOU (run de PR sai `success` com Pest `skipped`
 * — skip-as-pass, ADR 0271 — e isso não prova nada: LC-13) e cujo head é byte-idêntico ao
 * `origin/main` nos paths do módulo, e registra via `status.mjs --run-test --runner ci`.
 * Nunca inventa lane: módulo sem lane que liste seus testes é reportado e pulado.
 *
 *   node scripts/design-sync/recibos-ci.mjs [--dry] [--limit 20] [--no-fetch] [--root DIR]
 *   node scripts/design-sync/recibos-ci.mjs --selftest
 */
import { readFileSync, readdirSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { spawnSync } from 'node:child_process';

const args = process.argv.slice(2);
const flag = (n) => args.includes(n);
const valueOf = (n) => { const i = args.indexOf(n); return i >= 0 && args[i + 1] ? args[i + 1] : null; };
const ROOT = resolve(valueOf('--root') || process.cwd());
const ESTADOS_ELEGIVEIS = new Set(['applied', 'tested']);

/* ── partes puras (cobertas por --selftest) ─────────────────────────────────────────── */

/** `resources/js/Pages/<Mod>/...` ou `Modules/<Mod>/Resources/js/Pages/...` → Mod. */
export function moduloDoAlvo(target) {
  const t = String(target || '').replace(/\\/g, '/');
  return t.match(/^resources\/js\/Pages\/([^/]+)\//)?.[1]
    || t.match(/^Modules\/([^/]+)\/Resources\/js\/Pages\//)?.[1] || null;
}

/** Módulo de um arquivo de teste listado na lane (`Modules/<Mod>/Tests/…` · `tests/Feature/<Mod>/…`). */
export function moduloDoTeste(path) {
  return path.match(/^Modules\/([^/]+)\/Tests\//)?.[1] || path.match(/^tests\/Feature\/([^/]+)\//)?.[1] || null;
}

/** Parse de uma lane: job `PHP / Pest (<X> · MySQL)` + lista EXPLÍCITA de arquivos de teste. */
export function parseLane(texto, workflow) {
  const job = texto.match(/^\s*name:\s*(PHP \/ Pest \([^)]*\))\s*$/m)?.[1] || null;
  const tests = [];
  for (const linha of texto.split(/\r?\n/)) {
    // só linha que É um path (comentário `#   - Modules/...` e `paths:` do trigger ficam de fora)
    const m = linha.match(/^\s*((?:Modules\/[^\s\\]+\/Tests\/[^\s\\]+|tests\/Feature\/[^\s\\]+)\.php)\s*\\?\s*$/);
    if (m) tests.push(m[1]);
  }
  const modulos = new Set(tests.map(moduloDoTeste).filter(Boolean));
  return { workflow, job, tests, modulos };
}

/** Lanes que listam teste do módulo, a que lista mais primeiro. Vazio = "sem lane". */
export function lanesDoModulo(lanes, modulo) {
  return lanes.filter((l) => l.job && l.modulos.has(modulo))
    .sort((a, b) => b.tests.filter((t) => moduloDoTeste(t) === modulo).length - a.tests.filter((t) => moduloDoTeste(t) === modulo).length);
}

/** Telas elegíveis (applied/tested) SEM recibo de teste válido pro alvo atual. */
export function selecionarTelas(report) {
  return (report.screens || []).filter((s) => ESTADOS_ELEGIVEIS.has(s.lifecycleState) && !(s.applicationEvidence?.tests?.length));
}

/**
 * Primeiro run (ordem recebida, mais novo primeiro) que prova algo: job da lane `success`,
 * step "Run Pest…" `success` (não `skipped`) e head idêntico ao main nos paths do módulo.
 * `jobsDe(run)` e `identico(headSha)` são injetados pra teste hermético.
 */
export function escolherRun({ runs, lane, jobsDe, identico }) {
  const motivos = [];
  for (const run of runs) {
    const job = (jobsDe(run) || []).find((j) => j.name === lane.job);
    if (!job || job.conclusion !== 'success') { motivos.push(`${run.databaseId}: job ausente/não-verde`); continue; }
    const pest = (job.steps || []).find((s) => /^Run Pest/.test(s.name));
    if (!pest || pest.conclusion !== 'success') { motivos.push(`${run.databaseId}: Pest ${pest?.conclusion || 'ausente'} (skip-as-pass não prova)`); continue; }
    const id = identico(run.headSha);
    if (id !== true) { motivos.push(`${run.databaseId}: head ${String(run.headSha).slice(0, 10)} ${id === false ? 'difere do main' : `não comparável (${id})`}`); continue; }
    return { run, job, motivos };
  }
  return { run: null, job: null, motivos };
}

export const pathsDoModulo = (m) => [`Modules/${m}`, `resources/js/Pages/${m}`, `tests/Feature/${m}`];

/* ── IO ────────────────────────────────────────────────────────────────────────────── */

function exec(cmd, argv, opts = {}) {
  const r = spawnSync(cmd, argv, { cwd: ROOT, encoding: 'utf8', shell: false, maxBuffer: 64 * 1024 * 1024, ...opts });
  if (r.error) throw new Error(`${cmd} ${argv[0]}: ${r.error.message}`);
  return r;
}
function ghJson(argv) {
  const r = exec('gh', argv);
  if (r.status !== 0) throw new Error(`gh ${argv.slice(0, 3).join(' ')} rc=${r.status}: ${(r.stderr || '').trim()}`);
  return JSON.parse(r.stdout);
}
/** Runs verdes: os de `push` (path-filtered — o Pest de fato roda) ∪ os N mais novos de qualquer evento. */
function listarRuns(workflow, limit) {
  const base = ['run', 'list', '--workflow', workflow, '--status', 'success', '--limit', String(limit), '--json', 'databaseId,headSha,createdAt,event'];
  const vistos = new Map();
  for (const run of [...ghJson([...base, '--event', 'push']), ...ghJson(base)]) vistos.set(run.databaseId, run);
  return [...vistos.values()].sort((a, b) => String(b.createdAt).localeCompare(String(a.createdAt)));
}
function lerLanes() {
  const dir = join(ROOT, '.github/workflows');
  return readdirSync(dir).filter((f) => f.endsWith('-pest.yml')).map((f) => parseLane(readFileSync(join(dir, f), 'utf8'), f));
}
/** true = diff vazio · false = difere · string = não deu pra medir (rc≠0/1, commit inalcançável). */
function identicoAoMain(headSha, paths) {
  if (exec('git', ['cat-file', '-e', `${headSha}^{commit}`]).status !== 0) {
    const f = exec('git', ['fetch', '-q', 'origin', headSha]);
    if (f.status !== 0) return `commit inalcançável (fetch rc=${f.status})`;
  }
  const d = exec('git', ['diff', '--quiet', headSha, 'origin/main', '--', ...paths]);
  if (d.status === 0) return true;
  if (d.status === 1) return false;
  return `git diff rc=${d.status}: ${(d.stderr || '').trim()}`;
}
function gravarRecibo(tela, jobId) {
  const cmd = JSON.stringify(['gh', 'run', 'view', '--job', String(jobId), '--exit-status']);
  const r = exec(process.execPath, ['scripts/design-sync/status.mjs', '--run-test', tela.source, '--target', tela.target, '--runner', 'ci', '--command-json', cmd], { stdio: ['ignore', 'ignore', 'pipe'] });
  return { ok: r.status === 0, stderr: (r.stderr || '').trim() };
}

async function main() {
  const dry = flag('--dry');
  const limit = Number(valueOf('--limit') || 20);
  if (!flag('--no-fetch')) exec('git', ['fetch', '-q', 'origin', 'main']);
  const refresh = exec(process.execPath, ['scripts/design-sync/status.mjs', '--refresh'], { stdio: ['ignore', 'ignore', 'pipe'] });
  if (refresh.status !== 0) throw new Error(`status.mjs --refresh rc=${refresh.status}: ${(refresh.stderr || '').trim()}`);
  const report = JSON.parse(readFileSync(join(ROOT, 'scripts/design-sync/state/application-report.json'), 'utf8'));
  const lanes = lerLanes();
  const telas = selecionarTelas(report);
  const jaTestadas = (report.screens || []).filter((s) => ESTADOS_ELEGIVEIS.has(s.lifecycleState)).length - telas.length;
  console.log(`\nRECIBOS-CI${dry ? ' (--dry: só plano)' : ''} · ${lanes.length} lanes · ${telas.length} tela(s) sem recibo válido · ${jaTestadas} já com recibo\n`);
  if (!telas.length) return 0;

  const runsCache = new Map(); const jobsCache = new Map(); const linhas = []; let falhas = 0;
  for (const tela of telas) {
    const modulo = moduloDoAlvo(tela.target) || tela.module;
    const linha = { tela: tela.target, lane: '-', run: '-', job: '-', resultado: '' };
    linhas.push(linha);
    const candidatas = lanesDoModulo(lanes, modulo);
    if (!candidatas.length) { linha.resultado = `pulado: sem lane que liste testes de ${modulo}`; continue; }
    let escolha = null;
    for (const lane of candidatas) {
      if (!runsCache.has(lane.workflow)) runsCache.set(lane.workflow, listarRuns(lane.workflow, limit));
      const r = escolherRun({
        runs: runsCache.get(lane.workflow), lane,
        jobsDe: (run) => { if (!jobsCache.has(run.databaseId)) jobsCache.set(run.databaseId, ghJson(['run', 'view', String(run.databaseId), '--json', 'jobs']).jobs); return jobsCache.get(run.databaseId); },
        identico: (sha) => identicoAoMain(sha, pathsDoModulo(modulo)),
      });
      linha.lane = lane.workflow;
      if (r.run) { escolha = { lane, ...r }; break; }
      linha.resultado = `pulado: nenhum run prova (${r.motivos.slice(0, 3).join(' · ')})`;
    }
    if (!escolha) continue;
    Object.assign(linha, { run: String(escolha.run.databaseId), job: String(escolha.job.databaseId) });
    if (dry) { linha.resultado = `plano: gravar (head ${escolha.run.headSha.slice(0, 10)} idêntico ao main em ${pathsDoModulo(modulo).join(',')})`; continue; }
    const g = gravarRecibo(tela, escolha.job.databaseId);
    if (g.ok) linha.resultado = 'gravado'; else { falhas += 1; linha.resultado = `FALHOU: ${g.stderr.split('\n').pop()}`; }
  }
  console.log('tela · lane · run · job · resultado');
  for (const l of linhas) console.log(`  ${l.tela} · ${l.lane} · ${l.run} · ${l.job} · ${l.resultado}`);
  console.log('');
  return falhas ? 1 : 0;
}

/* ── selftest hermético (sem gh, sem git) ──────────────────────────────────────────── */
async function selftest() {
  const assert = (await import('node:assert/strict')).default;
  const yaml = [
    'name: X · Pest (MySQL)', 'on:', '  push:', '    paths:', "      - 'Modules/Alpha/**'", 'jobs:', '  pest:',
    '    name: PHP / Pest (Alpha · MySQL)', '    steps:', '      - name: Run Pest', '        #   - Modules/Beta/Tests/Feature/ComentadoTest.php',
    '        run: |', '          vendor/bin/pest \\', '            Modules/Alpha/Tests/Feature/UmTest.php \\',
    '            tests/Feature/Gamma/DoisTest.php \\', '            Modules/Alpha/Tests/Unit/TresTest.php',
  ].join('\n');
  const lane = parseLane(yaml, 'alpha-pest.yml');
  assert.equal(lane.job, 'PHP / Pest (Alpha · MySQL)');
  assert.deepEqual(lane.tests, ['Modules/Alpha/Tests/Feature/UmTest.php', 'tests/Feature/Gamma/DoisTest.php', 'Modules/Alpha/Tests/Unit/TresTest.php']);
  assert.deepEqual([...lane.modulos].sort(), ['Alpha', 'Gamma'], 'comentário e paths: do trigger não viram teste');
  assert.equal(moduloDoAlvo('resources/js/Pages/Arquivos/Index.tsx'), 'Arquivos');
  assert.equal(moduloDoAlvo('Modules/Fiscal/Resources/js/Pages/Config.tsx'), 'Fiscal');
  assert.equal(moduloDoAlvo('resources/views/x.blade.php'), null);
  assert.equal(lanesDoModulo([lane], 'Alpha').length, 1);
  assert.equal(lanesDoModulo([lane], 'Beta').length, 0, 'módulo só citado em comentário = sem lane');
  const report = { screens: [
    { target: 'a', lifecycleState: 'applied', applicationEvidence: { tests: [] } },
    { target: 'b', lifecycleState: 'tested', applicationEvidence: { tests: [{ exitCode: 0 }] } },
    { target: 'c', lifecycleState: 'compared', applicationEvidence: { tests: [] } },
  ] };
  assert.deepEqual(selecionarTelas(report).map((s) => s.target), ['a'], 'só applied/tested sem recibo');
  const jobs = {
    1: [{ name: lane.job, conclusion: 'success', databaseId: 11, steps: [{ name: 'Run Pest (Alpha)', conclusion: 'skipped' }] }],
    2: [{ name: lane.job, conclusion: 'success', databaseId: 22, steps: [{ name: 'Run Pest (Alpha)', conclusion: 'success' }] }],
    3: [{ name: lane.job, conclusion: 'success', databaseId: 33, steps: [{ name: 'Run Pest (Alpha)', conclusion: 'success' }] }],
  };
  const runs = [{ databaseId: 1, headSha: 'aaa' }, { databaseId: 2, headSha: 'bbb' }, { databaseId: 3, headSha: 'ccc' }];
  const r = escolherRun({ runs, lane, jobsDe: (run) => jobs[run.databaseId], identico: (sha) => (sha === 'ccc') });
  assert.equal(r.job?.databaseId, 33, 'run 1 (Pest skipped) e run 2 (head difere) são recusados; 3 passa');
  assert.match(r.motivos[0], /skip-as-pass/);
  const nenhum = escolherRun({ runs, lane, jobsDe: (run) => jobs[run.databaseId], identico: () => 'commit inalcançável' });
  assert.equal(nenhum.run, null, 'identidade não medida nunca vira recibo');
  console.log('recibos-ci --selftest OK (parse de lane · módulo do alvo · seleção · escolha de run)');
  return 0;
}

if (import.meta.url === `file:///${process.argv[1].replace(/\\/g, '/').replace(/^\//, '')}`) {
  (flag('--selftest') ? selftest() : main()).then((rc) => process.exit(rc)).catch((e) => { console.error(`✗ recibos-ci: ${e.message}`); process.exit(2); });
}
