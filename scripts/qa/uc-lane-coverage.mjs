#!/usr/bin/env node
// uc-lane-coverage.mjs — o teste que o `casos.md` cita EXISTE, e alguma lane de CI o RODA?
//
// =====================================================================================
// POR QUE EXISTE (achado medido 2026-08-23)
// =====================================================================================
// A lane `ponto-pest.yml` roda uma ALLOWLIST de 11 arquivos; o módulo tem 37 no disco.
// Os 26 de fora não são "menos importantes" — são INVISÍVEIS: existem, podem estar
// vermelhos há meses, e nenhum PR os acorda. Rodados à mão no CT 100 naquele dia, 5 deles
// devolveram `2 failed · 13 skipped · 8 passed`, incluindo:
//
//   · `DashboardTest` × `DashboardDeferredContractTest` — um afirma que `kpis` está no
//     payload inicial, o irmão afirma que a prop é DEFERIDA. Os dois no mesmo módulo,
//     contradizendo-se, verdes na consciência de todo mundo porque nenhum roda por PR.
//   · `MultiTenantIsolationTest` — `@dataProvider` foi REMOVIDA no PHPUnit 12 (o repo está
//     no 12.5.23), então o teste que varre as 10 rotas do Ponto nunca executou nenhuma.
//   · os 5 casos cross-tenant do `CrossTenantMarcacaoTest`: 100% SKIP por fixture ausente.
//
// Um `casos.md` que cite qualquer um desses na coluna `Teste` está apontando pra uma prova
// que o CI não colhe. O UC parece coberto e não está.
//
// =====================================================================================
// ESCOPO — DUAS perguntas, não quatro (o resto JÁ TEM DONO)
// =====================================================================================
// O pedido original trazia 4 perguntas. Duas delas são do `casos-coverage-guard` (G-7,
// REQUIRED), que já mede "Status MENTE (✅ declarado vs teste FALHOU)" e "Status SEM PROVA"
// contra o manifesto `scripts/casos-test-results.json` — e os dois estavam zerados no dia.
// Reimplementá-las aqui seria abrir um 2º dono do mesmo tema (§5 2026-07-09: "duplica régua
// consolidada"). Então este script responde só o que ninguém responde:
//
//   (1) o arquivo de teste citado EXISTE no disco?
//   (2) alguma lane de CI o RODA?
//
// O `Status` declarado e o `verdict` do manifesto entram como COLUNA DE CONTEXTO — lidos do
// dono, nunca recalculados aqui. `skip` é destacado porque foi o mecanismo de mascaramento
// medido (13 de 23 casos), e porque skip sai com exit 0 (LC-13).
//
// =====================================================================================
// POR QUE NÃO É DUPLICATA DO `anchor-lint --check-lane` (medido, não afirmado)
// =====================================================================================
// O `anchor-lint` já tem um check "teste-que-cobre FORA das lanes de JUnit" (G1c). Ele é
// dono do eixo **US → teste** e resolve lane por PREFIXO DE DIRETÓRIO: `junitModuleLanes()`
// varre os workflows com `--log-junit` e guarda `Modules/<X>/Tests` / `tests/Feature/<X>`.
//
// Isso o torna CEGO a allowlist dentro da pasta — e a cegueira foi medida em 2026-08-23,
// replicando `inLane()` linha a linha:
//
//   Modules/Ponto/Tests/Feature/DashboardTest.php             → anchor-lint: em-lane ✔
//   Modules/Ponto/Tests/Feature/MultiTenantIsolationTest.php  → anchor-lint: em-lane ✔
//   Modules/Ponto/Tests/Feature/CrossTenantMarcacaoTest.php   → anchor-lint: em-lane ✔
//   …e a `ponto-pest.yml` roda ONZE dos 37 arquivos daquela pasta.
//
// Os três estão fora do run-set e o dono do eixo diz que estão dentro. Não é defeito dele:
// a granularidade de pasta basta pro que ele mede. Este script trabalha em granularidade de
// ARQUIVO (allowlist, matriz, lista `.list` e quarentena) e no eixo **UC → teste**. Dois
// eixos, duas granularidades — estender o anchor-lint pra cá mudaria o veredito de um gate
// required por um caminho que ninguém pediu, e é por isso que isto nasce separado e advisory.
//
// =====================================================================================
// COMO A COBERTURA É DERIVADA (nunca presumida — e o que não dá pra derivar é declarado)
// =====================================================================================
// O run-set de cada lane sai do próprio workflow, em 4 formas medidas no repo:
//   (a) alvos LITERAIS depois de `vendor/bin/pest` (a maioria das lanes);
//   (b) `Modules/${{ matrix.module }}/Tests` — expandido pela `strategy.matrix` do job;
//   (c) `"${VAR[@]}"` alimentado por `mapfile ... < <(... .github/<algo>.list)` — lê a lista;
//   (d) `find <dir> -name '*Test.php'` MENOS um `*-quarantine.list` — o lado SUBTRATIVO.
//
// (d) existe porque inclusão tem um gêmeo (§5 2026-08-12): registro e trigger certos, e o
// arquivo continua fora porque a quarentena o remove. Ignorar isso mediria a diligência de
// quem escreveu o workflow, não o run-set real.
//
// ⚠️ REGRA DURA — `INDETERMINADO` nunca vira `NÃO COBRE`. Se o script não entende a forma
// de seleção de uma lane, ele NÃO conclui que a lane não cobre nada: marca a lane como
// indeterminada, conta quantas são, e os testes que só ela poderia cobrir saem como
// `indeterminado`, não como órfãos. Colapsar "não consegui medir" em "não coberto" é
// fabricar achado — a doença do §5 2026-07-29 (fail-open que vira frase falsa), aqui pelo
// avesso. `--check` NUNCA morde por indeterminado.
//
// USO (na raiz do repo):
//   node scripts/qa/uc-lane-coverage.mjs                 # tabela + resumo (exit 0)
//   node scripts/qa/uc-lane-coverage.mjs --check         # exit 1 se houver UC com teste órfão
//   node scripts/qa/uc-lane-coverage.mjs --check --baseline governance/uc-lane-baseline.json
//   node scripts/qa/uc-lane-coverage.mjs --write-baseline governance/uc-lane-baseline.json
//   node scripts/qa/uc-lane-coverage.mjs --json          # determinístico
//   node scripts/qa/uc-lane-coverage.mjs --lanes         # só o run-set derivado por lane
//
// BITE-TEST: node scripts/qa/uc-lane-coverage.test.mjs (irmão) — exercita o CLI de fora,
// com controle negativo e com o caso "lane indeterminada não vira órfão".

import { readFileSync, writeFileSync, existsSync, readdirSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { join, sep } from 'node:path';
import { pathToFileURL } from 'node:url';
import { ucBlocksInCasos } from '../lib/uc-regex.mjs';

const argv = process.argv.slice(2);
const has = (f) => argv.includes(f);
const valOf = (f) => { const i = argv.indexOf(f); return i >= 0 ? argv[i + 1] : null; };

const CHECK = has('--check');
const JSON_OUT = has('--json');
const LANES_ONLY = has('--lanes');
const BASELINE_PATH = valOf('--baseline');
const WRITE_BASELINE = valOf('--write-baseline');

const ROOT = valOf('--root') || process.cwd();
const posix = (p) => p.split(sep).join('/');
const abs = (p) => join(ROOT, p);

// ═════════════════════════════════════════════════════════════════════════════════════
// 1. RUN-SET POR LANE — derivado do workflow
// ═════════════════════════════════════════════════════════════════════════════════════

// ═════════════════════════════════════════════════════════════════════════════════════
// PARSER DE WORKFLOW — por LINHA, sem dependência (e o porquê disso não ser preguiça)
// ═════════════════════════════════════════════════════════════════════════════════════
//
// A 1ª versão usava `js-yaml`. Passou verde na minha máquina e QUEBROU no CI: o job
// `governance script tests` roda `node` puro, sem `npm ci` — o cabeçalho dele diz
// "Node puro, sem deps/DB/rede -- segundos", e ZERO dos ~90 scripts de governança importa
// um pacote. O meu era o único. Verde local não é verde no CI quando o ambiente difere
// (§5 2026-08-07: validar em UMA plataforma e concluir que passa).
//
// O parser abaixo lê só o que este script precisa — fronteira de job, `strategy.matrix` e
// blocos `run:` — e é o mesmo formato line-based do `junit-lanes.mjs` e do
// `anchor-lint::junitModuleLanes()`. Linha de comentário é descartada de propósito: menção
// a um comando dentro de `#` não é invocação (a mesma regra que o anchor-lint aplica).

/** Fronteiras dos jobs de um workflow: `[{ nome, linhas: string[] }]`. */
export function jobsDoWorkflow(src) {
  const linhas = String(src).split(/\r?\n/);
  const jobs = [];
  let emJobs = false;
  for (let i = 0; i < linhas.length; i++) {
    const l = linhas[i];
    if (/^jobs:\s*$/.test(l)) { emJobs = true; continue; }
    if (!emJobs) continue;
    // chave de topo (coluna 0, não-comentário) encerra o bloco `jobs:`
    if (/^[A-Za-z_"']/.test(l)) { emJobs = false; continue; }
    const m = /^ {2}([A-Za-z0-9_-]+):\s*$/.exec(l);
    if (m) { jobs.push({ nome: m[1], inicio: i + 1, linhas: [] }); continue; }
    if (jobs.length) jobs[jobs.length - 1].linhas.push(l);
  }
  return jobs;
}

/** `strategy.matrix` de um job: `{ chave: [valores] }`. */
export function matrizDoJob(linhas) {
  const out = {};
  let emMatrix = false;
  let indentMatrix = 0;
  let chave = null;
  for (const l of linhas) {
    if (/^\s*#/.test(l)) continue;
    const mM = /^(\s*)matrix:\s*$/.exec(l);
    if (mM) { emMatrix = true; indentMatrix = mM[1].length; chave = null; continue; }
    if (!emMatrix) continue;
    if (l.trim() && /^\s*/.exec(l)[0].length <= indentMatrix) { emMatrix = false; continue; }
    const mK = /^\s*([A-Za-z0-9_-]+):\s*$/.exec(l);
    if (mK) { chave = mK[1]; out[chave] = []; continue; }
    const mI = /^\s*-\s*(.+?)\s*$/.exec(l);
    if (mI && chave) out[chave].push(mI[1].replace(/^["']|["']$/g, ''));
  }
  return out;
}

/** Blocos `run:` de um job (escalar literal `|` ou inline), como strings. */
export function runsDoJob(linhas) {
  const out = [];
  for (let i = 0; i < linhas.length; i++) {
    const l = linhas[i];
    if (/^\s*#/.test(l)) continue;
    const m = /^(\s*)(?:-\s+)?run:\s*(\|[-+]?|>[-+]?)?\s*(.*)$/.exec(l);
    if (!m) continue;
    const indent = m[1].length + (/^\s*-\s+run:/.test(l) ? 2 : 0);
    if (!m[2]) { out.push(m[3]); continue; } // inline
    const corpo = [];
    for (let j = i + 1; j < linhas.length; j++) {
      const b = linhas[j];
      if (b.trim() === '') { corpo.push(''); continue; }
      if (/^\s*/.exec(b)[0].length <= indent) break;
      corpo.push(b);
      i = j;
    }
    out.push(corpo.join('\n'));
  }
  return out;
}

// Forma (d) do run-set: `find <dir...> -name '*Test.php'`. FONTE ÚNICA porque o padrão é
// consultado em DOIS lugares (o guard do `${VAR[@]}` e a coleta dos dirs) e a 1ª versão
// tinha duas cópias que já divergiam — a do guard só aceitava UM diretório, então o
// estoque-pest (que passa dois) caía em `indeterminado` mesmo com o find bem ali.
// É `() =>` porque regex /g é stateful: instância compartilhada com matchAll é footgun.
const FIND_TESTES = () => /\bfind\s+((?:[\w./-]+\s+)+)-name\s+'?\*Test\.php'?/g;

/** Um token do shell é alvo de teste? (path de arquivo/pasta, não flag nem env) */
function pareceAlvo(tok) {
  if (!tok || tok.startsWith('-')) return false;
  if (/^[A-Z_]+=/.test(tok)) return false;
  return /^(tests|Modules|app|database|scripts)\//.test(tok);
}

/**
 * Alvos de UM bloco `run:` que invoca o Pest.
 *
 * Devolve `{ alvos, listas, finds, indeterminado }`. `indeterminado` é uma STRING com o
 * motivo quando a invocação usa uma forma que este parser não sabe resolver — e nesse caso
 * o chamador NÃO pode tratar a lane como "cobre nada".
 */
export function alvosDoRun(run) {
  const texto = String(run || '');
  if (!/vendor\/bin\/pest|artisan test/.test(texto)) return null;

  // 1º junta continuações `\` e 2º COLAPSA os espaços DENTRO de `${{ ... }}` — sem isso
  // `Modules/${{ matrix.module }}/Tests` vira TRÊS tokens no split e a expansão de matriz
  // nunca acontece (medido: modules-pest derivava 1 alvo em vez dos 6 da matriz).
  const juntado = texto
    .replace(/\\\r?\n\s*/g, ' ')
    .replace(/\$\{\{\s*([\w.]+)\s*\}\}/g, '${{$1}}');
  const linhas = juntado.split('\n').filter((l) => /vendor\/bin\/pest|artisan test/.test(l));

  const alvos = new Set();
  const listas = new Set();
  const finds = new Set();
  let indeterminado = null;

  let ignorada = null;
  for (const l of linhas) {
    // `--mutate` (mutation-gate) mede QUALIDADE de assert, não cobertura de arquivo. Sai
    // como `ignorada`, NUNCA como `indeterminada`: misturar decisão deliberada com limite
    // do parser polui o alarme e ensina o leitor a ignorá-lo.
    if (/--mutate/.test(l)) { ignorada = 'mutation testing (--mutate) não é lane de cobertura'; continue; }
    const toks = l.trim().split(/\s+/);
    let viuBinario = false;
    for (const tok of toks) {
      if (/vendor\/bin\/pest|artisan$/.test(tok) || tok === 'test') { viuBinario = true; continue; }
      if (!viuBinario) continue;
      if (pareceAlvo(tok)) { alvos.add(tok.replace(/^["']|["']$/g, '')); continue; }
      // expansão de array bash: `"${PEST_TARGETS[@]}"` / `"${TARGETS[@]}"`
      const arr = /^"?\$\{([A-Z_]+)\[@\]\}"?$/.exec(tok);
      if (arr) {
        const lista = /<\s*\(\s*[^)]*?(\.github\/[\w.-]+\.list)/.exec(texto)
          || /mapfile\s+-t\s+\w+\s*<\s*[^\n]*?(\.github\/[\w.-]+\.list)/.exec(texto);
        if (lista) { listas.add(lista[1]); continue; }
        // o run-set veio de um pipeline shell (find/comm) — tratado abaixo pelos `finds`
        if (FIND_TESTES().test(texto)) continue;
        indeterminado = `array de shell \`${arr[1]}\` sem lista .list nem find reconhecível`;
      }
    }
  }

  // forma (d): `find <dir...> -name '*Test.php'` (+ quarentena, resolvida pelo chamador).
  // N diretórios antes do `-name` — o estoque-pest passa DOIS (`tests/Feature/Estoque
  // tests/Feature/Produto`); capturar só o primeiro perderia metade do run-set da lane.
  for (const m of texto.matchAll(FIND_TESTES())) {
    for (const d of m[1].trim().split(/\s+/)) finds.add(d);
  }
  // extras nomeados à mão no shell (`echo 'tests/...' >> /tmp/run.txt`)
  for (const m of texto.matchAll(/echo\s+'((?:tests|Modules)\/[^']+\.php)'/g)) alvos.add(m[1]);

  if (!alvos.size && !listas.size && !finds.size && !indeterminado && !ignorada) {
    indeterminado = 'invoca o Pest mas nenhum alvo foi reconhecido';
  }
  return { alvos: [...alvos], listas: [...listas], finds: [...finds], indeterminado, ignorada };
}

/**
 * Paths em quarentena citados por um bloco `run:` (o lado SUBTRATIVO — §5 2026-08-12).
 *
 * DOIS detectores, e a ordem importa. O primeiro é a atribuição `QUAR_FILE=<path>`: é a
 * variável que o workflow de fato USA pra montar a exclusão (`comm -23 all quar`), então
 * é o mecanismo, não o rótulo. O segundo — nome de arquivo contendo "quarantine" — é
 * critério SINTÁTICO, da família que o §5 já matou 4 vezes; fica só como rede pra lane que
 * cite a lista sem passar pela variável, nunca como detector principal.
 */
export function quarentenaDoRun(run, lerArquivo) {
  const fora = new Set();
  const listas = new Set();
  for (const m of String(run || '').matchAll(/\bQUAR_FILE=([\w./-]+)/g)) listas.add(m[1]);
  for (const m of String(run || '').matchAll(/([\w./-]*\.github\/[\w.-]*quarantine[\w.-]*\.list)/g)) listas.add(m[1]);
  for (const lista of listas) {
    const conteudo = lerArquivo(lista);
    if (conteudo == null) continue;
    for (const linha of conteudo.split('\n')) {
      const p = linha.replace(/#.*/, '').trim();
      if (p) fora.add(p);
    }
  }
  return [...fora];
}

function arquivosDeTeste(dir) {
  const out = [];
  const walk = (d) => {
    if (!existsSync(abs(d))) return;
    for (const e of readdirSync(abs(d), { withFileTypes: true })) {
      const p = `${d}/${e.name}`;
      if (e.isDirectory()) walk(p);
      else if (e.name.endsWith('Test.php')) out.push(p);
    }
  };
  walk(dir.replace(/\/$/, ''));
  return out;
}

/** Cobertura de CI derivada: `{ cobertos:Set<path>, lanes:[], indeterminadas:[] }`. */
export function derivaCobertura(lerArquivo, listarWorkflows) {
  const cobertos = new Set();
  const lanes = [];
  const indeterminadas = [];

  for (const wf of listarWorkflows()) {
    const src = lerArquivo(wf);
    if (src == null) continue;

    for (const job of jobsDoWorkflow(src)) {
      const nomeJob = job.nome;
      const matriz = matrizDoJob(job.linhas);
      for (const run of runsDoJob(job.linhas)) {
        const r = alvosDoRun(run);
        if (!r) continue;

        if (r.ignorada && !r.alvos.length && !r.listas.length && !r.finds.length) continue;
        if (r.indeterminado) {
          indeterminadas.push({ lane: `${wf}:${nomeJob}`, motivo: r.indeterminado });
          continue;
        }

        const brutos = [...r.alvos];
        for (const l of r.listas) {
          const c = lerArquivo(l);
          if (c == null) { indeterminadas.push({ lane: `${wf}:${nomeJob}`, motivo: `lista ${l} ausente` }); continue; }
          for (const ln of c.split('\n')) {
            const p = ln.replace(/#.*/, '').trim();
            if (p) brutos.push(p);
          }
        }
        for (const d of r.finds) brutos.push(...arquivosDeTeste(d));

        // expande `${{ matrix.X }}` — sem isso o modules-pest cobriria zero
        const expandidos = [];
        for (const alvo of brutos) {
          const mm = /\$\{\{\s*matrix\.(\w+)\s*\}\}/.exec(alvo);
          if (!mm) { expandidos.push(alvo); continue; }
          const valores = matriz[mm[1]];
          if (!Array.isArray(valores)) { indeterminadas.push({ lane: `${wf}:${nomeJob}`, motivo: `matrix.${mm[1]} não é lista` }); continue; }
          for (const v of valores) expandidos.push(alvo.replace(mm[0], String(v)));
        }

        const fora = new Set(quarentenaDoRun(run, lerArquivo));
        const finais = expandidos.filter((p) => !fora.has(p));
        for (const p of finais) cobertos.add(p.replace(/\/$/, ''));
        lanes.push({ lane: `${wf}:${nomeJob}`, alvos: finais.length, quarentena: fora.size });
      }
    }
  }
  return { cobertos, lanes, indeterminadas };
}

/** Um arquivo de teste é coberto? (alvo exato OU pasta ancestral entre os alvos) */
export function cobertoPor(arquivo, cobertos) {
  if (cobertos.has(arquivo)) return true;
  const partes = arquivo.split('/');
  for (let i = partes.length - 1; i > 0; i--) {
    if (cobertos.has(partes.slice(0, i).join('/'))) return true;
  }
  return false;
}

// ═════════════════════════════════════════════════════════════════════════════════════
// 2. UC → TESTE CITADO (tabela de Rastreabilidade do casos.md)
// ═════════════════════════════════════════════════════════════════════════════════════

/**
 * Linhas `| UC-XX | … | <Teste> | <Status> |` de um casos.md.
 * As duas últimas colunas são Teste e Status por contrato do template.
 */
export function citacoesEm(content) {
  const out = [];
  const declarados = new Set();
  for (const { uc } of ucBlocksInCasos(content)) declarados.add(uc);

  for (const linha of String(content).split('\n')) {
    if (!linha.trim().startsWith('|')) continue;
    const cols = linha.split('|').map((c) => c.trim());
    if (cols.length < 5) continue;
    const uc = (/\b(UC-[A-Z0-9-]+)\b/.exec(cols[1] || '') || [])[1];
    if (!uc) continue;
    const status = cols[cols.length - 2];
    const teste = cols[cols.length - 3];
    if (/^-+$/.test(status)) continue;                       // linha separadora
    const nomes = [...String(teste).matchAll(/`?\b(\w*Test)\b`?/g)].map((m) => m[1]);
    out.push({ uc, teste: teste.replace(/`/g, '').trim(), nomes, status, declarado: declarados.has(uc) });
  }
  return out;
}

// ═════════════════════════════════════════════════════════════════════════════════════
// 3. MAIN
// ═════════════════════════════════════════════════════════════════════════════════════

const lerArquivo = (rel) => { try { return readFileSync(abs(rel), 'utf8'); } catch { return null; } };
const listarWorkflows = () => {
  const d = abs('.github/workflows');
  if (!existsSync(d)) return [];
  return readdirSync(d).filter((f) => /\.ya?ml$/.test(f)).map((f) => `.github/workflows/${f}`).sort();
};

function indiceDeTestes() {
  // git ls-files: o universo é o VERSIONADO (não uma travessia que pega worktree alheio).
  const raw = execFileSync('git', ['ls-files', '--', '*Test.php'], { cwd: ROOT, encoding: 'utf8' });
  const porNome = new Map();
  for (const p of raw.split('\n').map((s) => s.trim()).filter(Boolean)) {
    const nome = p.split('/').pop().replace(/\.php$/, '');
    if (!porNome.has(nome)) porNome.set(nome, []);
    porNome.get(nome).push(p);
  }
  return porNome;
}

function manifestoG7() {
  const c = lerArquivo('scripts/casos-test-results.json');
  if (!c) return {};
  try { return JSON.parse(c).ucs || {}; } catch { return {}; }
}

function carregaBaseline(p) {
  if (!p) return null;
  if (!existsSync(abs(p))) { console.error(`uc-lane-coverage: --baseline ${p} não existe.`); process.exit(2); }
  try { return new Set(JSON.parse(readFileSync(abs(p), 'utf8')).grandfathered || []); }
  catch (e) { console.error(`uc-lane-coverage: --baseline ${p} não parseia (${e.message}).`); process.exit(2); }
}

function main() {
  const { cobertos, lanes, indeterminadas } = derivaCobertura(lerArquivo, listarWorkflows);

  if (LANES_ONLY) {
    console.log(`\n  run-set derivado — ${lanes.length} invocação(ões) de Pest, ${cobertos.size} alvo(s)\n`);
    for (const l of lanes.sort((a, b) => a.lane.localeCompare(b.lane))) {
      console.log(`   ${String(l.alvos).padStart(4)} alvo(s)  ${l.lane}${l.quarentena ? `   (−${l.quarentena} em quarentena)` : ''}`);
    }
    if (indeterminadas.length) {
      console.log(`\n  ⚠️  ${indeterminadas.length} invocação(ões) INDETERMINADA(S) — não viram "não cobre":`);
      for (const i of indeterminadas) console.log(`      ${i.lane} — ${i.motivo}`);
    }
    return 0;
  }

  const testesPorNome = indiceDeTestes();
  const manifesto = manifestoG7();
  const arquivosCasos = execFileSync('git', ['ls-files', '--', '*.casos.md'], { cwd: ROOT, encoding: 'utf8' })
    .split('\n').map((s) => s.trim()).filter(Boolean).sort();

  const linhas = [];
  for (const f of arquivosCasos) {
    for (const c of citacoesEm(readFileSync(abs(f), 'utf8'))) {
      if (!c.nomes.length) {
        linhas.push({ ...c, arquivo: posix(f), estado: c.teste ? 'sem-nome-de-teste' : 'sem-teste', paths: [] });
        continue;
      }
      const paths = c.nomes.flatMap((n) => testesPorNome.get(n) || []);
      const ausentes = c.nomes.filter((n) => !testesPorNome.has(n));
      let estado;
      if (ausentes.length) estado = 'arquivo-ausente';
      else if (paths.some((p) => cobertoPor(p, cobertos))) estado = 'na-lane';
      else estado = 'orfao';
      linhas.push({ ...c, arquivo: posix(f), estado, paths, ausentes, verdict: manifesto[c.uc]?.verdict || null });
    }
  }

  const orfaos = linhas.filter((l) => l.estado === 'orfao' || l.estado === 'arquivo-ausente');
  const chave = (l) => `${l.arquivo}::${l.uc}`;

  if (WRITE_BASELINE) {
    writeFileSync(abs(WRITE_BASELINE), `${JSON.stringify({
      _meta: {
        schema: 'uc-lane-baseline/v1',
        purpose: 'Grandfathera UC cujo teste citado já era órfão de lane. `--check --baseline` morde só citação NOVA (no-new-lie).',
        contrato: 'grandfathered[] = "<casos.md>::<UC>". CRESCER = grandfatherar órfão novo, o oposto do propósito. DIMINUIR (teste entrou na lane) é livre.',
        regenerar: `node scripts/qa/uc-lane-coverage.mjs --write-baseline ${WRITE_BASELINE}`,
        nao_e: 'NÃO é permissão pra deixar teste fora da lane — é dívida datada. Por que a allowlist existe (custo de CI? teste instável escondido?) é decisão [W].',
      },
      grandfathered: orfaos.map(chave).sort(),
    }, null, 2)}\n`);
    console.log(`uc-lane-coverage: baseline escrito em ${WRITE_BASELINE} (${orfaos.length} UC grandfatherado).`);
    return 0;
  }

  const base = carregaBaseline(BASELINE_PATH);
  const ativos = base ? orfaos.filter((l) => !base.has(chave(l))) : orfaos;

  const resumo = {
    casos_md: arquivosCasos.length,
    citacoes: linhas.length,
    na_lane: linhas.filter((l) => l.estado === 'na-lane').length,
    orfao: linhas.filter((l) => l.estado === 'orfao').length,
    arquivo_ausente: linhas.filter((l) => l.estado === 'arquivo-ausente').length,
    sem_teste: linhas.filter((l) => l.estado === 'sem-teste' || l.estado === 'sem-nome-de-teste').length,
    lanes_derivadas: lanes.length,
    lanes_indeterminadas: indeterminadas.length,
    ativos: ativos.length,
  };

  if (JSON_OUT) {
    console.log(JSON.stringify({ _meta: { schema: 'uc-lane-coverage/v1' }, resumo, indeterminadas, violacoes: ativos }, null, 2));
    return CHECK && ativos.length ? 1 : 0;
  }

  console.log('\n  uc-lane-coverage — o teste citado pelo casos.md roda em alguma lane?\n');
  console.log(`  casos.md: ${resumo.casos_md} · citações UC→teste: ${resumo.citacoes}`);
  console.log(`  na lane: ${resumo.na_lane} · ÓRFÃO: ${resumo.orfao} · arquivo ausente: ${resumo.arquivo_ausente} · sem teste citado: ${resumo.sem_teste}`);
  console.log(`  run-set derivado de ${resumo.lanes_derivadas} invocação(ões) de Pest · ${resumo.lanes_indeterminadas} indeterminada(s)\n`);

  if (indeterminadas.length) {
    console.log('  ⚠️  lanes cuja seleção este parser NÃO derivou — NÃO contam como "não cobre":');
    for (const i of indeterminadas) console.log(`      ${i.lane} — ${i.motivo}`);
    console.log('');
  }

  if (!ativos.length) {
    console.log(`  ✅ nenhuma citação ativa aponta pra teste órfão de lane.${base ? ' (legado no baseline)' : ''}\n`);
    return 0;
  }

  let ultimo = null;
  for (const l of ativos) {
    if (l.arquivo !== ultimo) { console.log(`  ${l.arquivo.replace(/^resources\/js\/Pages\//, '')}`); ultimo = l.arquivo; }
    const marca = l.estado === 'arquivo-ausente' ? '∅ arquivo não existe' : '⛔ existe e NENHUMA lane roda';
    console.log(`     ${l.uc.padEnd(16)} ${marca}   teste: ${l.teste || '—'}   status declarado: ${l.status || '—'}`);
    for (const p of l.paths) console.log(`        ${p}`);
  }
  console.log('\n  Teste fora de toda lane é "verde impossível": existe, pode estar vermelho');
  console.log('  há meses, e nenhum PR o acorda. O UC que o cita parece coberto e não está.');
  console.log('  Conserto NÃO é mexer na allowlist por conta própria — por que ela existe');
  console.log('  (custo de CI? teste instável escondido?) é decisão [W].\n');
  return CHECK ? 1 : 0;
}

if (import.meta.url === pathToFileURL(process.argv[1]).href) process.exit(main());
