#!/usr/bin/env node
// test-lane-coverage.mjs — quais testes EXISTEM × quais o CI realmente EXECUTA.
//
// ── O BURACO QUE ISTO FECHA ─────────────────────────────────────────────────
// Nenhuma lane deste repo usa `--testsuite`: as 27 invocações de teste nos 117
// workflows passam ALVOS EXPLÍCITOS na linha de comando (arquivo ou diretório).
// Logo `phpunit.xml` NÃO decide o que roda no CI — ele serve a config/bootstrap
// e à execução local. Consequência medida em 2026-08-02: 5 arquivos de
// `Modules/Jana/Tests/Unit` e 40 de `Modules/RecurringBilling/Tests/Feature`
// existiam no repo, contavam como "cobertura de PR", e NENHUMA lane de PR os
// alcançava (rodavam só na nightly — ver ⚠️ abaixo).
// Descobrir isso exigiu varrer os 117 workflows à mão.
//
// A allowlist per-lane é DESENHO CORRETO (sqlite-safe, ratchet — ci-sqlite-pest.list
// diz isso explicitamente). O que faltava era medir o COMPLEMENTO dela: o que ficou
// de fora. Este script é esse complemento — reporta, não decide.
//
// ── O QUE ELE NÃO É (leia, o nome engana) ───────────────────────────────────
// Não mede cobertura de LINHA (isso é scripts/tests/coverage-compute.mjs, clover
// da nightly CT100). Não mede se o teste PASSOU (isso é junit-summary.mjs, e o
// discriminador `--check-assertions` de lá). Aqui a pergunta é anterior às duas:
// o arquivo de teste é ALCANÇADO por alguma lane DE PR?
//
// ⚠️ "FORA DO CI DE PR" ≠ "NUNCA RODA". A nightly do CT 100
// (scripts/tests/ct100-fullsuite.sh) roda SHARDED sobre `--roots tests,Modules`,
// isto é, a árvore INTEIRA, excluindo só `tests/Browser` e `tests/governance-fixtures`.
// Logo quase todo teste roda ao menos 1×/dia. O que este script mede é LATÊNCIA DE
// FEEDBACK: rodar no PR (minutos, antes do merge) × rodar na nightly (horas, depois).
// Chamar isso de "não tem cobertura" seria alarmismo — e mediria a fonte errada.
//
// Por que ainda importa: o caso que originou este script foi o `PiiRedactorTest`
// (19 casos, LGPD) fora da lane enquanto o PR alterava `PiiRedactor.php` em +98
// linhas — o teste de regressão da classe só falaria no dia seguinte, depois do
// merge. Feedback tardio em código Tier 0 é risco real; invisibilidade total não é
// o que acontece aqui.
//
// ── LIMITE HONESTO (leia antes de confiar) ──────────────────────────────────
// Parsing é TEXTUAL (regex sobre o YAML), não AST — Node puro, sem deps, igual
// ao gate-selftest. Isso significa: um workflow que monte a lista de alvos de
// forma dinâmica (variável de shell, script externo, glob expandido em runtime)
// pode não ser lido. O script REPORTA `lanes_lidas` e `alvos_totais` justamente
// pra que "0 alvos" salte aos olhos em vez de virar falso "tudo órfão".
//
// USO:
//   node scripts/governance/test-lane-coverage.mjs            # relatório
//   node scripts/governance/test-lane-coverage.mjs --json     # consumo por outra máquina
//   node scripts/governance/test-lane-coverage.mjs --modulo Jana
//   node scripts/governance/test-lane-coverage.mjs --selftest # bite-test (boa/ruim)

import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = process.cwd();
const WF_DIR = join(ROOT, '.github', 'workflows');
const LISTA_CURADA = join(ROOT, '.github', 'ci-sqlite-pest.list');

// ───────────────────────── lógica pura (testável pelo --selftest) ─────────────

/**
 * Extrai os alvos de teste de UM texto de workflow.
 * Entende os 3 padrões vivos do repo:
 *   (1) lane individual — arquivos listados após `vendor/bin/pest`
 *   (2) matriz — `Modules/${{ matrix.module }}/Tests` expandido pelos valores da matriz
 *   (3) lista externa — workflow que lê `.github/ci-sqlite-pest.list`
 * `entradasDaLista` é injetado (não lido do disco) pra manter a função pura.
 */
export function extrairAlvos(yamlText, entradasDaLista = []) {
  const alvos = new Set();

  // (2) valores da matriz `module:` — usados pra expandir ${{ matrix.module }}
  const modulosDaMatriz = [];
  const linhas = yamlText.split(/\r?\n/);
  let dentroMatrizModule = false;
  for (const linha of linhas) {
    if (/^\s*module:\s*$/.test(linha)) { dentroMatrizModule = true; continue; }
    if (dentroMatrizModule) {
      const item = linha.match(/^\s*-\s*([A-Za-z][A-Za-z0-9_]*)\s*$/);
      if (item) { modulosDaMatriz.push(item[1]); continue; }
      if (linha.trim() !== '') dentroMatrizModule = false;
    }
  }

  // (1) alvos citados em qualquer invocação do pest.
  // Casa caminhos de teste no corpo do `run:` — conservador: só paths que
  // começam em `Modules/` ou `tests/` e seguem até espaço/backslash/aspas.
  const invocaPest = /vendor\/bin\/pest/.test(yamlText);
  if (invocaPest) {
    // Só as LINHAS DE COMANDO contam. A 1ª versão fatiava do 1º `vendor/bin/pest`
    // até o FIM DO ARQUIVO — e engolia texto que não é comando: em
    // visual-regression.yml:680 a string de AJUDA de um comentário de PR
    // ("./vendor/bin/pest tests/Browser/ --update-snapshots") virava alvo-diretório
    // e "cobria" os 12 arquivos de tests/Browser por prefixo. Um deles
    // (NfeBrasil/NfceStatusTest.php) não roda em lane nenhuma E a nightly exclui
    // tests/Browser: o script marcava como coberto justamente o teste que não roda
    // em lugar nenhum — o defeito que ele existe pra achar. (adversário 2026-08-02)
    //
    // Heurística conservadora: descarta linha que é literal de string de script
    // (aspas + vírgula final, padrão do actions/github-script) e linha de markdown
    // de comentário. Preferir FALSO-ÓRFÃO a FALSO-COBERTO: acusar demais é ruído
    // que se investiga; acusar de menos esconde o teste que ninguém roda.
    const linhasComando = yamlText
      .slice(yamlText.indexOf('vendor/bin/pest'))
      .split(/\r?\n/)
      .filter((l) => {
        const t = l.trim();
        if (/^['"].*['"],?$/.test(t)) return false;   // literal de array JS (github-script)
        if (/^#/.test(t)) return false;               // comentário YAML
        if (/^['"]?\s*\d+\.\s/.test(t)) return false; // item numerado de mensagem
        return true;
      })
      .join('\n');
    // `${{ matrix.module }}` CONTÉM ESPAÇOS — vira placeholder sem espaço antes do
    // regex, senão o match para no 1º espaço e a matriz nunca expande (bug pego
    // pelo próprio --selftest antes deste script ser usado pra decidir qualquer coisa).
    const desdePest = linhasComando
      .replace(/\$\{\{\s*matrix\.module\s*\}\}/g, '__MATRIXMODULE__');
    const re = /(?:^|[\s'"])((?:Modules|tests)\/[A-Za-z0-9_\-./]*?)(?=[\s'"\\]|$)/gm;
    let m;
    while ((m = re.exec(desdePest)) !== null) {
      let alvo = m[1].replace(/[.,;]+$/, '');
      if (!alvo || alvo.endsWith('.list')) continue;
      if (alvo.includes('__MATRIXMODULE__')) {
        // expande a matriz; SEM valores, descarta — nunca inventa cobertura
        // (um molde não-expandido cobriria tudo por prefixo e zeraria os órfãos).
        for (const mod of modulosDaMatriz) {
          alvos.add(alvo.replaceAll('__MATRIXMODULE__', mod));
        }
        continue;
      }
      // só interessa caminho de teste (arquivo .php ou diretório Tests/...)
      if (alvo.endsWith('.php') || /\/Tests(\/|$)/.test(alvo) || /^tests\//.test(alvo)) {
        alvos.add(alvo);
      }
    }
  }

  // (3) lista curada externa
  if (yamlText.includes('ci-sqlite-pest.list')) {
    for (const e of entradasDaLista) alvos.add(e);
  }

  // (4) DENYLIST — `find <dir> -name '*Test.php'` roda a ÁRVORE INTEIRA menos a
  // quarentena (padrão do financeiro-pest.yml, estritamente melhor que allowlist:
  // teste novo entra sozinho). Sem isto o módulo aparecia 100% órfão — falso.
  const reFind = /find\s+((?:Modules|tests)\/[A-Za-z0-9_\-./]+)\s+-name\s+'\*Test\.php'/g;
  let mf;
  while ((mf = reFind.exec(yamlText)) !== null) alvos.add(mf[1]);

  // (5) extras nomeados fora da árvore do módulo (`echo 'path' >> run.txt`)
  const reEcho = /echo\s+'((?:Modules|tests)\/[A-Za-z0-9_\-./]+Test\.php)'\s*>>/g;
  let me;
  while ((me = reEcho.exec(yamlText)) !== null) alvos.add(me[1]);

  return [...alvos].sort();
}

/**
 * Um teste está coberto se algum alvo é ele mesmo OU um diretório ancestral.
 * (a lane `modules-pest` roda `Modules/X/Tests` — diretório inteiro.)
 */
export function estaCoberto(teste, alvos) {
  for (const alvo of alvos) {
    if (alvo === teste) return true;
    const dir = alvo.endsWith('/') ? alvo : alvo + '/';
    if (!alvo.endsWith('.php') && teste.startsWith(dir)) return true;
  }
  return false;
}

// ───────────────────────────────── coleta (I/O) ───────────────────────────────

/**
 * Diretórios que NÃO são teste real. O critério NÃO é inventado aqui: é lido do
 * `SHARD_EXCLUDE` do ct100-fullsuite.sh, que já os poda e explica por quê
 * ("tests/governance-fixtures = testes SINTETICOS bad/good dos gates, nao reais").
 * Ler do dono evita dois critérios divergindo — se lá mudar, aqui acompanha.
 * `tests/Browser` fica FORA desta poda: são testes reais (11 dos 12 rodam em PR);
 * a nightly os exclui por falta de Playwright na imagem, não por não serem testes.
 */
function dirsSinteticos() {
  const sh = join(ROOT, 'scripts', 'tests', 'ct100-fullsuite.sh');
  if (!existsSync(sh)) return ['tests/governance-fixtures'];
  const m = readFileSync(sh, 'utf8').match(/SHARD_EXCLUDE="\$\{FULLSUITE_SHARD_EXCLUDE:-([^}"]+)\}"/);
  const todos = m ? m[1].split(',').map((s) => s.trim()).filter(Boolean) : [];
  return todos.filter((d) => d.includes('fixtures'));
}

function testesExistentes() {
  const out = execFileSync('git', ['ls-files', 'Modules', 'tests'], {
    cwd: ROOT, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024,
  });
  const sinteticos = dirsSinteticos();
  return out
    .split(/\r?\n/)
    .filter((p) => /Test\.php$/.test(p) && /(^|\/)([Tt]ests)\//.test(p))
    .filter((p) => !sinteticos.some((d) => p.startsWith(d + '/')))
    .sort();
}

/**
 * Regra ÚNICA de leitura de `.list` — PARIDADE EXATA com o que as lanes fazem no
 * shell (o `sed` delas corta do `#` ao fim da linha e apara espaço à direita):
 * corta do primeiro `#` até o fim da linha, trima, descarta vazio.
 *
 * Por que virou helper único (2026-08-07): havia DUAS leituras do mesmo formato
 * neste arquivo, ambas `trim()` + `startsWith('#')` — que só remove a linha
 * INTEIRA de comentário e deixa o inline colado no path. Medido em origin/main:
 * `financeiro-pest-quarantine.list` 24/24 e `estoque-pest-quarantine.list` 21/21
 * usam comentário inline (o motivo por linha é OBRIGATÓRIO por desenho das duas
 * listas). Resultado: as 45 entradas viravam strings do tipo
 * `"caminho/X.php   # motivo"`, que não casam path nenhum — e os 45 arquivos
 * conscientemente quarentenados eram contados como ÓRFÃOS, apagando justamente
 * a distinção que este script existe pra mostrar.
 *
 * `ci-sqlite-pest.list` não tinha inline nenhum (0/149) — lá o defeito era
 * latente, não ativo. Uma regra só pros dois evita que ele acorde depois.
 */
function entradasDeLista(txt) {
  return txt
    .split(/\r?\n/)
    .map((l) => l.split('#')[0].trim())
    .filter(Boolean);
}

function entradasDaListaCurada() {
  if (!existsSync(LISTA_CURADA)) return [];
  return entradasDeLista(readFileSync(LISTA_CURADA, 'utf8'));
}

/**
 * Testes em quarentena declarada (`.github/*-quarantine.list`). NÃO são órfãos:
 * alguém decidiu conscientemente que não rodam, e a lane imprime a lista.
 * Órfão é o que ninguém decidiu — some sem ninguém saber. Misturar os dois
 * apagaria justamente a diferença que este script existe pra mostrar.
 */
function emQuarentena() {
  const dir = join(ROOT, '.github');
  if (!existsSync(dir)) return [];
  const out = new Set();
  for (const f of readdirSync(dir).filter((f) => /quarantine.*\.list$/i.test(f))) {
    for (const t of entradasDeLista(readFileSync(join(dir, f), 'utf8'))) out.add(t);
  }
  return [...out];
}

function coletarAlvos() {
  const lista = entradasDaListaCurada();
  const alvos = new Set();
  let lanesLidas = 0;
  if (!existsSync(WF_DIR)) return { alvos: [], lanesLidas: 0 };
  for (const f of readdirSync(WF_DIR).filter((f) => /\.ya?ml$/.test(f))) {
    const txt = readFileSync(join(WF_DIR, f), 'utf8');
    if (!/vendor\/bin\/pest|ci-sqlite-pest\.list/.test(txt)) continue;
    lanesLidas++;
    for (const a of extrairAlvos(txt, lista)) alvos.add(a);
  }
  return { alvos: [...alvos].sort(), lanesLidas };
}

function moduloDe(teste) {
  const m = teste.match(/^Modules\/([^/]+)\//);
  return m ? m[1] : '(raiz tests/)';
}

// ══════════════════ EIXO 2: ALCANÇADO × DEIXADO RODAR (2026-08-10) ═══════════
//
// O eixo acima responde "a lane ALCANÇA o arquivo?". Este responde o nível
// seguinte: "a lane que o alcança deixa ele RODAR?".
//
// O caso que originou: `Modules/Jana/Tests/Feature/Ai/BriefDiarioAgentTest.php`
// está na ÚLTIMA linha do bloco `ALLOWLIST VERDE (catraca)` de `jana-pest.yml`
// — lane que roda `DB_CONNECTION: mysql`. O arquivo faz `markTestSkipped` quando
// o driver NÃO é sqlite. Não está na lista sqlite. A nightly do CT 100 também é
// MySQL. Resultado: nunca roda, em superfície nenhuma, e sai VERDE — skip é
// exit 0. Um dos seus 6 casos é uma trava multi-tenant Tier 0 (ADR 0093).
//
// Pro eixo 1 ele conta como COBERTO, e está certo: a lane o alcança. O defeito
// é que alcançar não é executar.
//
// ⚠️ LIMITE: skip por driver é o único cruzamento medido aqui. Skip por env
// ausente, por `RefreshDatabase`, por feature-flag ou por `todo()` NÃO é visto.
// "Não aparece aqui" não significa "executa".

/** Driver que a lane oferece. Ausente ⇒ sqlite (phpunit.xml o força). */
export function driverDaLane(yamlText) {
  const m = yamlText.match(/DB_CONNECTION:\s*["']?(\w+)/);
  return m ? m[1] : 'sqlite';
}

/**
 * Driver que o arquivo EXIGE, por guard de topo. `null` = não exige nada.
 *
 * TOP-LEVEL = o guard aparece ANTES do primeiro `it(`/`test(`, logo vale pro
 * arquivo inteiro. Guard DEPOIS do primeiro caso pula só aquele caso — contá-lo
 * seria falso-positivo. Medido no corpus 2026-08-10: descartar os por-caso tira
 * **15** arquivos que o critério ingênuo acusaria à toa.
 */
export function guardDeDriver(phpText) {
  const primeiroCaso = phpText.search(/^\s*(?:it|test)\s*\(/m);
  const antes = (re) => {
    const g = phpText.search(re);
    return g >= 0 && (primeiroCaso < 0 || g < primeiroCaso);
  };
  // `!== 'sqlite'` ⇒ pula quando NÃO é sqlite ⇒ EXIGE sqlite.
  if (antes(/getDriverName\(\)\s*!==\s*['"]sqlite['"]/)) return 'sqlite';
  // `=== 'sqlite'` ⇒ pula quando É sqlite ⇒ EXIGE outro (mysql).
  if (antes(/getDriverName\(\)\s*===\s*['"]sqlite['"]/)) return 'mysql';
  return null;
}

/** Alvos agrupados pelo driver da lane que os executa. */
function alvosPorDriver() {
  const lista = entradasDaListaCurada();
  const porDriver = new Map();
  if (!existsSync(WF_DIR)) return porDriver;
  for (const f of readdirSync(WF_DIR).filter((f) => /\.ya?ml$/.test(f))) {
    const txt = readFileSync(join(WF_DIR, f), 'utf8');
    if (!/vendor\/bin\/pest|ci-sqlite-pest\.list/.test(txt)) continue;
    const drv = driverDaLane(txt);
    if (!porDriver.has(drv)) porDriver.set(drv, new Set());
    for (const a of extrairAlvos(txt, lista)) porDriver.get(drv).add(a);
  }
  return porDriver;
}

/**
 * MUDOS: o arquivo exige um driver que NENHUMA lane que o alcança oferece.
 * Pode ser alcançado por várias lanes — basta UMA com o driver certo pra não ser mudo.
 */
export function testesMudos(testes, porDriver, lerArquivo) {
  const mudos = [];
  for (const t of testes) {
    let txt = '';
    try { txt = lerArquivo(t); } catch { continue; }
    const exige = guardDeDriver(txt);
    if (!exige) continue;
    const alvosDoDriver = [...(porDriver.get(exige) || [])];
    if (estaCoberto(t, alvosDoDriver)) continue; // alguma lane certa o alcança
    // só é MUDO se alguma lane o alcança (senão é órfão puro, eixo 1)
    const alcancadoPorAlguem = [...porDriver.values()].some((s) => estaCoberto(t, [...s]));
    mudos.push({ teste: t, exige, alcancado: alcancadoPorAlguem });
  }
  return mudos;
}

/**
 * FORWARD-ONLY: dado o conjunto de arquivos-fonte tocados por um PR, aponta os que
 * TÊM teste no repo mas cujo teste NÃO é alcançado por lane de PR.
 *
 * É o caso exato do incidente que originou tudo: `PiiRedactor.php` alterado em +98
 * linhas com `PiiRedactorTest.php` (19 casos, LGPD) fora da lista de execução — o
 * `paths:` do trigger CITAVA o arquivo, então a lane disparava e não rodava o teste
 * dele. Trigger dispara a lane; a LISTA decide o que roda. Confundir os dois é o
 * defeito.
 *
 * Casamento por convenção de nome (`Foo.php` → `FooTest.php`). Deliberadamente
 * simples: não infere por conteúdo. Falso-negativo (teste com outro nome) é aceito;
 * falso-positivo seria ruído em todo PR.
 */
export function testesDeRisco(arquivosTocados, testes, alvos) {
  const porBase = new Map();
  for (const t of testes) {
    const base = t.split('/').pop().replace(/\.php$/, '');
    if (!porBase.has(base)) porBase.set(base, []);
    porBase.get(base).push(t);
  }
  const achados = [];
  for (const src of arquivosTocados) {
    if (!/\.php$/.test(src) || /Test\.php$/.test(src)) continue;
    const base = src.split('/').pop().replace(/\.php$/, '');
    for (const t of porBase.get(base + 'Test') || []) {
      if (!estaCoberto(t, alvos)) achados.push({ fonte: src, teste: t });
    }
  }
  return achados;
}

// ───────────────────────────────── selftest ───────────────────────────────────

function selftest() {
  const casos = [];
  const ok = (nome, cond) => casos.push({ nome, ok: !!cond });

  // BITE: alvo só no `paths:` do trigger NÃO conta como execução.
  // (é literalmente o defeito do PiiRedactorTest: estava no paths, fora do comando)
  const wfSoPaths = [
    'on:', '  pull_request:', '    paths:',
    "      - 'Modules/X/Tests/Unit/AlvoTest.php'",
    'jobs:', '  pest:', '    steps:',
    '      - run: |', '          vendor/bin/pest Modules/X/Tests/Unit/OutroTest.php',
  ].join('\n');
  const aSoPaths = extrairAlvos(wfSoPaths);
  ok('BITE: arquivo só no paths: não vira alvo',
    !aSoPaths.includes('Modules/X/Tests/Unit/AlvoTest.php'));
  ok('LIBERA: arquivo no comando vira alvo',
    aSoPaths.includes('Modules/X/Tests/Unit/OutroTest.php'));

  // BITE: o órfão real é detectado
  ok('BITE: teste fora dos alvos é órfão',
    !estaCoberto('Modules/X/Tests/Unit/AlvoTest.php', aSoPaths));

  // matriz expandida
  const wfMatriz = [
    'jobs:', '  pest:', '    strategy:', '      matrix:', '        module:',
    '          - Alpha', '          - Beta', '    steps:',
    '      - run: |', '          vendor/bin/pest Modules/${{ matrix.module }}/Tests \\',
    '            --no-coverage',
  ].join('\n');
  const aMatriz = extrairAlvos(wfMatriz);
  ok('matriz expande p/ cada módulo',
    aMatriz.includes('Modules/Alpha/Tests') && aMatriz.includes('Modules/Beta/Tests'));
  ok('diretório cobre arquivo dentro dele',
    estaCoberto('Modules/Alpha/Tests/Unit/QualquerTest.php', aMatriz));
  ok('CONTROLE NEGATIVO: módulo fora da matriz não é coberto',
    !estaCoberto('Modules/Gama/Tests/Unit/QualquerTest.php', aMatriz));

  // ── entradasDeLista: paridade com o `sed 's/#.*//'` das lanes ──────────────
  // BITE do defeito real (2026-08-07): comentário INLINE é obrigatório nas duas
  // listas de quarentena (motivo por linha), e a regra antiga o colava no path.
  const listaInline = [
    '# cabeçalho de bloco — linha inteira, some',
    'tests/Feature/Produto/AlvoTest.php          # UC-X-01: motivo escrito',
    '   tests/Feature/Produto/OutroTest.php\t# outro motivo',
    '',
    'tests/Feature/Produto/LimpoTest.php',
    '   # comentário indentado também some',
  ].join('\n');
  const eInline = entradasDeLista(listaInline);
  ok('BITE: comentário inline NÃO gruda no path',
    eInline.includes('tests/Feature/Produto/AlvoTest.php'));
  ok('BITE: nenhuma entrada carrega "#" depois do parse',
    eInline.every((e) => !e.includes('#')));
  ok('LIBERA: linha sem comentário passa intacta',
    eInline.includes('tests/Feature/Produto/LimpoTest.php'));
  ok('trima espaço à esquerda e tab antes do #',
    eInline.includes('tests/Feature/Produto/OutroTest.php'));
  ok('CONTROLE NEGATIVO: linha 100% comentário não vira entrada',
    eInline.length === 3);
  ok('CONTROLE NEGATIVO: linha vazia não vira entrada',
    !eInline.includes(''));
  // Fecha o laço com o consumidor: entrada parseada tem que CASAR o teste real,
  // senão o arquivo quarentenado volta a ser contado como órfão.
  ok('BITE (ponta-a-ponta): entrada parseada casa o path do teste',
    estaCoberto('tests/Feature/Produto/AlvoTest.php', eInline));

  // lista curada
  const wfLista = ['jobs:', '  x:', '    steps:', '      - run: |',
    '          mapfile -t T < .github/ci-sqlite-pest.list',
    '          vendor/bin/pest "${T[@]}"'].join('\n');
  const aLista = extrairAlvos(wfLista, ['Modules/Y/Tests/Feature/DaListaTest.php']);
  ok('lista curada externa entra como alvo',
    aLista.includes('Modules/Y/Tests/Feature/DaListaTest.php'));

  // CONTROLE NEGATIVO: workflow sem pest não contribui alvo
  ok('CONTROLE NEGATIVO: workflow sem pest → 0 alvos',
    extrairAlvos('jobs:\n  x:\n    steps:\n      - run: npm ci').length === 0);

  // (4) DENYLIST — `find <dir>` cobre a árvore (padrão financeiro-pest).
  // Sem isto o módulo inteiro aparecia órfão: falso-positivo de 80 arquivos.
  const wfFind = [
    'jobs:', '  x:', '    steps:', '      - run: |',
    "          find Modules/Fin/Tests -name '*Test.php' | sort > /tmp/all.txt",
    "          echo 'tests/Feature/Extra/ForaDaArvoreTest.php' >> /tmp/run.txt",
    '          vendor/bin/pest "${TARGETS[@]}"',
  ].join('\n');
  const aFind = extrairAlvos(wfFind);
  ok('DENYLIST: find <dir> cobre a árvore inteira',
    estaCoberto('Modules/Fin/Tests/Feature/QualquerTest.php', aFind));
  ok('extra nomeado via echo >> vira alvo',
    estaCoberto('tests/Feature/Extra/ForaDaArvoreTest.php', aFind));
  ok('CONTROLE NEGATIVO: find de um módulo não cobre outro',
    !estaCoberto('Modules/Outro/Tests/Feature/QualquerTest.php', aFind));

  // FORWARD-ONLY — reproduz o incidente do PiiRedactor (2026-08-02):
  // a fonte é tocada, o teste dela existe, e nenhuma lane de PR o alcança.
  const testesRepo = [
    'Modules/Jana/Tests/Unit/PiiRedactorTest.php',
    'Modules/Jana/Tests/Unit/PiiRedactorNumeroCruTest.php',
  ];
  const alvosLane = ['Modules/Jana/Tests/Unit/PiiRedactorNumeroCruTest.php'];
  const risco = testesDeRisco(['Modules/Jana/Services/Privacy/PiiRedactor.php'], testesRepo, alvosLane);
  ok('BITE forward-only: fonte tocada + teste fora da lane = risco',
    risco.length === 1 && risco[0].teste.endsWith('PiiRedactorTest.php'));
  ok('CONTROLE NEGATIVO: teste JÁ na lane não vira risco',
    testesDeRisco(['Modules/Jana/Services/Privacy/PiiRedactorNumeroCru.php'], testesRepo, alvosLane).length === 0);
  ok('CONTROLE NEGATIVO: tocar o próprio arquivo de teste não vira risco',
    testesDeRisco(['Modules/Jana/Tests/Unit/PiiRedactorTest.php'], testesRepo, alvosLane).length === 0);
  ok('CONTROLE NEGATIVO: fonte sem teste correspondente não vira risco',
    testesDeRisco(['Modules/Jana/Services/Privacy/SemTeste.php'], testesRepo, alvosLane).length === 0);

  // BITE do falso-COBERTO achado pelo adversário (2026-08-02): path dentro de
  // string de AJUDA (actions/github-script) não é comando e não pode virar alvo.
  // Era assim que tests/Browser/NfeBrasil/NfceStatusTest.php — que não roda em
  // lane nenhuma e é excluído da nightly — aparecia coberto.
  const wfAjuda = [
    'jobs:', '  x:', '    steps:', '      - run: |',
    '          vendor/bin/pest tests/Browser/RealTest.php',
    '      - uses: actions/github-script@v7',
    '        with:', '          script: |',
    '            const corpo = [',
    "              '   ./vendor/bin/pest tests/Browser/ --update-snapshots',",
    "              '   git add tests/Browser/Screenshots/',",
    '            ]',
  ].join('\n');
  const aAjuda = extrairAlvos(wfAjuda);
  ok('BITE: path em string de AJUDA não vira alvo-diretório',
    !aAjuda.includes('tests/Browser/'));
  ok('LIBERA: o comando real da mesma lane segue coberto',
    estaCoberto('tests/Browser/RealTest.php', aAjuda));
  ok('BITE: teste irmão não roda por causa da string de ajuda',
    !estaCoberto('tests/Browser/NfeBrasil/NfceStatusTest.php', aAjuda));

  // arquivo .php nunca é tratado como diretório
  ok('CONTROLE NEGATIVO: .php não cobre irmão por prefixo',
    !estaCoberto('Modules/X/Tests/Unit/OutroTest.php.bak',
      ['Modules/X/Tests/Unit/OutroTest.php']));

  // ─────────── EIXO 2: alcançado × deixado rodar (driver × guard) ───────────

  ok('driver: lane com DB_CONNECTION: mysql', driverDaLane('env:\n  DB_CONNECTION: mysql') === 'mysql');
  ok('driver: lane sem DB_CONNECTION ⇒ sqlite (phpunit.xml força)', driverDaLane('jobs:\n  pest:') === 'sqlite');

  const phpSqliteOnly = [
    '<?php', "beforeEach(function () {", "    if (DB::connection()->getDriverName() !== 'sqlite') {",
    "        test()->markTestSkipped('era-sqlite');", '    }', '});',
    "it('faz algo', function () { expect(1)->toBe(1); });",
  ].join('\n');
  ok('guard: exige sqlite quando pula fora de sqlite', guardDeDriver(phpSqliteOnly) === 'sqlite');

  const phpMysqlOnly = [
    '<?php', 'beforeEach(function () {', "    if (DB::connection()->getDriverName() === 'sqlite') {",
    "        test()->markTestSkipped('precisa MySQL');", '    }', '});',
    "it('faz algo', function () {});",
  ].join('\n');
  ok('guard: exige mysql quando pula em sqlite', guardDeDriver(phpMysqlOnly) === 'mysql');

  // CONTROLE NEGATIVO — o FP que o critério ingênuo cometeria (15 arquivos medidos
  // no corpus 2026-08-10): guard DEPOIS do primeiro caso pula só aquele caso.
  const phpPorCaso = [
    '<?php', "it('caso um', function () { expect(1)->toBe(1); });",
    "it('caso dois', function () {", "    if (DB::connection()->getDriverName() !== 'sqlite') {",
    "        test()->markTestSkipped('só este caso');", '    }', '});',
  ].join('\n');
  ok('CONTROLE NEGATIVO: guard POR-CASO não vira exigência do arquivo',
    guardDeDriver(phpPorCaso) === null);
  ok('CONTROLE NEGATIVO: arquivo sem guard não exige driver',
    guardDeDriver("<?php\nit('x', function () {});") === null);

  // BITE — o caso real: listado numa lane mysql, exige sqlite, e a lane sqlite não o alcança.
  const pd = new Map([
    ['mysql', new Set(['Modules/X/Tests/Feature/AlvoTest.php'])],
    ['sqlite', new Set(['Modules/X/Tests/Feature/OutroTest.php'])],
  ]);
  const mudos = testesMudos(
    ['Modules/X/Tests/Feature/AlvoTest.php', 'Modules/X/Tests/Feature/OutroTest.php'],
    pd,
    (t) => (t.endsWith('AlvoTest.php') ? phpSqliteOnly : "<?php\nit('x', function () {});"),
  );
  ok('BITE: exige sqlite, só lane mysql o alcança ⇒ MUDO',
    mudos.length === 1 && mudos[0].teste === 'Modules/X/Tests/Feature/AlvoTest.php');
  ok('BITE: o mudo é reportado como ALCANÇADO (o eixo 1 o daria por coberto)',
    mudos[0]?.alcancado === true);

  // CONTROLE NEGATIVO — a lane certa existe ⇒ não é mudo.
  const pdOk = new Map([['sqlite', new Set(['Modules/X/Tests/Feature/AlvoTest.php'])]]);
  ok('CONTROLE NEGATIVO: lane com o driver certo ⇒ não é mudo',
    testesMudos(['Modules/X/Tests/Feature/AlvoTest.php'], pdOk, () => phpSqliteOnly).length === 0);

  const falhas = casos.filter((c) => !c.ok);
  for (const c of casos) console.log(`  ${c.ok ? '✓' : '✗'} ${c.nome}`);
  console.log(`\n  ${casos.length - falhas.length}/${casos.length} — ${falhas.length ? 'FALHOU' : 'a lógica morde (bite + controles negativos)'}`);
  return falhas.length === 0 ? 0 : 1;
}

// ─────────────────────────────────── main ─────────────────────────────────────

// Só executa como CLI. Sem esta guarda, `import` deste módulo dispararia o main
// e as funções exportadas ficariam inúteis pra outro consumidor (ex.: um teste
// .test.mjs, ou o próprio junit-summary querendo cruzar alcance x assertions).
const ehCli = /test-lane-coverage\.mjs$/.test(process.argv[1] || '');
const args = process.argv.slice(2);
if (!ehCli) { /* importado como módulo: nada roda */ }
else if (args.includes('--selftest')) process.exit(selftest());
else {

const filtroModulo = (() => {
  const i = args.indexOf('--modulo');
  return i >= 0 ? args[i + 1] : null;
})();

const { alvos, lanesLidas } = coletarAlvos();
const testes = testesExistentes();

// --diff <base>: modo FORWARD-ONLY pro PR. Não olha a dívida histórica (que é
// grande e não é deste PR); só o que ESTE PR está prestes a deixar sem rede.
if (args.includes('--diff')) {
  const base = args[args.indexOf('--diff') + 1] || 'origin/main';
  let tocados = [];
  try {
    tocados = execFileSync('git', ['diff', '--name-only', `${base}...HEAD`], {
      cwd: ROOT, encoding: 'utf8', maxBuffer: 32 * 1024 * 1024,
    }).split(/\r?\n/).filter(Boolean);
  } catch {
    console.log(`⚠️  não consegui ler o diff vs ${base} — ausência de MEDIÇÃO, não de risco.`);
    process.exit(0);
  }
  const risco = testesDeRisco(tocados, testes, alvos);
  if (risco.length === 0) {
    console.log(`✓ nenhum arquivo tocado tem teste fora das lanes de PR (${tocados.length} arquivos no diff).`);
    process.exit(0);
  }
  console.log('\n⚠️  ESTE PR toca arquivo cujo teste NÃO roda em lane de PR:\n');
  for (const r of risco) {
    console.log(`  fonte:  ${r.fonte}`);
    console.log(`  teste:  ${r.teste}   ← existe, mas nenhuma lane de PR o executa\n`);
  }
  console.log('  Isso é o incidente do PiiRedactor (2026-08-02): o teste de regressão da');
  console.log('  classe só falaria na nightly, DEPOIS do merge — e merge em main é deploy.');
  console.log('  Ação: adicione o teste à lista de execução da lane E ao paths: do trigger.');
  console.log('  (advisory — não bloqueia; o número é relato, não veredito)\n');
  process.exit(0);
}
// --mudos: eixo 2 — o arquivo é ALCANÇADO, mas o guard de topo exige um driver
// que nenhuma lane que o alcança oferece. Skip = exit 0, então sai VERDE.
if (args.includes('--mudos')) {
  const todos = testesMudos(testes, alvosPorDriver(), (t) => readFileSync(join(ROOT, t), 'utf8'));
  // SEPARAR é obrigatório: quem NENHUMA lane alcança já é órfão do eixo 1 — misturar
  // os dois inflaria o alarme com dívida conhecida. O achado NOVO é só o alcançado.
  const mudos = todos.filter((m) => m.alcancado);
  const naoAlcancados = todos.length - mudos.length;
  console.log('\n=== MUDOS: a lane ALCANÇA o arquivo, e o driver dela o faz pular ===\n');
  console.log(`  ${mudos.length} arquivo(s) — o eixo 1 os dá por COBERTOS, e eles nunca rodam`);
  console.log(`  (${naoAlcancados} outros exigem driver e nenhuma lane os alcança:`);
  console.log(`   isso é órfão do EIXO 1, já contado lá — não somar aqui)\n`);
  const porMod = {};
  for (const m of mudos) (porMod[moduloDe(m.teste)] ??= []).push(m);
  for (const [mod, lista] of Object.entries(porMod).sort((a, b) => b[1].length - a[1].length)) {
    console.log(`  ${String(lista.length).padStart(3)}  ${mod}`);
    for (const m of lista.slice(0, 3)) console.log(`         ${m.teste}  (exige ${m.exige})`);
    if (lista.length > 3) console.log(`         … +${lista.length - 3}`);
  }
  console.log('\n  ⚠️  skip sai exit 0 — estes NÃO aparecem como vermelho em lugar nenhum.');
  console.log('  ⚠️  LIMITE: só skip por DRIVER é medido. Skip por env/flag/todo() não é visto.');
  console.log('  (advisory — relato, não veredito. Ligar um deles numa lane exige rodar no CT 100.)\n');
  process.exit(0);
}

const quarentena = new Set(emQuarentena());
const orfaos = testes.filter((t) => !estaCoberto(t, alvos) && !quarentena.has(t));

const porModulo = {};
for (const t of testes) {
  const mod = moduloDe(t);
  porModulo[mod] ??= { total: 0, orfaos: 0, quarentena: 0, arquivos_orfaos: [] };
  porModulo[mod].total++;
  if (quarentena.has(t)) porModulo[mod].quarentena++;
  else if (!estaCoberto(t, alvos)) {
    porModulo[mod].orfaos++;
    porModulo[mod].arquivos_orfaos.push(t);
  }
}

const resultado = {
  lanes_lidas: lanesLidas,
  alvos_totais: alvos.length,
  testes_totais: testes.length,
  orfaos_totais: orfaos.length,
  quarentena_totais: quarentena.size,
  por_modulo: Object.fromEntries(
    Object.entries(porModulo)
      .filter(([m]) => !filtroModulo || m === filtroModulo)
      .sort((a, b) => b[1].orfaos - a[1].orfaos || a[0].localeCompare(b[0]))
  ),
};

if (args.includes('--json')) {
  console.log(JSON.stringify(resultado, null, 2));
  process.exit(0);
}

console.log('\n=== Testes: rodam no CI de PR? (nightly CT100 roda a árvore) ===\n');
if (lanesLidas === 0 || alvos.length === 0) {
  console.log('⚠️  lanes_lidas=0 ou alvos=0 — parsing não achou invocação de pest.');
  console.log('   NÃO leia isto como "tudo órfão": é ausência de MEDIÇÃO.\n');
}
console.log(`lanes com pest: ${lanesLidas}  ·  alvos extraídos: ${alvos.length}`);
console.log(`testes no repo: ${testes.length}  ·  FORA DO PR: ${orfaos.length} (${((orfaos.length / Math.max(testes.length, 1)) * 100).toFixed(1)}%)\n`);

const linhas = Object.entries(resultado.por_modulo).filter(([, v]) => v.orfaos > 0);
if (linhas.length === 0) {
  console.log('✓ nenhum teste órfão.\n');
} else {
  console.log('módulo                     fora-do-PR/total');
  console.log('─'.repeat(48));
  for (const [mod, v] of linhas) {
    console.log(`${mod.padEnd(30)} ${String(v.orfaos).padStart(5)}/${String(v.total).padEnd(5)}`);
  }
  console.log('\n(--json pra lista completa de arquivos; --modulo X pra focar)\n');
}
}
