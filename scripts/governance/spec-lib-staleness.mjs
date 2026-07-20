#!/usr/bin/env node
// @ts-check
/**
 * spec-lib-staleness.mjs — sentinela: o DOC que descreve uma lib externa ficou
 * atrás da VERSÃO que o composer.lock realmente trava? (régua Tessl / Spec Registry)
 *
 * O 6º EIXO de staleness — e o único que olha pra FORA (contratos com libs de
 * terceiros). Os irmãos vigiam artefatos internos; nada media doc×versão-de-lib:
 *   · BRIEFING.md                 × Modules/<X> ∪ Pages/<X>  → briefing-code-staleness.mjs
 *   · <tela>-visual-comparison.md × inertia_target .tsx      → visual-comparison-staleness.mjs
 *   · ADR proposta parada                                    → adr-proposto-parado.mjs
 *   · RADAR de frescor por doc (0-100)                       → doc-freshness-score.mjs
 *   · AGENTS.md                   × CLAUDE.md ∪ @imports     → agents-md-staleness.mjs
 *   · DOC de lib                  × versão no composer.lock  → ESTE
 *
 * ── O BURACO (fraqueza spec-registry-libs, grade de réguas 2026-07-18, nota 5,0) ──
 *   O contrato com libs externas vive só nos pins do composer.json + doutrina
 *   módulo-referência (ADR 0011) + RUNBOOKs de API externa. Quando uma lib SOBE DE
 *   VERSÃO, NADA acusa que o doc/RUNBOOK que descreve a superfície dela ficou stale.
 *   A régua de mercado é o Tessl (Spec Registry): specs de libs versionadas que o
 *   sistema sabe quando divergem do pin real. Aqui, o mínimo honesto: um sinal de
 *   TEMPO — a versão travada mudou depois que o doc foi refrescado pela última vez?
 *
 * ── O QUE ISTO NÃO É (proibicoes.md §5 — não re-propor padrão morto) ──────────────
 *   · NÃO é motor novo de staleness (§5 2026-07-09 "não nascer 3º motor"): importa
 *     `classifyCodeStaleness` + `declaredDoorDate` do briefing-code-staleness. Segue
 *     EXATAMENTE o precedente do agents-md-staleness.mjs (6º eixo = 5º + 1): mesma
 *     derivada temporal, alvo diferente (versão de lib no lugar de CLAUDE.md), arquivo
 *     próprio, wireado no MESMO workflow agregador. O "engine" é a função pura reusada.
 *   · NÃO é presence-gate. Foi DESCARTADO exigir que o doc CITE a versão exata por
 *     grep ("presence-gate sobre TEXTO", família last_validated/§-vazio rejeitada
 *     2026-07-09): sairia verde com a versão ERRADA escrita. Medimos a DERIVADA
 *     (versão mudou depois do último refresh do doc), que É o formato do drift real.
 *   · NÃO é required — ADR 0314 (required = só Tier 0: dinheiro/PII/multi-tenant/
 *     fiscal). Frescor de doc-de-lib é HIGIENE → reporter, exit 0 sempre. Promover =
 *     emenda ADR + mordida provada (ADR 0336), nunca no calado.
 *
 * ── SINAL (determinístico, sem LLM, sem deps) ─────────────────────────────────────
 *   libChangeDate = data-git (committer %cs) do commit onde a versão TRAVADA da lib no
 *                   composer.lock fez uma TRANSIÇÃO REAL (valor ≠ do commit anterior
 *                   parseável). Só transição visível no git conta — lib estável (ou
 *                   cuja introdução é anterior ao horizonte do squash) → sem transição
 *                   → NÃO avaliada (nada pra estar atrás; evita falso-positivo).
 *   docDate       = data DECLARADA do doc (updated_at/**Atualizado:** etc; fallback
 *                   data-git) — MESMO idioma de door dos irmãos (declaredDoorDate).
 *   stale ⇔ (libChangeDate − docDate) > N dias (default 30) — o doc foi refrescado
 *           pela última vez ANTES do bump, e o bump já passou da folga.
 *
 *   Por que ignorar commits que dão version=null (não-parseável): o squash de
 *   2026-06-08 deixou locks intermediários quebrados (restore de codebase +
 *   MapaTelas gerado — os mesmos toques MECÂNICOS catalogados no agents-md-staleness).
 *   Trata-los como "versão sumiu" fabricaria uma transição falsa None↔X. `series` os
 *   pula: só compara valores concretos adjacentes (mesma assimetria "git mecânico
 *   mente" das lápides §5 2026-07-16/17).
 *
 * ── HONESTIDADE (o que este sentinela NÃO garante) ────────────────────────────────
 *   G1 mede TEMPO, não VERDADE: doc fresco e com a versão errada escrita sai verde.
 *      Compra prazo (acusa a janela em que a defasagem pode viver), não corretude.
 *   G2 docDate declarado é auto-escrito — mas a assimetria protege: esquecer de bumpar
 *      deixa o doc VELHO (morde). Só engana quem bumpar sem refrescar = teatro do
 *      last_validated → por isso é reporter, NUNCA catraca (§5 2026-07-09).
 *   G3 HORIZONTE do git: transições anteriores ao squash (2026-06-08) são invisíveis.
 *      laravel/ai, laravel/mcp e spatie/laravel-html aparecem "estáveis" porque o lock
 *      limpo mais antigo (05-28) já traz a versão atual — a introdução real (ex: ADR
 *      0035) foi apagada pelo squash. Só flagramos transição que o git PROVA.
 *
 * USO:
 *   node scripts/governance/spec-lib-staleness.mjs            (tabela; exit 0 — reporter)
 *   node scripts/governance/spec-lib-staleness.mjs --json     (JSON pro Daily Brief)
 *   node scripts/governance/spec-lib-staleness.mjs --strict   (exit 1 se stale — opt-in local)
 *   node scripts/governance/spec-lib-staleness.mjs --selftest (bite/release hermético — CI)
 *   OIMPRESSO_SPEC_LIB_STALE_DAYS=21 node …                   (limiar tunável)
 *
 * Refs: briefing-code-staleness.mjs (núcleo reusado) · agents-md-staleness.mjs
 *       (precedente do 6º-eixo-reusa-núcleo) · Tessl Spec Registry (régua) ·
 *       fraqueza spec-registry-libs (grade 2026-07-18) · ADR 0314 · proibicoes §5.
 */
import { execSync } from 'node:child_process';
import { existsSync, readFileSync, realpathSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { classifyCodeStaleness, declaredDoorDate } from './briefing-code-staleness.mjs';

const ROOT = process.cwd();
// 30d — MESMO limiar dos eixos irmãos (não introduzir número mágico novo).
const DEFAULT_STALE_DAYS = Number(process.env.OIMPRESSO_SPEC_LIB_STALE_DAYS) || 30;
const LOCK = 'composer.lock';

/**
 * MAPA CURADO lib → docs VIVOS que descrevem a superfície dela (config-as-code,
 * revisado em PR). NÃO é "todo doc que menciona a lib" (isso seria 90 arquivos de
 * ruído — sessions, ADRs append-only, handoffs). Só a porta VIVA cujo dever é
 * refletir o pin/superfície atual. ADR NÃO entra: é registro append-only, não se
 * mantém "fresco". Verificado 2026-07-19 lendo cada doc:
 *   · what-oimpresso.md            — declara os 4 pins na "Stack canônica REAL"
 *   · AGENTS.md                    — declara stack IA + nWidart ^10 (porta não-Anthropic)
 *   · project-form-shim-migration  — descreve o shim Form:: que delega pro spatie/html
 */
export const LIB_DOC_MAP = {
  'laravel/ai': ['memory/what-oimpresso.md', 'AGENTS.md'],
  'laravel/mcp': ['memory/what-oimpresso.md'],
  'spatie/laravel-html': ['memory/what-oimpresso.md', 'memory/reference/project-form-shim-migration.md'],
  'nwidart/laravel-modules': ['memory/what-oimpresso.md', 'AGENTS.md'],
};

// ── núcleo PURO (o --selftest exercita ISTO — sem git, sem FS) ────────────────

/**
 * lastVersionTransition — dada a série de versões da lib ao longo do histórico do
 * composer.lock (NEWEST→OLDEST, `null` = commit com lock não-parseável/ausente),
 * acha a TRANSIÇÃO REAL mais recente (valor concreto ≠ do valor concreto anterior).
 * NÚCLEO PURO e testável — é o que separa "a versão mudou" de ruído mecânico.
 *
 * @param {Array<{date:string, version:(string|null)}>} series  newest→oldest
 * @returns {{ current:(string|null), changedAt:(string|null), from:(string|null) }}
 *   current   = versão concreta mais nova (ou null se nunca houve valor concreto).
 *   changedAt = data-git da transição mais recente (o commit que trouxe `current`
 *               a partir de um valor DIFERENTE); null se estável / ≤1 valor visível.
 *   from      = versão imediatamente anterior à transição (contexto do bump).
 */
export function lastVersionTransition(series) {
  // só valores concretos, preservando a ordem newest→oldest (pula mecânico None).
  const concrete = (series || []).filter((s) => s && s.version != null);
  const current = concrete.length ? concrete[0].version : null;
  for (let i = 0; i < concrete.length - 1; i++) {
    if (concrete[i].version !== concrete[i + 1].version) {
      return { current, changedAt: concrete[i].date, from: concrete[i + 1].version };
    }
  }
  return { current, changedAt: null, from: null };
}

/**
 * classifyLibDoc — junta a transição da lib com a data do doc via o núcleo REUSADO.
 * @param {{ docExists:boolean, docDate:(string|null), changedAt:(string|null), staleDays?:number }} p
 * @returns {{ evaluated:boolean, stale:boolean, gapDays:(number|null) }}
 */
export function classifyLibDoc({ docExists, docDate, changedAt, staleDays = DEFAULT_STALE_DAYS }) {
  // moduleCodeExists ← "há transição visível pra estar atrás". Lib estável (changedAt
  // null) → não avaliada (não stale). Doc ausente → não avaliado (cobertura ≠ frescor).
  return classifyCodeStaleness({
    hasDoor: docExists, moduleCodeExists: changedAt != null,
    doorDate: docDate, codeDate: changedAt, staleDays,
  });
}

// ── camada git/FS (impura — só no run real, nunca no self-test) ──────────────
function gitOut(cmd) {
  try {
    return execSync(cmd, { cwd: ROOT, stdio: ['ignore', 'pipe', 'ignore'], maxBuffer: 256 * 1024 * 1024 }).toString();
  } catch { return ''; }
}
const gitDate = (rel) => gitOut(`git log -1 --format=%cs -- "${rel}"`).trim() || null;

/**
 * lockVersionHistory — reconstrói, por commit do composer.lock (newest→oldest), a
 * versão travada de cada lib curada. UMA passada: N `git show <sha>:composer.lock`.
 * @param {string[]} libs
 * @returns {Map<string, Array<{date:string, version:(string|null)}>>}
 */
function lockVersionHistory(libs) {
  const byLib = new Map(libs.map((l) => [l, []]));
  // `%H %cs` (espaço, NÃO pipe): execSync roda via shell (cmd.exe no Windows,
  // /bin/sh no CI) e um `|` no format viraria pipe de shell, quebrando o comando —
  // a mesma família de pegadinha shell catalogada em proibicoes.md (MSYS/cmd).
  const raw = gitOut(`git log --format="%H %cs" -- ${LOCK}`).trim();
  if (!raw) return byLib;
  for (const line of raw.split('\n')) {
    const sp = line.indexOf(' ');
    const sha = line.slice(0, sp), date = line.slice(sp + 1).trim();
    let vers = null;
    try {
      const lock = JSON.parse(gitOut(`git show ${sha}:${LOCK}`));
      vers = {};
      for (const sec of ['packages', 'packages-dev']) {
        for (const p of lock[sec] || []) if (libs.includes(p.name)) vers[p.name] = p.version;
      }
    } catch { vers = null; } // lock quebrado (squash) → null, series pula
    for (const lib of libs) byLib.get(lib).push({ date, version: vers ? (vers[lib] ?? null) : null });
  }
  return byLib;
}

/** docDate = data declarada (fallback git) — mesmo idioma de door dos irmãos. */
function resolveDocDate(docRel) {
  let content = '';
  try { content = readFileSync(join(ROOT, docRel), 'utf8'); } catch { /* ilegível */ }
  const declared = declaredDoorDate(content);
  return { docDate: declared || gitDate(docRel), docSource: declared ? 'declarado' : 'git-fallback' };
}

/** scan — monta as linhas (lib × doc) do eixo. run() decide formato/exit. */
export function scan(staleDays = DEFAULT_STALE_DAYS, map = LIB_DOC_MAP) {
  const libs = Object.keys(map);
  const history = lockVersionHistory(libs);
  const rows = [];
  for (const lib of libs) {
    const { current, changedAt, from } = lastVersionTransition(history.get(lib) || []);
    for (const doc of map[lib]) {
      const docExists = existsSync(join(ROOT, doc));
      const { docDate, docSource } = docExists ? resolveDocDate(doc) : { docDate: null, docSource: null };
      const { evaluated, stale, gapDays } = classifyLibDoc({ docExists, docDate, changedAt, staleDays });
      rows.push({ lib, doc, docExists, docDate, docSource, current, changedAt, from, evaluated, stale, gapDays });
    }
  }
  return rows;
}

// ── self-test (hermético — núcleo puro, zero git/FS) ─────────────────────────
function selftest() {
  let fails = 0;
  const check = (n, c) => { console.log(`${c ? '[OK]  ' : '[FAIL]'} ${n}`); if (!c) fails++; };

  // lastVersionTransition — o ruído mecânico (None do squash) NÃO fabrica transição.
  const nwidart = lastVersionTransition([
    { date: '2026-07-03', version: '10.0.6' },
    { date: '2026-06-08', version: null },       // squash lock quebrado
    { date: '2026-05-28', version: '10.0.6' },
    { date: '2026-04-23', version: 'v9.0.6' },   // transição REAL v9→v10
  ]);
  check(`lastVersionTransition: acha v9.0.6→10.0.6 em 05-28, ignora None do squash (changedAt=${nwidart.changedAt})`,
    nwidart.current === '10.0.6' && nwidart.changedAt === '2026-05-28' && nwidart.from === 'v9.0.6');

  const estavel = lastVersionTransition([
    { date: '2026-07-03', version: 'v0.6.3' }, { date: '2026-06-08', version: null }, { date: '2026-05-28', version: 'v0.6.3' },
  ]);
  check(`lastVersionTransition: lib estável (só v0.6.3 visível) → sem transição (changedAt=${estavel.changedAt})`,
    estavel.current === 'v0.6.3' && estavel.changedAt === null);

  check('lastVersionTransition: série vazia → tudo null', (() => {
    const r = lastVersionTransition([]); return r.current === null && r.changedAt === null;
  })());

  // BITE — doc refrescado ANTES do bump, e o bump já passou da folga → stale.
  const bite = classifyLibDoc({ docExists: true, docDate: '2026-04-01', changedAt: '2026-05-28', staleDays: 30 });
  check(`BITE: doc 04-01 vs bump 05-28 (57d) → stale (obtido stale=${bite.stale}, gap=${bite.gapDays})`,
    bite.evaluated && bite.stale && bite.gapDays === 57);

  // RELEASE — doc refrescado DEPOIS do bump → gap negativo → fresco (o caso real hoje:
  // nwidart bumpou 05-28, what-oimpresso.md/AGENTS.md tocados em 07-14/07-17).
  const rel = classifyLibDoc({ docExists: true, docDate: '2026-07-14', changedAt: '2026-05-28', staleDays: 30 });
  check(`RELEASE: doc 07-14 vs bump 05-28 → fresco (obtido stale=${rel.stale}, gap=${rel.gapDays})`,
    rel.evaluated && !rel.stale && rel.gapDays < 0);

  // ESTÁVEL — lib sem transição visível → NÃO avaliada (sem falso-positivo, mesmo com doc velho).
  const stable = classifyLibDoc({ docExists: true, docDate: '2026-01-01', changedAt: null, staleDays: 30 });
  check(`ESTÁVEL: lib sem bump visível + doc velho → não avaliada (obtido evaluated=${stable.evaluated}, stale=${stable.stale})`,
    !stable.evaluated && !stable.stale);

  // COBERTURA — doc mapeado ausente → não avaliado (cobertura ≠ staleness).
  const nodoc = classifyLibDoc({ docExists: false, docDate: null, changedAt: '2026-05-28', staleDays: 30 });
  check(`COBERTURA: doc ausente → não avaliado (obtido evaluated=${nodoc.evaluated})`, !nodoc.evaluated && !nodoc.stale);

  // FRONTEIRA — 30d exatos não morde (>30 estrito); 31d morde.
  const at = classifyLibDoc({ docExists: true, docDate: '2026-04-28', changedAt: '2026-05-28', staleDays: 30 });
  const over = classifyLibDoc({ docExists: true, docDate: '2026-04-27', changedAt: '2026-05-28', staleDays: 30 });
  check(`FRONTEIRA: 30d não morde, 31d morde (obtido 30=${at.stale}, 31=${over.stale})`, !at.stale && over.stale);

  // MAPA curado íntegro — só libs reais, sem doc duplicado por lib, só docs .md vivos.
  const okMap = Object.entries(LIB_DOC_MAP).every(([lib, docs]) =>
    lib.includes('/') && Array.isArray(docs) && docs.length && new Set(docs).size === docs.length && docs.every((d) => d.endsWith('.md')));
  check('MAPA: 4 libs curadas, docs .md sem duplicata por lib', okMap && Object.keys(LIB_DOC_MAP).length === 4);

  console.log(fails ? `\nSELFTEST FALHOU (${fails})` : `\nSELFTEST OK — transição real detectada, None mecânico ignorado; núcleo morde (bump não refletido) e solta (doc em dia / lib estável).`);
  return fails ? 1 : 0;
}

// ── run (CLI) ────────────────────────────────────────────────────────────────
function run() {
  const JSON_OUT = process.argv.includes('--json');
  const STRICT = process.argv.includes('--strict');
  const staleDays = DEFAULT_STALE_DAYS;
  const rows = scan(staleDays);
  const stale = rows.filter((r) => r.stale).sort((a, b) => (b.gapDays ?? 0) - (a.gapDays ?? 0));

  if (JSON_OUT) {
    console.log(JSON.stringify({
      gate: 'spec-lib-staleness',
      axis: 'doc de lib (data declarada; fallback git) vs versão travada no composer.lock (data-git da transição)',
      staleDays, evaluated: rows.length,
      stale: stale.map((r) => ({ lib: r.lib, doc: r.doc, from: r.from, to: r.current, changedAt: r.changedAt, docDate: r.docDate, gapDays: r.gapDays })),
      libs: rows,
    }, null, 2));
    return stale.length && STRICT ? 1 : 0;
  }

  console.log(`\n  DOC de lib × versão no composer.lock — a porta ficou atrás do pin travado? (limiar ${staleDays}d)`);
  console.log(`  eixo: MAPA curado (${Object.keys(LIB_DOC_MAP).length} libs) × ${LOCK} data-git da transição de versão`);
  console.log('  ' + '─'.repeat(78));
  if (!stale.length) {
    console.log('  🟢 nenhum doc de lib atrás de um bump de versão além do limiar.');
  } else {
    for (const r of stale) {
      console.log(`  🟡 ${r.lib} ${r.from}→${r.current} (bump ${r.changedAt}) · ${r.doc} refrescado ${r.docDate} (${r.docSource}) · atraso ${r.gapDays}d`);
    }
  }
  console.log('  ' + '─'.repeat(78));
  const transicoes = [...new Set(rows.filter((r) => r.changedAt).map((r) => `${r.lib}@${r.changedAt}`))];
  console.log(`  ${stale.length} doc(s) stale · ${rows.length} pares (lib×doc) avaliados · ${transicoes.length} transição(ões) visível(is) no git (${transicoes.join(', ') || '— nenhuma (horizonte do squash)'})`);
  console.log('  ADVISORY (ADR 0314 — higiene, nunca required). Mede TEMPO, não verdade (G1).');
  console.log('  NÃO é presence-gate: não exige que o doc CITE a versão — grep sairia verde com a errada.');
  console.log('  Ação por doc stale: reconciliar a superfície da lib e bumpar o carimbo de data do doc.\n');

  if (process.env.GITHUB_ACTIONS === 'true') {
    for (const r of stale) {
      console.log(`::warning title=Doc de lib atrás do pin (${r.lib})::${r.doc}: a lib ${r.lib} subiu ${r.from}→${r.current} em ${r.changedAt} (composer.lock) e o doc foi refrescado por último em ${r.docDate} — ${r.gapDays} dias atrás do bump. Reconcilie a superfície descrita e bumpe o carimbo de data. Régua Tessl Spec Registry; reporter advisory (ADR 0314).`);
    }
  }
  return stale.length && STRICT ? 1 : 0;
}

// ── main (só quando executado direto; importável p/ self-test sem rodar) ──────
const isMain = (() => {
  try { return realpathSync(process.argv[1]) === fileURLToPath(import.meta.url); }
  catch { return false; }
})();

if (isMain) process.exit(process.argv.includes('--selftest') ? selftest() : run());
