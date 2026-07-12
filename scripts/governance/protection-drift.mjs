#!/usr/bin/env node
// protection-drift.mjs — drift de branch protection + watchdog de staleness (GT-G4,
// plano memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md §2
// GARANTIDA + ADR 0275 §5 calendário/demoção e §3 armamento/staleness).
//
// POR QUE EXISTE: o único buraco real do sistema de gates é a DEMOÇÃO INVISÍVEL —
// admin remove um required check em 1 clique e nenhum diff aparece em lugar nenhum.
// Este script congela a lista real em governance/required-checks-baseline.json:
//   - required que SUMIU do vivo → 🔴 exit 1 (demoção só via PR editando o baseline + ADR)
//   - required NOVO no vivo      → 🟡 aviso (entrar é permitido; sugerir PR de baseline)
//   - context MOJIBAKE no vivo   → 🔴 par missing↔extra que é double-encoding UTF-8 um
//     do outro (incidente 2026-07-02: protection re-postada inline via shell Windows →
//     "Â·"/"ConstituiÃ§Ã£o" → 10 contexts nunca satisfeitos → todo merge BLOCKED)
//   - enforcement enfraquecido   → 🔴 (everyone → non_admins = admin bypass liberado)
// + WATCHDOG: métrica `measured` do governance/sdd-scorecard-baseline.json cuja fonte
//   (workflow) está sem run verde >48h → 🔴 (canário parado é regressão silenciosa).
//
// FONTES VIVAS (públicas; funcionam com GITHUB_TOKEN contents:read — a rota admin
// /branches/main/protection NÃO é usada porque exige admin e 404a pra agente):
//   gh api repos/<repo>/branches/main        → .protection (contexts clássicos + enforcement_level)
//   gh api repos/<repo>/rules/branches/main  → required_status_checks vindos de rulesets
//   gh api repos/<repo>/actions/workflows/<wf>/runs?status=success&per_page=1
//
// DETERMINÍSTICO: --json sem idade computada — timestamps são literais da API;
// 2 runs seguidas sem mudança no mundo = diff vazio.
//
// Uso (na raiz do repo, GH_TOKEN ou gh auth ativos):
//   node scripts/governance/protection-drift.mjs                    # tabela 🔴🟡🟢; exit 1 se 🔴
//   node scripts/governance/protection-drift.mjs --json             # JSON determinístico no stdout
//   node scripts/governance/protection-drift.mjs --capture          # (re)escreve o baseline do vivo — ato deliberado, commit + PR
//   node scripts/governance/protection-drift.mjs --fixture <f.json> # estado vivo vem de arquivo (simulação/selftest GT-G6)
// Node puro (fs + execSync). Idioma: clone de sdd-scorecard.mjs.

import { readFileSync, writeFileSync, existsSync, realpathSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = process.cwd();
const REPO = 'wagnerra23/oimpresso.com';
const BASELINE = join(ROOT, 'governance', 'required-checks-baseline.json');
const SCORECARD_BASELINE = join(ROOT, 'governance', 'sdd-scorecard-baseline.json');
const STALE_HOURS = 48;
const MODE_JSON = process.argv.includes('--json');
const MODE_CAPTURE = process.argv.includes('--capture');
const fxIdx = process.argv.indexOf('--fixture');
const FIXTURE = fxIdx > -1 ? process.argv[fxIdx + 1] : null;

// métrica measured do scorecard → workflow que mantém a fonte viva (watchdog ADR 0275 §3).
// Métrica nova `measured` sem entry aqui = 🟡 aviso (adicionar o mapeamento).
// V5 (2026-07-12): fonte `orfa:<branch>#<path>` = frescor do `computed_at` do CONTEÚDO do
// arquivo publicado na órfã — NÃO do tip do commit. O write-side da nightly re-pusha com
// [skip ci] todo dia, então o tip avança mesmo quando a suíte morre por OOM e o floor congela;
// medir o tip deixaria 6 dias de floor stale passar como "fresco". Ler o computed_at fecha esse
// buraco (avaliação adversarial 2026-07-12 risco nº1). Fail-closed: sem computed_at legível =
// stale. Segue advisory (GT-G4); o required GT-G3 nunca depende de wall-clock (gate-grita-lobo).
const WATCHDOG_SOURCES = {
  anchor_coverage: 'sdd-scorecard.yml',
  ghost_count: 'sdd-scorecard.yml',
  front_door_coverage: 'sdd-scorecard.yml',
  n_quarantine: 'sdd-scorecard.yml',
  // P14 carona 2 (#3548): a auditoria do repo pelo sqlite-test-corruptors roda dentro do
  // agregador sdd-scorecard — mesmo frescor do resto do scorecard.
  sqlite_corruptors: 'sdd-scorecard.yml',
  // measureDistillerFreshness() roda dentro do agregador sdd-scorecard.mjs — mesmo
  // frescor do resto do scorecard (measured desde o 1º carimbo distilled_at).
  distiller_freshness: 'sdd-scorecard.yml',
  full_suite_pass_rate: 'orfa:governance/nightly-floor#governance/nightly-floor.json',
};

// ── frescor de fonte órfã: computed_at do CONTEÚDO, não o tip ─────────────────
// PURAS + exportadas (testadas em protection-drift-freshness.test.mjs sem rede).
// parseFloorTs: normaliza o carimbo do write-side (floor-compute.mjs grava `ts`/`computed_at`
// no formato compacto 'YYYYMMDD-HHMMSS', UTC) OU um ISO 8601 → string ISO canônica (ou null).
export function parseFloorTs(s) {
  if (typeof s !== 'string' || !s.trim()) return null;
  const t = s.trim();
  const m = /^(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2})(\d{2})$/.exec(t);
  if (m) {
    const iso = `${m[1]}-${m[2]}-${m[3]}T${m[4]}:${m[5]}:${m[6]}Z`;
    return Number.isNaN(Date.parse(iso)) ? null : new Date(iso).toISOString();
  }
  const ms = Date.parse(t);
  return Number.isNaN(ms) ? null : new Date(ms).toISOString();
}

// resolveOrfaFreshness: dado o JSON já parseado do arquivo da órfã, devolve o frescor
// (ISO do computed_at) ou null. fail-closed — sem computed_at reconhecível o watchdog
// trata null como stale (NUNCA), que é o comportamento seguro.
export function resolveOrfaFreshness(content) {
  return parseFloorTs(content?.computed_at);
}

// ghContent: lê o CONTEÚDO de um arquivo numa ref via API `contents` (base64) e devolve o
// JSON parseado. fail-closed: encoding != 'base64' ou content vazio → lança (o caller reduz a
// null = stale). ghFn injetável pra teste sem rede.
export function ghContent(repo, ref, path, ghFn = gh) {
  const res = ghFn(`repos/${repo}/contents/${path}?ref=${encodeURIComponent(ref)}`);
  if (res?.encoding !== 'base64' || !res?.content)
    throw new Error(`contents API: encoding='${res?.encoding ?? 'none'}' (esperado base64 não-vazio) em ${ref}:${path}`);
  return JSON.parse(Buffer.from(res.content, 'base64').toString('utf8'));
}

function gh(path) {
  return JSON.parse(execSync(`gh api "${path}"`, { maxBuffer: 8 * 1024 * 1024, stdio: ['ignore', 'pipe', 'pipe'] }).toString());
}

function fetchLive() {
  if (FIXTURE) {
    const fx = JSON.parse(readFileSync(FIXTURE, 'utf8'));
    // Fixture pode trazer o CONTEÚDO cru da órfã em `orfa_contents` (chave = wf) — resolve o
    // frescor pelo computed_at do CONTEÚDO, sem rede. Prova que o tip é ignorado (selftest GT-G6).
    if (fx.orfa_contents) {
      fx.workflow_runs = fx.workflow_runs ?? {};
      for (const [wf, content] of Object.entries(fx.orfa_contents)) fx.workflow_runs[wf] = resolveOrfaFreshness(content);
    }
    return fx;
  }
  const branch = gh(`repos/${REPO}/branches/main`);
  const rules = gh(`repos/${REPO}/rules/branches/main`).filter((r) => r.type === 'required_status_checks');
  const live = {
    enforcement_level: branch.protection?.required_status_checks?.enforcement_level ?? null,
    classic_contexts: (branch.protection?.required_status_checks?.contexts ?? []).slice().sort(),
    ruleset_contexts: rules.flatMap((r) => (r.parameters?.required_status_checks ?? []).map((c) => c.context)).sort(),
    ruleset_strict: rules.some((r) => r.parameters?.strict_required_status_checks_policy === true),
    workflow_runs: {},
  };
  for (const wf of [...new Set(Object.values(WATCHDOG_SOURCES))].sort()) {
    if (wf.startsWith('orfa:')) {
      // V5: frescor = computed_at do CONTEÚDO do arquivo na órfã (não o tip, que avança com
      // [skip ci] mesmo com o floor congelado por OOM). Formato: orfa:<branch>#<path>.
      const spec = wf.slice(5);
      const hash = spec.indexOf('#');
      const brName = hash > -1 ? spec.slice(0, hash) : spec;
      const path = hash > -1 ? spec.slice(hash + 1) : null;
      let fresh = null;
      if (path) { try { fresh = resolveOrfaFreshness(ghContent(REPO, brName, path)); } catch { fresh = null; } }
      live.workflow_runs[wf] = fresh; // fail-closed: erro/encoding ruim/sem computed_at = null = stale
    } else {
      const runs = gh(`repos/${REPO}/actions/workflows/${wf}/runs?status=success&per_page=1`);
      live.workflow_runs[wf] = runs.workflow_runs?.[0]?.run_started_at ?? null;
    }
  }
  return live;
}

function capture(live) {
  const sha = execSync('git rev-parse --short HEAD', { cwd: ROOT }).toString().trim();
  const body = {
    _meta: {
      baseline: 'Required checks de main CONGELADOS — GT-G4 (plano 2026-06-12 §2 GARANTIDA)',
      adr: 'memory/decisions/0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md',
      regra: 'required que SUMIR do vivo = 🔴 drift (demoção SÓ via PR editando este arquivo + ADR — ADR 0275 §5); required NOVO no vivo = 🟡 aviso (entrar é permitido, abrir PR incorporando); promoção a required atualiza este arquivo no MESMO PR do flip',
      capturado_em: new Date().toISOString().slice(0, 10),
      capturado_de_sha: sha,
      fonte: 'gh api repos/<repo>/branches/main (.protection) + repos/<repo>/rules/branches/main — rota admin não usada (404 pra não-admin)',
      limitacao: 'o resumo público da protection clássica não expõe o flag strict (só rulesets expõem) — strict clássico é verificado no flip R1 pelo humano (ADR 0275 §5)',
      consumidores: ['scripts/governance/protection-drift.mjs', '.github/workflows/protection-drift.yml'],
    },
    branch: 'main',
    enforcement_level: live.enforcement_level,
    classic_protection: { contexts: live.classic_contexts },
    rulesets: { strict_required_status_checks_policy: live.ruleset_strict, contexts: live.ruleset_contexts },
  };
  writeFileSync(BASELINE, JSON.stringify(body, null, 2) + '\n', 'utf8');
  console.log(`  --capture: baseline escrito em governance/required-checks-baseline.json (${live.classic_contexts.length} classic + ${live.ruleset_contexts.length} ruleset @ ${sha}) — commite via PR.`);
}

// Mojibake = UTF-8 do nome correto decodado como latin1 (double-encoding). Acontece quando
// a protection é re-postada com payload inline via shell Windows (incidente 2026-07-02:
// "Â·"/"ConstituiÃ§Ã£o" nos 23 contexts → 10 nunca satisfeitos → todo merge BLOCKED).
// Detectável: latin1-bytes(torto) decodados como UTF-8 devolvem o nome do baseline.
function mojibakeDe(torto) {
  return Buffer.from(torto, 'latin1').toString('utf8');
}

function compareProtection(live, base) {
  const red = [], warn = [];
  const baseAll = new Set([...(base.classic_protection?.contexts ?? []), ...(base.rulesets?.contexts ?? [])]);
  const liveAll = new Set([...(live.classic_contexts ?? []), ...(live.ruleset_contexts ?? [])]);
  const missing = [...baseAll].filter((c) => !liveAll.has(c)).sort();
  const extra = [...liveAll].filter((c) => !baseAll.has(c)).sort();
  const mojibake = extra.filter((e) => missing.includes(mojibakeDe(e))).map((e) => ({ vivo: e, esperado: mojibakeDe(e) }));
  const mojiMissing = new Set(mojibake.map((p) => p.esperado));
  const mojiExtra = new Set(mojibake.map((p) => p.vivo));
  for (const p of mojibake)
    red.push(`MOJIBAKE double-encoding UTF-8 no vivo: "${p.vivo}" (esperado: "${p.esperado}") — context nunca será satisfeito por check-run nenhum = merge deadlock. Causa típica: protection re-postada com payload inline via shell Windows. Reparo: PUT required_status_checks/contexts com \`gh api --input <arquivo UTF-8 sem BOM>\` gerado de governance/required-checks-baseline.json (proibicoes.md §Ambiente, incidente 2026-07-02)`);
  for (const c of missing.filter((c) => !mojiMissing.has(c))) red.push(`required SUMIU do vivo: "${c}" — demoção exige PR editando governance/required-checks-baseline.json + ADR (ADR 0275 §5)`);
  for (const c of extra.filter((c) => !mojiExtra.has(c))) warn.push(`required NOVO no vivo (fora do baseline): "${c}" — entrar é permitido; abrir PR incorporando ao baseline`);
  if (base.enforcement_level === 'everyone' && live.enforcement_level !== 'everyone')
    red.push(`enforcement_level enfraqueceu: baseline "everyone" → vivo "${live.enforcement_level}" (admin bypass liberado)`);
  if (Boolean(base.rulesets?.strict_required_status_checks_policy) !== Boolean(live.ruleset_strict))
    warn.push(`strict_required_status_checks_policy (rulesets) mudou: baseline ${Boolean(base.rulesets?.strict_required_status_checks_policy)} → vivo ${Boolean(live.ruleset_strict)} — refletir via PR de baseline`);
  return { missing, extra, mojibake, red, warn };
}

function watchdog(live) {
  const red = [], warn = [], rows = [];
  if (FIXTURE && !('workflow_runs' in live)) return { rows, red, warn, skipped: 'fixture sem workflow_runs' };
  if (!existsSync(SCORECARD_BASELINE)) return { rows, red, warn: ['governance/sdd-scorecard-baseline.json ausente — watchdog sem métricas'], skipped: null };
  const sb = JSON.parse(readFileSync(SCORECARD_BASELINE, 'utf8'));
  // PROTECTION_DRIFT_NOW: relógio injetável (ISO) pra testar staleness determinística.
  const now = process.env.PROTECTION_DRIFT_NOW ? Date.parse(process.env.PROTECTION_DRIFT_NOW) : Date.now();
  for (const [name, m] of Object.entries(sb.metrics ?? {})) {
    if (m.status !== 'measured') continue;
    const wf = WATCHDOG_SOURCES[name];
    if (!wf) { warn.push(`${name}: measured sem workflow mapeado no watchdog — adicionar em WATCHDOG_SOURCES (protection-drift.mjs)`); continue; }
    const last = live.workflow_runs?.[wf] ?? null;
    const stale = !last || now - Date.parse(last) > STALE_HOURS * 3600 * 1000;
    rows.push({ metric: name, workflow: wf, last_success: last, stale });
    if (stale) red.push(`${name}: fonte ${wf} sem run verde há >${STALE_HOURS}h (último sucesso: ${last ?? 'NUNCA'}) — canário parado é regressão silenciosa (ADR 0275 §3: desarmar MANUALMENTE via PR no baseline até nova sequência de 3 — o ratchet required não afrouxa sozinho)`);
  }
  return { rows, red, warn, skipped: null };
}

// ── main ────────────────────────────────────────────────────────────────────
// isMain guard: importar as puras (parseFloorTs/resolveOrfaFreshness/ghContent) no
// protection-drift-freshness.test.mjs NÃO pode disparar fetchLive() (rede). Clone do
// padrão de sdd-scorecard.mjs.
const isMain = (() => {
  try { return realpathSync(process.argv[1]) === fileURLToPath(import.meta.url); }
  catch { return false; }
})();
if (isMain) main();

function main() {
const live = fetchLive();
if (MODE_CAPTURE) { capture(live); process.exit(0); }
if (!existsSync(BASELINE)) {
  console.error('  🔴 sem baseline — rode `node scripts/governance/protection-drift.mjs --capture` e commite governance/required-checks-baseline.json via PR.');
  process.exit(1);
}
const base = JSON.parse(readFileSync(BASELINE, 'utf8'));
const drift = compareProtection(live, base);
const wd = watchdog(live);
const reds = [...drift.red, ...wd.red];
const warns = [...drift.warn, ...wd.warn];
const verdict = reds.length ? 'red' : warns.length ? 'yellow' : 'green';

if (MODE_JSON) {
  process.stdout.write(JSON.stringify({ live, drift: { missing: drift.missing, extra: drift.extra, mojibake: drift.mojibake }, watchdog: { rows: wd.rows, skipped: wd.skipped ?? null }, reds, warns, verdict }, null, 2) + '\n');
  process.exit(reds.length ? 1 : 0);
}

console.log('\n  PROTECTION DRIFT — main vivo vs governance/required-checks-baseline.json\n');
const baseTotal = (base.classic_protection?.contexts ?? []).length + (base.rulesets?.contexts ?? []).length;
const liveTotal = (live.classic_contexts ?? []).length + (live.ruleset_contexts ?? []).length;
console.log(`  baseline: ${baseTotal} required (${(base.classic_protection?.contexts ?? []).length} classic + ${(base.rulesets?.contexts ?? []).length} ruleset) · vivo: ${liveTotal} · enforcement: ${live.enforcement_level}`);
if (!drift.missing.length && !drift.red.length) console.log('  🟢 nenhum required demovido');
console.log('\n  WATCHDOG STALENESS — fontes das métricas measured (sdd-scorecard-baseline)');
if (wd.skipped) console.log(`  ⏭️  pulado: ${wd.skipped}`);
for (const r of wd.rows) console.log(`  ${r.stale ? '🔴' : '🟢'} ${r.metric.padEnd(22)} ← ${r.workflow} (último verde: ${r.last_success ?? 'NUNCA'})`);
if (warns.length || reds.length) console.log('');
for (const w of warns) console.log(`  🟡 ${w}`);
for (const r of reds) console.log(`  🔴 ${r}`);
console.log(`\n  veredito: ${verdict === 'red' ? '🔴 DRIFT' : verdict === 'yellow' ? '🟡 avisos' : '🟢 ok'}\n`);
process.exit(reds.length ? 1 : 0);
}
