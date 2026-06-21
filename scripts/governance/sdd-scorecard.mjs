#!/usr/bin/env node
// sdd-scorecard.mjs — agregador do scorecard SDD (GT-G2, Semana 0 do plano
// memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md §2).
//
// POR QUE EXISTE: a reestruturação SDD só é "medida, testada, garantida" se houver
// UM lugar com as 10 métricas e a regra anti-stale — baseline capturado na 1ª
// medição REAL da fonte, nunca copiado do plano. Este script agrega o que JÁ é
// medível hoje e declara o resto `not_yet_measured` (mentir "0" seria pior).
//
// FONTES VIVAS (hoje):
//   - knowledge-drift.mjs --json  → ghost_count + front_door_coverage
//   - grep dos SPECs              → anchor_coverage ESTRITO (campo preenchido
//                                   sem placeholder E ≥1 path existente no disco)
// As outras 7 métricas nascem `not_yet_measured` com a fonte futura declarada.
//
// DETERMINÍSTICO: zero timestamps/sha no arquivo — re-run sem mudança = diff vazio.
// Uso (na raiz do repo):
//   node scripts/governance/sdd-scorecard.mjs            # mede + escreve governance/sdd-scorecard.json
//   node scripts/governance/sdd-scorecard.mjs --json     # mede + imprime no stdout (não escreve)
//   node scripts/governance/sdd-scorecard.mjs --ratchet  # compara com governance/sdd-scorecard-baseline.json (GT-G3):
//                                                        # métrica ARMADA (armed:true no baseline) que regrediu = exit 1;
//                                                        # desarmada que regrediu = warn (exit 0). ADR 0275 §3: métrica só
//                                                        # arma após 3 medições válidas consecutivas da fonte real.
//                                                        # SDD_RATCHET_ARM=1 trata todas como armadas (simulação/selftest).
// Node puro (fs + execSync). Sem deps, sem DB, sem PHP. Idioma: clone de knowledge-drift.mjs.

import { readdirSync, readFileSync, existsSync, writeFileSync, realpathSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = process.cwd();
const OUT = join(ROOT, 'governance', 'sdd-scorecard.json');
const BASELINE = join(ROOT, 'governance', 'sdd-scorecard-baseline.json');
const MODE_JSON = process.argv.includes('--json');
const MODE_RATCHET = process.argv.includes('--ratchet');
const ARMED = process.env.SDD_RATCHET_ARM === '1';

// ── fonte 1: knowledge-drift --json ─────────────────────────────────────────
function measureKnowledgeDrift() {
  const raw = execSync(`"${process.execPath}" scripts/governance/knowledge-drift.mjs --json`, {
    cwd: ROOT, maxBuffer: 32 * 1024 * 1024, stdio: ['ignore', 'pipe', 'pipe'],
  }).toString();
  const rows = JSON.parse(raw);
  const names = new Set();
  let citing = 0;
  for (const r of rows) if (r.ghosts.length) { citing++; for (const g of r.ghosts) names.add(g); }
  const withDoor = rows.filter((r) => r.door !== 'NÃO').length;
  return { ghost_count: names.size, ghost_citing_modules: citing, door_num: withDoor, door_den: rows.length };
}

// ── fonte 2: anchor-lint --json (FONTE ÚNICA do anchor_coverage — ADR 0273 §2) ─
// Antes media com grep estrito próprio (PLACEHOLDER_RE/ANCHOR_PATH_RE/US_HEADING_RE/
// FIELD_RE) e divergia do anchor-lint: dois números pro mesmo conceito. Agora delega —
// `summary.anchor_coverage_pct` do anchor-lint é a fonte única; este script só transporta.
function measureAnchors() {
  const raw = execSync(`"${process.execPath}" scripts/governance/anchor-lint.mjs --json`, {
    cwd: ROOT, maxBuffer: 32 * 1024 * 1024, stdio: ['ignore', 'pipe', 'pipe'],
  }).toString();
  const s = JSON.parse(raw).summary;
  return { coverage_pct: s.anchor_coverage_pct, ...s };
}

// ── fonte 3: quarentena FV-Q3 (convenção legacy-quarantine nos testes) ───────
// Conta ARQUIVOS de teste quarentenados (n_quarantine só DESCE no burn-down). A convenção
// aparece em 3 formas — @group legacy-quarantine (docblock), ->group('legacy-quarantine')
// (Pest fluent, granular por teste) e skip('legacy-quarantine: ...') — todas cravam o MESMO
// token. Casar só @group subcontava (14 de 27 reais). Determinístico: contagem por arquivo,
// independe da ordem do readdir. Escopo = tests/ + Modules/*/Tests (onde vive o marcador).
const QUARANTINE_RE = /legacy-quarantine/;
function measureQuarantine() {
  let files = 0;
  const walk = (dir) => {
    if (!existsSync(dir)) return;
    for (const e of readdirSync(dir, { withFileTypes: true })) {
      const p = join(dir, e.name);
      if (e.isDirectory()) walk(p);
      else if (e.name.endsWith('.php') && QUARANTINE_RE.test(readFileSync(p, 'utf8'))) files++;
    }
  };
  walk(join(ROOT, 'tests'));
  const mods = join(ROOT, 'Modules');
  if (existsSync(mods)) for (const e of readdirSync(mods, { withFileTypes: true }))
    if (e.isDirectory()) walk(join(mods, e.name, 'Tests'));
  return { files };
}

// ── montagem do scorecard (ordem fixa = output determinístico) ──────────────
function pct(num, den) { return den ? Math.round((1000 * num) / den) / 10 : null; }
const notYet = (direction, target, source) => ({
  status: 'not_yet_measured', value: null, direction, target, source,
  baseline_rule: '1ª medição real da fonte, nunca do plano (anti-stale)',
});

// ── stream de cada métrica (a "cola" que une SDD + memória num só scorecard) ──
// SA=spec-anchor · FV=full-suite/verificação · KL=knowledge/ghost · GT=garantia ·
// MEM=memória-unificada (read-path do ADR 0270) como stream de 1ª classe.
// É a peça 4 do keystone da união (ledger 2026-06-19-adversario-uniao-sdd-memoria.md):
// um SÓ scorecard governa SDD e memória — não dois sistemas paralelos.
const STREAM = {
  anchor_coverage: 'SA',
  full_suite_pass_rate: 'FV', n_quarantine: 'FV', coverage_pct: 'FV',
  ghost_count: 'KL',
  front_door_coverage: 'MEM', recall_eval_violations: 'MEM', ragas_real_uptime: 'MEM',
  distiller_freshness: 'MEM', read_path_hops: 'MEM',
  drift_alarms: 'GT', backfill_error_rate: 'GT',
};

// ── fonte: floor do nightly CT100 (ADR 0279 — transporte Opção A · US-GOV-023) ─
// Read-side do elo MEDIR→GOVERNAR. Lê governance/nightly-floor.json, que o write-side
// (PR-2) escreve no CT100 via git-push [skip ci]. O artefato carrega floor_count =
// INTERSEÇÃO dos arquivos-que-falham entre ≥2 runs (def US-GOV-018) + counts por run.
// O schema NÃO traz passed/total, então a métrica mede o FLOOR (arquivos-que-falham,
// DESCE pra 0); o nome histórico full_suite_pass_rate fica só pra casar a chave do
// baseline. Fallback not_yet_measured se ausente/inválido — ZERO-RISCO até a 1ª escrita
// do CT100 (nunca "mente 0"). Quando o write-side publicar, vira measured sozinha.
export function measureFullSuiteFloor(floorPath = join(ROOT, 'governance', 'nightly-floor.json')) {
  if (!existsSync(floorPath)) {
    return notYet('down', 0,
      'governance/nightly-floor.json ainda não publicado pelo write-side CT100 (ADR 0279 PR-2 — precisa de credencial de push no CT100). A nightly FV-F3 roda (15+ runs reais); falta o transporte.');
  }
  let f;
  try { f = JSON.parse(readFileSync(floorPath, 'utf8')); }
  catch { return notYet('down', 0, 'governance/nightly-floor.json presente mas JSON inválido — write-side corrige; fallback honesto.'); }
  if (typeof f.floor_count !== 'number') {
    return notYet('down', 0, 'governance/nightly-floor.json sem floor_count numérico (schema ADR 0279) — fallback honesto.');
  }
  return {
    status: 'measured', value: f.floor_count, unit: 'arquivos-que-falham (interseção ≥2 runs · ADR 0279)',
    direction: 'down', target: 0,
    source: 'governance/nightly-floor.json (transporte CT100→scorecard · ADR 0279 Opção A · US-GOV-023)',
    detail: {
      floor_files_hash: f.floor_files_hash ?? null,
      runs: Array.isArray(f.runs) ? f.runs : [],
      computed_at: f.computed_at ?? null,
      intersection_of: f.intersection_of ?? null,
    },
  };
}

// ── fonte: distilled_at das portas BRIEFING (ADR 0291 D-D — peça 3 do keystone) ─
// Lê o carimbo `distilled_at:` do frontmatter de cada memory/requisitos/<Mod>/BRIEFING.md
// (o distiller-módulo-verdade — PR-C — escreve esse carimbo ao reescrever a porta).
// DETERMINÍSTICO por contrato (_meta.determinismo): a "frescura" é medida contra a
// data-git do doc MAIS NOVO do módulo (fato imutável da história), NÃO contra "hoje"
// (senão o JSON commitado mudaria todo dia). Mede "o distiller ficou pra trás dos
// eventos?" = D-3 do 0270 ("destilação que para = porta envelhece"). O alarme DURO
// >7d-vs-hoje vive no jana:health-check (runtime, não-commitado — ADR 0291 D-D).
//
// Anti-stale (espelha measureFullSuiteFloor): ZERO portas carimbadas → not_yet_measured
// (honesto — distiller nunca rodou; gate Wagner/CT100). ≥1 carimbada → measured: value =
// nº de portas carimbadas atrás do doc mais novo por > staleDays. Portas SEM carimbo
// entram só no detail (cobertura pendente), NÃO como stale (rollout não-punitivo, espelha
// front_door 62%→100%). `reqDir`/`newestDocDate` injetáveis pra teste sem git/FS real.
const STALE_DAYS_DISTILLER = 7;
const DISTILLED_AT_RE = /^distilled_at:\s*["']?(\d{4}-\d{2}-\d{2})/m;

function gitDateOf(file) {
  try {
    return execSync(`git log -1 --format=%cs -- "${file}"`, { cwd: ROOT, stdio: ['ignore', 'pipe', 'ignore'] })
      .toString().trim() || null;
  } catch { return null; }
}

// Data-git (committer %cs) do doc .md mais novo do módulo, EXCETO a própria BRIEFING.
// Só é chamada pras portas carimbadas → barato no rollout (poucas portas têm carimbo).
function gitNewestModuleDocDate(modDir) {
  let newest = null;
  const briefing = join(modDir, 'BRIEFING.md');
  const walk = (d) => {
    for (const e of readdirSync(d, { withFileTypes: true })) {
      const p = join(d, e.name);
      if (e.isDirectory()) { walk(p); continue; }
      if (!e.name.endsWith('.md') || p === briefing) continue;
      const dt = gitDateOf(p);
      if (dt && (!newest || dt > newest)) newest = dt;
    }
  };
  try { walk(modDir); } catch { /* módulo sem docs legíveis — ignora */ }
  return newest;
}

// dias que `toDate` está À FRENTE de `fromDate` (ambas YYYY-MM-DD fixas → determinístico).
function daysAhead(fromDate, toDate) {
  return (Date.parse(toDate) - Date.parse(fromDate)) / 86400000;
}

export function measureDistillerFreshness(
  reqDir = join(ROOT, 'memory', 'requisitos'),
  { newestDocDate = gitNewestModuleDocDate, staleDays = STALE_DAYS_DISTILLER } = {},
) {
  const FRESH_TARGET = '< 7d atrás do doc mais novo em 100% das portas';
  if (!existsSync(reqDir)) {
    return notYet('down', FRESH_TARGET, 'ADR 0291 D-D — memory/requisitos/ ausente; nada a medir.');
  }
  let total = 0, stamped = 0, stale = 0, oldest = null;
  for (const mod of readdirSync(reqDir, { withFileTypes: true })) {
    if (!mod.isDirectory()) continue;
    const modDir = join(reqDir, mod.name);
    const briefing = join(modDir, 'BRIEFING.md');
    if (!existsSync(briefing)) continue;
    total++;
    const m = readFileSync(briefing, 'utf8').match(DISTILLED_AT_RE);
    if (!m) continue; // porta sem carimbo → cobertura pendente, não stale
    stamped++;
    const distilledAt = m[1];
    if (!oldest || distilledAt < oldest) oldest = distilledAt;
    const newest = newestDocDate(modDir);
    if (newest && daysAhead(distilledAt, newest) > staleDays) stale++;
  }
  if (stamped === 0) {
    return notYet('down', FRESH_TARGET,
      'ADR 0291 D-D — nenhuma porta tem distilled_at ainda (distiller-módulo-verdade não rodou em prod; gate Wagner/CT100). Vira measured no 1º carimbo, como o floor do 0279.');
  }
  return {
    status: 'measured', value: stale, unit: 'portas atrás dos eventos (>7d vs doc mais novo · ADR 0291 D-D)',
    direction: 'down', target: 0,
    source: 'memory/requisitos/*/BRIEFING.md frontmatter distilled_at vs data-git do doc mais novo do módulo (determinístico — ADR 0291 D-D)',
    detail: { portas: total, carimbadas: stamped, sem_carimbo: total - stamped, stale, oldest_distilled_at: oldest },
  };
}

// ── fonte GT: error_rate agregado do ledger refutador (GT-G5 · P08) ──────────
// Lê governance/sdd-verification-ledger.json (4+ entries, todas error_rate_pct:0 ·
// PRs 2750/2754/2761/2970). Espelha measureFullSuiteFloor (anti-stale honesto):
// ausente/inválido/sem entries `aprovado` → not_yet_measured (NUNCA mente 0). Senão
// measured, value = MAX(error_rate_pct) das entries aprovadas. Por que MAX e não média:
// é o número conservador — 1 lote ruim já estoura o <2% (kill-criteria do plano P08).
export function measureBackfillErrorRate(ledgerPath = join(ROOT, 'governance', 'sdd-verification-ledger.json')) {
  if (!existsSync(ledgerPath)) {
    return notYet('down', '<2%',
      'governance/sdd-verification-ledger.json ausente — protocolo refutador G5 nunca rodou; fallback honesto.');
  }
  let l;
  try { l = JSON.parse(readFileSync(ledgerPath, 'utf8')); }
  catch { return notYet('down', '<2%', 'governance/sdd-verification-ledger.json presente mas JSON inválido — fallback honesto.'); }
  const entries = Array.isArray(l.entries) ? l.entries : [];
  const aprovadas = entries.filter((e) => e.veredito === 'aprovado' && typeof e.error_rate_pct === 'number');
  if (!aprovadas.length) {
    return notYet('down', '<2%',
      'governance/sdd-verification-ledger.json sem entry `aprovado` com error_rate_pct numérico — só existe após 1º lote IA refutado; fallback honesto.');
  }
  const rates = aprovadas.map((e) => e.error_rate_pct);
  const value = Math.max(...rates);
  const last = aprovadas[aprovadas.length - 1];
  return {
    status: 'measured', value, unit: '% (MAX error_rate das entries aprovadas — conservador: 1 lote ruim estoura <2%)',
    direction: 'down', target: '<2%',
    source: 'governance/sdd-verification-ledger.json .entries[veredito=aprovado] → max(error_rate_pct) (GT-G5 · P08)',
    detail: { entries_aprovadas: aprovadas.length, max_error_rate_pct: value, last_pr: last.pr ?? null, last_lote: last.lote_id ?? null },
  };
}

// ── fonte GT: drift_alarms via protection-drift.mjs --json (GT-G4 · P08) ──────
// Executa protection-drift.mjs --json (igual measureKnowledgeDrift exec'a a fonte).
// O script depende de `gh api` (rede + GITHUB_TOKEN). Dois desfechos legítimos:
//   - sucesso (exit 0, reds=[]) OU drift detectado (exit 1 COM JSON válido no stdout):
//     measured, value = json.reds.length (alarmes DUROS). reds>0 é estado real, não erro.
//   - `gh` ausente/sem token: execSync lança SEM JSON parseável → not_yet_measured honesto
//     (NUNCA exit 1 por falha de rede — espelha o fallback de measureFullSuiteFloor).
export function measureDriftAlarms() {
  let raw = null;
  try {
    raw = execSync(`"${process.execPath}" scripts/governance/protection-drift.mjs --json`, {
      cwd: ROOT, maxBuffer: 32 * 1024 * 1024, stdio: ['ignore', 'pipe', 'pipe'],
    }).toString();
  } catch (e) {
    // exit 1 COM JSON no stdout = drift real (reds>0), não falha de rede → aproveita o JSON.
    raw = e?.stdout ? e.stdout.toString() : null;
  }
  let json = null;
  if (raw) { try { json = JSON.parse(raw); } catch { json = null; } }
  if (!json || !Array.isArray(json.reds)) {
    return notYet('down', 'advisory perene (0 alarmes duros)',
      'gh api indisponível — drift_alarms requer GITHUB_TOKEN (protection-drift.mjs lê branch protection). Fallback honesto: vira measured assim que o gh autenticar.');
  }
  return {
    status: 'measured', value: json.reds.length, unit: 'alarmes duros (reds do protection-drift · GT-G4)',
    direction: 'down', target: 'advisory perene (0 alarmes duros)',
    source: 'scripts/governance/protection-drift.mjs --json .reds.length (drift de branch protection + watchdog staleness · GT-G4 · P08)',
    detail: { reds: json.reds, warns: Array.isArray(json.warns) ? json.warns.length : null, verdict: json.verdict ?? null },
  };
}

function buildScorecard() {
  const kd = measureKnowledgeDrift();
  const an = measureAnchors();
  const q = measureQuarantine();
  const sc = {
    _meta: {
      scorecard: 'SDD — sistema spec-anchored + verificação agêntica (plano 2026-06-12 §2)',
      generator: 'scripts/governance/sdd-scorecard.mjs',
      plan: 'memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md',
      determinismo: 'sem timestamps/sha no corpo — re-run sem mudança no repo = diff vazio',
      composta: 'v1 (fontes parciais) ≠ v2 (12/12 vivas) — regimes não comparáveis; composta NÃO é calculada enquanto houver not_yet_measured',
      streams: 'cada métrica tem `stream` — SA/FV/KL/GT + MEM (memória-unificada · read-path do ADR 0270). Um só scorecard governa SDD e memória (peça 4 da união; ledger memory/sessions/2026-06-19-adversario-uniao-sdd-memoria.md)',
      anchor_coverage_regra: 'delegado a scripts/governance/anchor-lint.mjs (ADR 0273 §2 — fonte única): (anchored_ok + pendente + parcial) / us_total. Antes era grep estrito próprio (divergia); unificado no PR do ledger §A.',
      ratchet: {
        baseline: 'governance/sdd-scorecard-baseline.json — armed POR MÉTRICA (ADR 0275 §3: arma após 3 medições válidas consecutivas da fonte real; armar/desarmar/piorar = PR editando o baseline, diff visível)',
        simulacao: 'SDD_RATCHET_ARM=1 node scripts/governance/sdd-scorecard.mjs --ratchet — trata todas as medidas como armadas (selftest local)',
      },
    },
    metrics: {
      anchor_coverage: {
        status: 'measured', value: an.coverage_pct, unit: '%',
        direction: 'up', target: 100,
        source: 'scripts/governance/anchor-lint.mjs --json .summary.anchor_coverage_pct (fonte única — ADR 0273 §2)',
        detail: an,
      },
      full_suite_pass_rate: measureFullSuiteFloor(),
      n_quarantine: {
        status: 'measured', value: q.files, unit: 'arquivos de teste',
        direction: 'down', target: 0,
        source: 'convenção legacy-quarantine (@group | ->group() | skip) em tests/ + Modules/*/Tests (este script)',
        detail: { quarantined_files: q.files },
      },
      coverage_pct: notYet('up', 'só sobe (catraca C2)',
        'pcov instrumentado em CI (P0-2) — hoje coverage: none'),
      ghost_count: {
        status: 'measured', value: kd.ghost_count, unit: 'nomes distintos',
        direction: 'down', target: 0,
        source: 'scripts/governance/knowledge-drift.mjs --json (Modules/<X> citado e inexistente no disco; nomes: rodar a fonte direto)',
        detail: { citing_modules: kd.ghost_citing_modules },
      },
      front_door_coverage: {
        status: 'measured', value: pct(kd.door_num, kd.door_den), unit: '%',
        direction: 'up', target: 100,
        source: 'scripts/governance/knowledge-drift.mjs --json (BRIEFING.md presente por módulo)',
        detail: { with_door: kd.door_num, modules: kd.door_den },
      },
      recall_eval_violations: notYet('down', 0,
        'golden set recall (KL-C2) — depende do alias map das 13 colisões ADR'),
      ragas_real_uptime: notYet('up', '≥95%',
        'RAGAS canário modo REAL diário (KL-D1/D4) — hoje compara mock com mock'),
      distiller_freshness: measureDistillerFreshness(),
      read_path_hops: notYet('down', 1,
        'ADR 0270 D-5 — nº de docs abertos pra saber o estado atual de um módulo (meta 1; instrumentação pendente)'),
      drift_alarms: measureDriftAlarms(),
      backfill_error_rate: measureBackfillErrorRate(),
    },
  };
  // carimba o stream (SA/FV/KL/GT/MEM) em cada métrica — a "cola" da união num só scorecard
  for (const [k, m] of Object.entries(sc.metrics)) m.stream = STREAM[k] ?? '?';
  return sc;
}

// ── ratchet vs baseline VERSIONADO (GT-G3 meta-catraca) ─────────────────────
// Compara a medição ATUAL com governance/sdd-scorecard-baseline.json — arquivo
// commitado: piorar exige editar o baseline em diff VISÍVEL no PR (plano §2
// GARANTIDA). Armed é POR MÉTRICA no baseline (ADR 0275 §3 — só arma após 3
// medições válidas consecutivas da fonte real): armada que regrediu = exit 1;
// desarmada que regrediu = warn (reporta, não pune).
function ratchet(current) {
  if (!existsSync(BASELINE)) { console.log('  --ratchet: sem baseline em governance/sdd-scorecard-baseline.json — nada a comparar.'); return 0; }
  const base = JSON.parse(readFileSync(BASELINE, 'utf8'));
  const red = [], warn = [];
  for (const [name, m] of Object.entries(current.metrics)) {
    const b = base.metrics?.[name];
    if (!b || typeof b.value !== 'number' || m.status !== 'measured') continue;
    const worse = m.direction === 'down' ? m.value > b.value : m.value < b.value;
    if (!worse) continue;
    const msg = `${name}: baseline ${b.value} → ${m.value} (${m.direction === 'down' ? 'só pode DESCER' : 'só pode SUBIR'})`;
    if (b.armed === true || ARMED) red.push(msg); else warn.push(msg);
  }
  if (!red.length && !warn.length) { console.log('  --ratchet: nenhuma regressão vs governance/sdd-scorecard-baseline.json. ✓'); return 0; }
  for (const v of warn) console.log(`  ⚠️ RATCHET (desarmada — reporta, não pune · ADR 0275 §3): ${v}`);
  for (const v of red) console.log(`  🔴 RATCHET (ARMADA): ${v}`);
  if (!red.length) return 0;
  console.log('  Piora intencional? Edite governance/sdd-scorecard-baseline.json no MESMO PR (diff visível) citando ADR 0275.');
  return 1;
}

// ── main (só quando executado direto; importável p/ teste sem rodar) ────────
const isMain = (() => {
  try { return realpathSync(process.argv[1]) === fileURLToPath(import.meta.url); }
  catch { return false; }
})();

if (isMain) {
  const scorecard = buildScorecard();
  const body = JSON.stringify(scorecard, null, 2) + '\n';

  if (MODE_JSON) { process.stdout.write(body); process.exit(0); }
  if (MODE_RATCHET) process.exit(ratchet(scorecard));

  writeFileSync(OUT, body, 'utf8');
  const measured = Object.entries(scorecard.metrics).filter(([, m]) => m.status === 'measured');
  const pending = Object.keys(scorecard.metrics).length - measured.length;
  console.log(`\n  SDD SCORECARD → governance/sdd-scorecard.json\n`);
  for (const [name, m] of measured) console.log(`  ✓ ${name.padEnd(22)} ${String(m.value).padStart(6)} ${m.unit}  (meta: ${m.target}, ${m.direction === 'up' ? 'sobe' : 'desce'})`);
  console.log(`  … ${pending} métricas not_yet_measured (fonte futura declarada no JSON — baseline na 1ª medição real)\n`);
}
