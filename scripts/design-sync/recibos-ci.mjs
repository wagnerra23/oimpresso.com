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
import { readFileSync, readdirSync, existsSync } from 'node:fs';
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

/**
 * Módulos de uma lane que NÃO declara a lista de testes arquivo-a-arquivo — o que ela declara
 * é a ÁRVORE (ou o diretório) que passa ao Pest.
 *
 * POR QUE EXISTE (medido 2026-09-18): das 21 lanes `*-pest.yml` com job reconhecido, **8**
 * parseavam `tests: []` e ficavam INVISÍVEIS pro seletor — entre elas `financeiro-pest.yml` e
 * `estoque-pest.yml`, que são contexts REQUIRED e rodam Pest de verdade. Efeito no funil de
 * design: 77 de 139 alvos tinham lane; com este fallback, 93 (+16).
 *
 * ⚠️ A CAUSA, MEDIDA — e a 1ª redação deste docblock a descrevia errado em 6 de 8 (LC-08):
 * só **2** montam a lista em runtime (`find … | sort` menos quarentena: `financeiro` e
 * `estoque`). As outras **6** passam um **diretório estático** ao Pest
 * (`vendor/bin/pest … tests/Feature/Backup/`), e ficavam invisíveis por outro motivo — o
 * regex da lista estática exige sufixo `.php`, e diretório não tem. Registro o erro em vez de
 * apagá-lo: quem ler "todas montam em runtime" desenha a defesa errada.
 *
 * ⚠️ COMENTÁRIO NÃO CONTA, e este é o conserto que o adversário arrancou (REJECT de
 * 2026-09-18). O predicado certo NÃO é *"este módulo aparece no texto do YAML?"* — é *"a lane
 * RODA este módulo?"*. A diferença tem caso vivo: `estoque-pest.yml:85` diz, em comentário,
 * *"⚠️ Entra AQUI porque os testes que pareciam cobrir isso NÃO RODAM: `tests/Feature/Domain/`"*
 * — e o run-set real (`:263`) é `find tests/Feature/{Estoque,Produto,Stock}`, sem `Domain`.
 * Varrer o texto inteiro lia uma NEGAÇÃO como AFIRMAÇÃO, que é LC-11 (substring em prosa como
 * âncora, §5 2026-07-26). Por isso o comentário é cortado ANTES de casar.
 *
 * FP MEDIDO NO DENOMINADOR CERTO — as 8 lanes onde o fallback DISPARA, nunca as 13 que, por
 * construção (`if (!modulos.size)`), não podem falhar: antes do corte, **1 de 8**
 * (`estoque → Domain`); depois, **0 de 8**. Medir nas 13 dava "0 de 13" e era vacuoso.
 * Contra-exemplo que prova que o corte não é excesso de zelo: `financeiro` ganha
 * `TravaSegunda` e `Middleware` de linhas de COMANDO (`echo tests/Feature/… >> run.txt`) e
 * segue ganhando — o que morre é só a menção em prosa.
 *
 * A existência do diretório continua sendo fail-closed (módulo que não existe no repo não
 * entra), mas ela sozinha NÃO basta: `tests/Feature/Domain` existe, e mesmo assim `Domain`
 * não pertence à lane. Existência filtra o inventado; o corte de comentário filtra o afirmado.
 */
export function modulosPorArvore(texto, existe = (rel) => existsSync(join(ROOT, rel))) {
  const mods = new Set();
  // Corta o comentário de cada linha. Conservador de propósito: `#` dentro de string quotada
  // trunca a linha cedo e no máximo PERDE um módulo — errar pra menos deixa a lane invisível,
  // como já era; errar pra mais grava recibo citando um job que não roda aquele teste.
  const codigo = String(texto).split('\n').map((l) => l.replace(/#.*$/, '')).join('\n');
  for (const m of codigo.matchAll(/Modules\/([A-Za-z][A-Za-z0-9_]*)\/Tests\b/g)) {
    if (existe(`Modules/${m[1]}/Tests`)) mods.add(m[1]);
  }
  for (const m of codigo.matchAll(/tests\/Feature\/([A-Za-z][A-Za-z0-9_]*)\//g)) {
    if (existe(`tests/Feature/${m[1]}`)) mods.add(m[1]);
  }
  return mods;
}

/**
 * O step que RODA o Pest (≠ o que faz SETUP dele). É por ele que se distingue execução de
 * skip-as-pass (LC-13), então errar aqui não dá vermelho — dá silêncio com a culpa trocada.
 *
 * MEDIDO em 21 de 21 lanes `*-pest.yml` com job reconhecido (2026-09-18): **20** nomeiam o step
 * `Run Pest …`; **1** — `financeiro-pest.yml`, que é REQUIRED — nomeia
 * `Selecionar alvos (DIRETÓRIO − QUARENTENA) e rodar Pest`. O predicado antigo era só
 * `/^Run Pest/`, logo o Financeiro **nunca** era aceito: a linha saía
 * `pulado: nenhum run prova (… Pest ausente)`, acusando o RUN quando o defeito era o NOME DO
 * STEP. Custo real, contado: dos +16 alvos que o fallback de árvore desbloqueou, **7 são do
 * Financeiro** e eram inalcançáveis por isto — o [PR #7507](https://github.com/wagnerra23/oimpresso.com/pull/7507)
 * afirmou o ganho sem medir esta perna (LC-15: anunciar capacidade que o código não honra).
 *
 * `Setup Pest MySQL (…)`, presente em 20 lanes, não casa nenhum dos dois termos — é o
 * controle negativo que mantém o predicado estreito, e o `--selftest` o pina.
 */
export function ehStepDePest(nome) {
  const n = String(nome || '').trim();
  return /^Run Pest\b/.test(n) || /\brodar Pest\b/i.test(n);
}

/** Parse de uma lane: job `PHP / Pest (<X> · MySQL)` + lista EXPLÍCITA de arquivos de teste. */
export function parseLane(texto, workflow, existe) {
  const job = texto.match(/^\s*name:\s*(PHP \/ Pest \([^)]*\))\s*$/m)?.[1] || null;
  const tests = [];
  for (const linha of texto.split(/\r?\n/)) {
    // só linha que É um path (comentário `#   - Modules/...` e `paths:` do trigger ficam de fora)
    const m = linha.match(/^\s*((?:Modules\/[^\s\\]+\/Tests\/[^\s\\]+|tests\/Feature\/[^\s\\]+)\.php)\s*\\?\s*$/);
    if (m) tests.push(m[1]);
  }
  const modulos = new Set(tests.map(moduloDoTeste).filter(Boolean));
  // FALLBACK de RAIO MÍNIMO: só quando NÃO há lista estática. Lane que lista arquivos segue
  // exatamente como era — os 13 casos visíveis não mudam de comportamento em nada, e os
  // controles negativos do selftest (comentário não vira teste; `Beta` não vira lane) seguem
  // valendo por construção, porque naquela fixture a lista não é vazia.
  if (!modulos.size) for (const mod of modulosPorArvore(texto, existe)) modulos.add(mod);
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
    const pest = (job.steps || []).find((s) => ehStepDePest(s.name));
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

  /* ── fallback árvore-menos-quarentena (2026-09-18) ────────────────────────────────────
   * As 3 provas que o fallback precisa ter, e a do meio é a que protege quem já funcionava.
   * `existe` é injetado: o selftest continua hermético (sem tocar o filesystem real).        */
  const arvoreYaml = [
    'name: Y · Pest (MySQL)', 'jobs:', '  pest:', '    name: PHP / Pest (Delta · MySQL)', '    steps:',
    '      - name: Run Pest', '        run: |',
    '          find Modules/Delta/Tests -name "*Test.php" | sort > /tmp/all.txt',
    '          # placeholder de doc, o diretório não existe: Modules/X/Tests',
    '          echo tests/Feature/Epsilon/AlgumTest.php >> /tmp/run.txt',
  ].join('\n');
  const existeFake = (rel) => ['Modules/Delta/Tests', 'tests/Feature/Epsilon'].includes(rel);
  const laneArvore = parseLane(arvoreYaml, 'delta-pest.yml', existeFake);
  assert.deepEqual(laneArvore.tests, [], 'lane de árvore não tem lista estática — é esse o caso');
  assert.deepEqual([...laneArvore.modulos].sort(), ['Delta', 'Epsilon'],
    'MORDE: lane que monta a lista em runtime recupera o módulo pela árvore que ela varre');
  assert.equal(lanesDoModulo([laneArvore], 'Delta').length, 1, 'e vira lane utilizável pro seletor');
  assert.equal(lanesDoModulo([laneArvore], 'X').length, 0,
    'CONTROLE: árvore inexistente no repo (placeholder em comentário) NÃO vira módulo');

  /* ── o módulo que aparece SÓ EM COMENTÁRIO não entra (REJECT do adversário, 2026-09-18) ──
   * Caso VIVO que motivou: `estoque-pest.yml:85` diz, em comentário, "⚠️ Entra AQUI porque os
   * testes que pareciam cobrir isso NÃO RODAM: `tests/Feature/Domain/`" — e o run-set real
   * (`:263`) é `find tests/Feature/{Estoque,Produto,Stock}`. O fallback lia a NEGAÇÃO como
   * AFIRMAÇÃO. O filtro de existência NÃO barra (`tests/Feature/Domain` existe de verdade):
   * quem barra é cortar o comentário. Por isso `existeFake` aqui diz que Zeta EXISTE — sem
   * isso a fixture passaria pelo motivo errado e não provaria nada.                            */
  const soComentarioYaml = [
    'name: Z · Pest (MySQL)', 'jobs:', '  pest:', '    name: PHP / Pest (Teta · MySQL)', '    steps:',
    '      - name: Run Pest', '        run: |',
    '          # os testes que PARECIAM cobrir isso NAO RODAM: tests/Feature/Zeta/',
    '          find Modules/Teta/Tests -name "*Test.php" | sort > /tmp/all.txt',
  ].join('\n');
  const existeTudo = (rel) => ['Modules/Teta/Tests', 'tests/Feature/Zeta'].includes(rel);
  const laneSoCom = parseLane(soComentarioYaml, 'teta-pest.yml', existeTudo);
  assert.deepEqual([...laneSoCom.modulos], ['Teta'],
    'MORDE: módulo citado SÓ em comentário não entra, mesmo EXISTINDO no repo (o run-set é que manda)');

  // O step que RODA o Pest — 20 lanes dizem `Run Pest…`, 1 (financeiro, REQUIRED) diz `…rodar Pest`.
  assert.equal(ehStepDePest('Run Pest (Arquivos · MySQL) — ALLOWLIST VERDE (catraca)'), true);
  assert.equal(ehStepDePest('Selecionar alvos (DIRETÓRIO − QUARENTENA) e rodar Pest'), true,
    'MORDE: a lane REQUIRED do Financeiro nomeia o step assim; `/^Run Pest/` a recusava em silêncio');
  assert.equal(ehStepDePest('Setup Pest MySQL (PHP + deps + .env + migrate + seed mínimo multi-tenant)'), false,
    'CONTROLE: o step de SETUP não pode ser lido como execução — ele é verde mesmo quando o Pest não roda');

  // CONTROLE que protege as 13 lanes que já funcionavam: com lista estática, a árvore é ignorada.
  const comListaEArvore = parseLane([
    '    name: PHP / Pest (Alpha · MySQL)', '      - name: Run Pest', '        run: |',
    '          find Modules/Delta/Tests -name "*Test.php"',
    '            Modules/Alpha/Tests/Feature/UmTest.php',
  ].join('\n'), 'misto-pest.yml', () => true);
  assert.deepEqual([...comListaEArvore.modulos], ['Alpha'],
    'CONTROLE: lane COM lista estática não ganha módulo da árvore (raio mínimo)');
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
