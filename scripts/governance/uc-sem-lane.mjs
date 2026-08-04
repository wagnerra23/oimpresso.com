#!/usr/bin/env node
// uc-sem-lane.mjs — UC com o id no TÍTULO de um teste que LANE NENHUMA executa.
//
// ── O BURACO QUE ISTO FECHA ─────────────────────────────────────────────────
// O `casos-coverage-guard` (dono do formato, ADR 0264) já publica o TETO de prova:
// quantos UCs têm o id no TÍTULO de um `it()/test()` — os únicos que o manifesto
// G-7 consegue carimbar, porque o coletor lê o atributo `name` do <testcase> do
// JUnit. Medido no main de 2026-08-04: 319 de 435 UCs estão no título.
//
// Mas "id no título" só torna o UC ALCANÇÁVEL. Ele ainda precisa que o arquivo
// que carrega esse título seja EXECUTADO por alguma lane. Existe uma terceira
// categoria entre "coberto" e "descoberto":
//
//     alcançável  ×  nunca alcançado
//
// O caso que originou isto: `Modules/Jana/Tests/Feature/Mcp/McpTasksHealthCheckCommandTest.php`
// tinha 9 `it()` com id no título e ficou MESES sem nunca ter sido executado —
// ele pula em MySQL (`markTestSkipped`, porque usa `dropIfExists` que corrompe o
// schema compartilhado) e não estava na allowlist da lane sqlite. Ao ser ancorado
// em `.github/ci-sqlite-pest.list`, 12 casos passaram a rodar de uma vez.
// Título sem lane é chokepoint fantasma: o mecanismo existe e nada o invoca.
//
// ── O QUE ELE NÃO É (leia antes de achar que duplica algo) ──────────────────
// NÃO reimplementa nada. É a JUNÇÃO de dois donos que já existem e nunca se
// falaram — cada um sabe metade da resposta e nenhum sabe a pergunta inteira:
//
//   scripts/lib/uc-regex.mjs          → o que é um UC (fonte única do regex+parser)
//   scripts/casos-coverage-guard.mjs  → quais UCs têm o id no título (o TETO)
//   scripts/governance/test-lane-coverage.mjs → quais arquivos alguma lane executa
//
// O guard mede UC×título e para (não sabe de lane). O test-lane-coverage mede
// arquivo×lane e para (não sabe de UC). O cruzamento — que é a pergunta do [W] —
// não era feito por ninguém. `extrairAlvos`/`estaCoberto` são IMPORTADOS do dono,
// não copiados: se a heurística de parsing do YAML melhorar lá, melhora aqui.
//
// NÃO mede se o teste PASSOU (isso é junit-summary.mjs + o manifesto G-7).
// NÃO mede cobertura de linha (isso é scripts/tests/coverage-compute.mjs).
// NÃO decide nada: ADVISORY, exit 0 sempre (fora do --selftest).
//
// ── LIMITE HONESTO (o mesmo do dono, herdado por construção) ────────────────
// "FORA DE LANE DE PR" ≠ "NUNCA RODA": a nightly do CT 100 (ct100-fullsuite.sh)
// roda a árvore inteira. O que se mede aqui é o elo do MANIFESTO: um UC cujo
// teste não roda em lane de PR nunca vira `execução-backed` no G-7 pela via
// per-PR — e é por isso que ele fica 🧪 pra sempre sem ninguém saber por quê.
//
// Parsing de YAML é TEXTUAL (regex), herdado do `test-lane-coverage`. Por isso
// o relatório imprime `lanes_lidas` e `alvos_totais`: "0 alvos" tem que saltar
// aos olhos como ausência de MEDIÇÃO, nunca virar falso "tudo órfão".
//
// USO:
//   node scripts/governance/uc-sem-lane.mjs             # relatório
//   node scripts/governance/uc-sem-lane.mjs --json      # consumo por máquina
//   node scripts/governance/uc-sem-lane.mjs --modulo Estoque
//   node scripts/governance/uc-sem-lane.mjs --selftest  # bite-test (boa/ruim)

import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { join, resolve, relative } from 'node:path';
import { ucsDeclaredInCasos, ucScanRe } from '../lib/uc-regex.mjs';
import { extrairAlvos, estaCoberto } from './test-lane-coverage.mjs';

const ROOT = process.cwd();
const PAGES_DIR = resolve(ROOT, 'resources/js/Pages');
const WF_DIR = resolve(ROOT, '.github/workflows');
const LISTA_CURADA = resolve(ROOT, '.github/ci-sqlite-pest.list');

// Mesmos diretórios que o `casos-coverage-guard` varre pra achar citação de UC.
// Divergir daqui faria os dois medirem corpos diferentes e brigarem no número.
const TEST_DIRS = ['Modules', 'tests', 'app', 'e2e'];

// Meta-tests do PRÓPRIO guard: um UC-id de FIXTURE não pode contar como título
// real (bug 2026-06-22 — id real em fixture "cobria" o UC real). Critério e
// motivo copiados de casos-coverage-guard.mjs, que é o dono.
const META_TEST_RE = /(?:casosGuard|casosResultsCollect)\.spec\.[tj]sx?$/;

// Fixtures sintéticas dos próprios gates não são teste real (mesmo critério que
// o `test-lane-coverage` lê do SHARD_EXCLUDE do ct100-fullsuite.sh).
const DIRS_SINTETICOS = ['tests/governance-fixtures'];

const norm = (p) => relative(ROOT, p).replace(/\\/g, '/');

// ───────────────────────── lógica pura (testável pelo --selftest) ─────────────

/**
 * Títulos de `it()/test()` de um arquivo de teste → UC-ids citados NO TÍTULO.
 *
 * O regex do título é o mesmo do `buildTestTitleCorpus` do guard; a diferença é
 * que aqui o resultado é indexado POR ARQUIVO (o guard concatena tudo num corpus
 * só e perde de qual arquivo veio — e é justamente o arquivo que decide a lane).
 */
export function ucsNoTituloDe(conteudo) {
  const out = new Set();
  const reTitulo = /\b(?:it|test)\s*\(\s*(['"`])([\s\S]*?)\1/g;
  let m;
  while ((m = reTitulo.exec(conteudo)) !== null) {
    for (const uc of m[2].matchAll(ucScanRe())) out.add(uc[0].toUpperCase());
  }
  return [...out];
}

/**
 * Alvos da lane PLAYWRIGHT. O `extrairAlvos` do dono só entende `vendor/bin/pest`
 * (o escopo dele é explícito: "as 27 invocações de teste passam ALVOS EXPLÍCITOS")
 * — e Playwright não passa alvo nenhum: o `e2e-gate.yml` roda `npm run e2e:check`,
 * que é `playwright test`, e QUEM decide o conjunto é o `playwright.config.ts`
 * (`testDir: './e2e'` + `testMatch: '**''/*.spec.ts'`).
 *
 * POR QUE ISTO EXISTE (erro meu, medido e corrigido antes do merge): sem esta
 * função os 11 UCs declarados por `e2e/*.spec.ts` apareciam como órfãos — e são
 * FALSO-POSITIVO puro, porque a lane e2e roda a pasta inteira e ainda por cima é
 * uma das 10 fontes que o `casos-results-publish` colhe pro manifesto. Pior: meu
 * verificador independente tinha o MESMO ponto cego (procurava basename/ancestral
 * no texto do workflow, e o nome do spec não aparece em workflow nenhum — quem o
 * seleciona é a config). Dois caminhos com o mesmo viés não são duas provas.
 * Medir a fonte certa é a lição LC-08.
 *
 * Deriva, não presume: lê o script do package.json e o testDir da config. Se
 * amanhã o `testDir` mudar, isto acompanha.
 *
 * @param {string} yamlText   texto do workflow
 * @param {Record<string,string>} scripts  package.json `scripts` (injetado = puro)
 * @param {string|null} testDir   testDir do playwright.config (injetado = puro)
 */
export function alvosDePlaywright(yamlText, scripts = {}, testDir = null) {
  if (!testDir) return [];
  const invocaDireto = /npx\s+playwright\s+test|(?:^|[\s&|;])playwright\s+test/m.test(yamlText);
  // `npm run <script>` cujo corpo no package.json chama `playwright test`.
  const viaNpm = [...yamlText.matchAll(/npm\s+run\s+([A-Za-z0-9:_-]+)/g)]
    .some((m) => /playwright\s+test/.test(scripts[m[1]] || ''));
  return invocaDireto || viaNpm ? [testDir] : [];
}

/** `testDir: './e2e'` → `e2e` (normalizado pro mesmo formato dos outros alvos). */
export function normalizaTestDir(configText) {
  const m = String(configText).match(/testDir\s*:\s*(['"`])([^'"`]+)\1/);
  if (!m) return null;
  return m[2].replace(/^\.\//, '').replace(/\/$/, '');
}

/**
 * Classifica cada UC declarado cruzando (título→arquivos) × (arquivos→lanes).
 *
 * Puro de propósito: recebe mapas prontos, então o --selftest exercita a REGRA
 * de classificação sem tocar disco nem depender do estado do repo.
 *
 * @param {string[]} ucsDeclarados        UCs declarados nos casos.md (heading `## UC-..`)
 * @param {Map<string,string[]>} porUc    UC → arquivos de teste que citam o id NO TÍTULO
 * @param {string[]} alvos                alvos de lane (saída do extrairAlvos do dono)
 * @returns {{semTitulo:string[], emLane:string[], semLane:Array<{uc:string,arquivos:string[]}>}}
 */
export function classificar(ucsDeclarados, porUc, alvos) {
  const semTitulo = [];
  const emLane = [];
  const semLane = [];
  for (const uc of ucsDeclarados) {
    const arquivos = porUc.get(uc) || [];
    if (arquivos.length === 0) {
      // Sem título: já é o `teto_so_docblock`/`uc-orphan` do guard. NÃO é este
      // problema — reportar aqui seria duplicar régua consolidada.
      semTitulo.push(uc);
      continue;
    }
    // Basta UMA lane alcançar UM dos arquivos que carregam o título.
    const alcancado = arquivos.some((f) => estaCoberto(f, alvos));
    if (alcancado) emLane.push(uc);
    else semLane.push({ uc, arquivos: [...arquivos].sort() });
  }
  return { semTitulo, emLane, semLane };
}

// ───────────────────────────────── coleta (I/O) ───────────────────────────────

function walk(dir, filtro, acc = []) {
  if (!existsSync(dir)) return acc;
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    const full = join(dir, e.name);
    if (e.isDirectory()) walk(full, filtro, acc);
    else if (filtro(e.name)) acc.push(full);
  }
  return acc;
}

function arquivosDeTeste() {
  const out = [];
  for (const d of TEST_DIRS) {
    for (const f of walk(resolve(ROOT, d), (name) =>
      (/Test\.php$/.test(name) || /\.test\.[tj]sx?$/.test(name) || /\.spec\.[tj]sx?$/.test(name))
      && !META_TEST_RE.test(name))) {
      const rel = norm(f);
      if (DIRS_SINTETICOS.some((s) => rel.startsWith(s + '/'))) continue;
      out.push(rel);
    }
  }
  return out.sort();
}

/** UC → arquivos de teste que citam o id NO TÍTULO de um it()/test(). */
function indiceUcPorArquivo() {
  const porUc = new Map();
  for (const rel of arquivosDeTeste()) {
    let c;
    try { c = readFileSync(resolve(ROOT, rel), 'utf8'); } catch { continue; }
    for (const uc of ucsNoTituloDe(c)) {
      if (!porUc.has(uc)) porUc.set(uc, []);
      porUc.get(uc).push(rel);
    }
  }
  return porUc;
}

function ucsDeclarados() {
  const out = [];
  for (const f of walk(PAGES_DIR, (name) => name.endsWith('.casos.md'))) {
    const rel = norm(f);
    let c;
    try { c = readFileSync(f, 'utf8'); } catch { continue; }
    for (const uc of new Set(ucsDeclaredInCasos(c))) out.push({ uc, file: rel });
  }
  return out;
}

function entradasDaListaCurada() {
  if (!existsSync(LISTA_CURADA)) return [];
  return readFileSync(LISTA_CURADA, 'utf8')
    .split(/\r?\n/).map((l) => l.trim()).filter((l) => l && !l.startsWith('#'));
}

/**
 * Alvos de todas as lanes. É I/O de 10 linhas em cima do `extrairAlvos` do dono
 * (que não exporta o coletor). O PARSING — a parte difícil e cheia de armadilhas
 * catalogadas — continua sendo dele; aqui só se lê o diretório.
 */
function coletarAlvos() {
  const lista = entradasDaListaCurada();
  const alvos = new Set();
  let lanesLidas = 0;
  if (!existsSync(WF_DIR)) return { alvos: [], lanesLidas: 0 };

  // Playwright: quem escolhe os specs é a config, não o comando (ver alvosDePlaywright).
  let scripts = {};
  try { scripts = JSON.parse(readFileSync(resolve(ROOT, 'package.json'), 'utf8')).scripts || {}; }
  catch { /* sem package.json: só some a perna e2e, e o relatório diz lanes_lidas */ }
  const pwConfig = ['playwright.config.ts', 'playwright.config.js']
    .map((f) => resolve(ROOT, f)).find((f) => existsSync(f));
  const testDir = pwConfig ? normalizaTestDir(readFileSync(pwConfig, 'utf8')) : null;

  for (const f of readdirSync(WF_DIR).filter((f) => /\.ya?ml$/.test(f))) {
    const txt = readFileSync(join(WF_DIR, f), 'utf8');
    const alvosPw = alvosDePlaywright(txt, scripts, testDir);
    if (!/vendor\/bin\/pest|ci-sqlite-pest\.list/.test(txt) && alvosPw.length === 0) continue;
    lanesLidas++;
    for (const a of extrairAlvos(txt, lista)) alvos.add(a);
    for (const a of alvosPw) alvos.add(a);
  }
  return { alvos: [...alvos].sort(), lanesLidas };
}

const moduloDe = (f) => (String(f).split('resources/js/Pages/')[1] || '?').split('/')[0];

// ───────────────────────────────── selftest ───────────────────────────────────

function selftest() {
  const casos = [];
  const ok = (nome, cond) => casos.push({ nome, ok: !!cond });

  // ---- extração de UC do TÍTULO ----
  ok('LIBERA: UC no título de it() é extraído',
    ucsNoTituloDe(`it('UC-EST-01 · movimenta estoque', function () {});`).includes('UC-EST-01'));
  ok('LIBERA: test() também conta',
    ucsNoTituloDe(`test('UC-FCC-02 concilia', function () {});`).includes('UC-FCC-02'));
  ok('BITE: UC só no DOCBLOCK não conta como título',
    ucsNoTituloDe(`/** cobre UC-EST-99 */\nit('movimenta estoque', function () {});`).length === 0);
  ok('BITE: UC em comentário solto não conta',
    ucsNoTituloDe(`// UC-EST-98 pendente\nit('outra coisa', fn);`).length === 0);
  ok('CONTROLE NEGATIVO: arquivo sem it()/test() → nenhum UC',
    ucsNoTituloDe('class Foo { public function bar() {} }').length === 0);
  ok('id hifenado longo (UC-FORJA-01) é extraído',
    ucsNoTituloDe(`it('UC-FORJA-01 · abre quadro', fn);`).includes('UC-FORJA-01'));

  // ---- classificação ----
  // Reproduz o incidente REAL: McpTasksHealthCheckCommandTest tinha o UC no
  // título e o arquivo não estava em lane nenhuma.
  const alvosLane = ['Modules/Jana/Tests/Feature/Mcp/OutroTest.php'];
  const porUc = new Map([
    ['UC-MCP-01', ['Modules/Jana/Tests/Feature/Mcp/McpTasksHealthCheckCommandTest.php']],
    ['UC-MCP-02', ['Modules/Jana/Tests/Feature/Mcp/OutroTest.php']],
  ]);
  const r = classificar(['UC-MCP-01', 'UC-MCP-02', 'UC-MCP-03'], porUc, alvosLane);
  ok('BITE: UC com título em arquivo FORA de lane → semLane',
    r.semLane.length === 1 && r.semLane[0].uc === 'UC-MCP-01');
  ok('CONTROLE NEGATIVO: UC com título em arquivo NA lane → emLane',
    r.emLane.length === 1 && r.emLane[0] === 'UC-MCP-02');
  ok('CONTROLE NEGATIVO: UC sem título nenhum NÃO vira semLane (é o teto do guard)',
    r.semTitulo.length === 1 && r.semTitulo[0] === 'UC-MCP-03');

  // Um UC pode ter título em VÁRIOS arquivos: basta UM em lane pra estar coberto.
  // Sem isto, acusaríamos UC que roda — falso-positivo caro.
  const rDois = classificar(['UC-X-01'], new Map([
    ['UC-X-01', ['tests/Feature/ForaTest.php', 'tests/Feature/DentroTest.php']],
  ]), ['tests/Feature/DentroTest.php']);
  ok('CONTROLE NEGATIVO: 1 arquivo em lane basta (não acusa se outro está fora)',
    rDois.semLane.length === 0 && rDois.emLane.length === 1);
  ok('BITE: TODOS os arquivos fora de lane → acusa',
    classificar(['UC-X-01'], new Map([['UC-X-01', ['a/ForaTest.php', 'b/ForaTest.php']]]), []).semLane.length === 1);

  // Cobertura por DIRETÓRIO (lane `modules-pest` roda `Modules/X/Tests`).
  ok('CONTROLE NEGATIVO: alvo-diretório cobre arquivo dentro dele',
    classificar(['UC-D-01'], new Map([['UC-D-01', ['Modules/Alpha/Tests/Feature/ATest.php']]]),
      ['Modules/Alpha/Tests']).emLane.length === 1);

  // ---- lane PLAYWRIGHT (o falso-positivo que eu mesmo produzi, 2026-08-04) ----
  // Sem estes, os 11 UCs de `e2e/*.spec.ts` viravam órfãos — e a lane roda a pasta
  // INTEIRA. O nome do spec não aparece em workflow nenhum: quem seleciona é a config.
  ok('testDir "./e2e" normaliza pra "e2e"',
    normalizaTestDir(`export default { testDir: './e2e', testMatch: '**/*.spec.ts' }`) === 'e2e');
  ok('CONTROLE NEGATIVO: config sem testDir → null',
    normalizaTestDir('export default { use: {} }') === null);
  ok('LIBERA: workflow com `npx playwright test` cobre o testDir',
    alvosDePlaywright('run: npx playwright test', {}, 'e2e').includes('e2e'));
  ok('LIBERA: `npm run e2e:check` resolve via package.json',
    alvosDePlaywright('run: npm run e2e:check', { 'e2e:check': 'playwright test' }, 'e2e').includes('e2e'));
  ok('CONTROLE NEGATIVO: npm run de OUTRO script não cobre e2e',
    alvosDePlaywright('run: npm run build', { 'e2e:check': 'playwright test', build: 'vite build' }, 'e2e').length === 0);
  ok('CONTROLE NEGATIVO: sem testDir não inventa alvo',
    alvosDePlaywright('run: npx playwright test', {}, null).length === 0);
  ok('CONTROLE NEGATIVO: menção a e2e/** só no paths: não cobre',
    alvosDePlaywright("on:\n  pull_request:\n    paths:\n      - 'e2e/**'", {}, 'e2e').length === 0);
  ok('INTEGRAÇÃO: spec e2e coberto pelo testDir não vira órfão',
    classificar(['UC-OFI-06'], new Map([['UC-OFI-06', ['e2e/oficina-uc06-gate-etapa.spec.ts']]]),
      alvosDePlaywright('run: npm run e2e:check', { 'e2e:check': 'playwright test' }, 'e2e')).semLane.length === 0);

  // ---- integração com o dono (prova que o import está vivo, não só importado) ----
  const alvosDoDono = extrairAlvos([
    'jobs:', '  x:', '    steps:', '      - run: |',
    '          vendor/bin/pest Modules/Y/Tests/Feature/NaLaneTest.php',
  ].join('\n'));
  ok('INTEGRAÇÃO: extrairAlvos do dono alimenta a classificação',
    classificar(['UC-Y-01'], new Map([['UC-Y-01', ['Modules/Y/Tests/Feature/NaLaneTest.php']]]),
      alvosDoDono).emLane.length === 1);

  const falhas = casos.filter((c) => !c.ok);
  for (const c of casos) console.log(`  ${c.ok ? '✓' : '✗'} ${c.nome}`);
  console.log(`\n  ${casos.length - falhas.length}/${casos.length} — ${falhas.length ? 'FALHOU' : 'a lógica morde (bite + controles negativos)'}`);
  return falhas.length === 0 ? 0 : 1;
}

// ─────────────────────────────────── main ─────────────────────────────────────

const ehCli = /uc-sem-lane\.mjs$/.test(process.argv[1] || '');
const args = process.argv.slice(2);
if (!ehCli) { /* importado como módulo: nada roda */ }
else if (args.includes('--selftest')) process.exit(selftest());
else {

const filtroModulo = (() => {
  const i = args.indexOf('--modulo');
  return i >= 0 ? args[i + 1] : null;
})();

const { alvos, lanesLidas } = coletarAlvos();
const decls = ucsDeclarados();
const porUc = indiceUcPorArquivo();
const distintos = [...new Set(decls.map((d) => d.uc))];
const { semTitulo, emLane, semLane } = classificar(distintos, porUc, alvos);

// módulo do casos.md que DECLARA o UC (não o do teste — o dono do UC é a tela).
const moduloDoUc = new Map();
for (const { uc, file } of decls) if (!moduloDoUc.has(uc)) moduloDoUc.set(uc, moduloDe(file));

const achados = semLane
  .map((s) => ({ ...s, modulo: moduloDoUc.get(s.uc) || '?' }))
  .filter((s) => !filtroModulo || s.modulo === filtroModulo)
  .sort((a, b) => a.modulo.localeCompare(b.modulo) || a.uc.localeCompare(b.uc));

const resultado = {
  lanes_lidas: lanesLidas,
  alvos_totais: alvos.length,
  ucs_declarados: distintos.length,
  ucs_no_titulo: distintos.length - semTitulo.length,
  // SEM título. NÃO chamar de "só docblock": o `casos-coverage-guard` (dono) separa os
  // que são citados em docblock dos que não são citados em teste NENHUM (= `uc-orphan`,
  // outra violação). Medido em 2026-08-04: destes, o guard atribui 104 a docblock — o
  // resto não é citado em lugar algum. Repetir o 104 aqui seria restatear número que
  // outro sistema sabe melhor (proibicoes §5 2026-07-17); quem quer a quebra roda
  // `node scripts/casos-coverage-guard.mjs --report`.
  ucs_sem_titulo: semTitulo.length,
  ucs_titulo_em_lane: emLane.length,
  ucs_titulo_sem_lane: semLane.length, // ← a categoria (c)
  achados,
};

if (args.includes('--json')) {
  console.log(JSON.stringify(resultado, null, 2));
  process.exit(0);
}

console.log('\n=== UC alcançável × nunca alcançado (id no título, lane nenhuma executa) ===\n');
if (lanesLidas === 0 || alvos.length === 0) {
  console.log('⚠️  lanes_lidas=0 ou alvos=0 — o parsing não achou invocação de pest.');
  console.log('   NÃO leia isto como "tudo órfão": é ausência de MEDIÇÃO.\n');
}
console.log(`lanes com pest: ${lanesLidas}  ·  alvos extraídos: ${alvos.length}`);
console.log(`UCs declarados: ${resultado.ucs_declarados}`);
console.log(`  ├─ SEM id no título:        ${resultado.ucs_sem_titulo}  (teto do casos-coverage-guard — outro problema)`);
console.log(`  ├─ id no título E em lane:  ${resultado.ucs_titulo_em_lane}`);
console.log(`  └─ id no título SEM lane:   ${resultado.ucs_titulo_sem_lane}  ← alcançável, nunca alcançado\n`);

if (achados.length === 0) {
  console.log('✓ nenhum UC com título fora de lane.\n');
} else {
  let modAtual = null;
  for (const a of achados) {
    if (a.modulo !== modAtual) { console.log(`\n[${a.modulo}]`); modAtual = a.modulo; }
    console.log(`  ${a.uc}`);
    for (const f of a.arquivos) console.log(`      ${f}   ← nenhuma lane o executa`);
  }
  console.log('\n  O teste existe, tem o id no título e NUNCA roda em lane de PR — logo o UC');
  console.log('  jamais vira execução-backed no manifesto G-7, e fica 🧪 sem ninguém saber por quê.');
  console.log('  Ação: ancore o arquivo em .github/ci-sqlite-pest.list (se sqlite-safe) ou na');
  console.log('  lane MySQL do módulo. Antes disso, rode-o no CT 100 — dívida pré-existente');
  console.log('  costuma aparecer no dia em que a lane liga (proibicoes §5 2026-07-28).');
  console.log('  (advisory — não bloqueia; o número é relato, não veredito)\n');
}
}
