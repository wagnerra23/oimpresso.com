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

import { readdirSync, readFileSync, existsSync, writeFileSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { join } from 'node:path';

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

function buildScorecard() {
  const kd = measureKnowledgeDrift();
  const an = measureAnchors();
  const q = measureQuarantine();
  return {
    _meta: {
      scorecard: 'SDD — sistema spec-anchored + verificação agêntica (plano 2026-06-12 §2)',
      generator: 'scripts/governance/sdd-scorecard.mjs',
      plan: 'memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md',
      determinismo: 'sem timestamps/sha no corpo — re-run sem mudança no repo = diff vazio',
      composta: 'v1 (fontes parciais) ≠ v2 (10/10 vivas) — regimes não comparáveis; composta NÃO é calculada enquanto houver not_yet_measured',
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
      full_suite_pass_rate: notYet('up', '100% não-quarentenado',
        'nightly MySQL diagnóstica (FV-F3) — nenhum run full-repo MySQL jamais foi salvo'),
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
      drift_alarms: notYet('down', 'advisory perene',
        'protection-drift + watchdog de staleness (GT-G4)'),
      backfill_error_rate: notYet('down', '<2%',
        'ledger do protocolo refutador G5 — só existe após 1º lote IA refutado'),
    },
  };
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

// ── main ────────────────────────────────────────────────────────────────────
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
