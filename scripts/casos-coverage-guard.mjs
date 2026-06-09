#!/usr/bin/env node
// scripts/casos-coverage-guard.mjs — Gate G-1 (trio-de-tela) + G-2 (rastreabilidade caso↔teste)
//                                    da Governança executável (ADR 0264).
//
// =====================================================================================
// POR QUE EXISTE
// =====================================================================================
// Wagner 2026-06-09: "se não tiver testes vai desandar… se isso ficar apenas em memória
// sem uma regra obrigando fazer vai morrer no tempo." As 4 camadas que seguram drift
// (charter/casos/teste/proibições) viram MÁQUINA, não disciplina (ADR 0264, lei da
// catraca ADR 0256). Censo @main 2026-06-09: 277 páginas roteadas, 138 charter, 1 casos.md.
//
// Este guard cobre 2 das 4 camadas:
//   G-1 TRIO-DE-TELA  : toda .tsx ROTEADA em resources/js/Pages/** (exclui _components/)
//                       DEVE ter, ao lado, <Nome>.charter.md E <Nome>.casos.md.
//   G-2 RASTREABILIDADE: todo UC-* declarado num *.casos.md DEVE ser citado por >=1 teste
//                       (string do ID em Tests/**, tests/**, ou spec Playwright e2e/**).
//                       UC órfão (caso no papel sem teste que o defenda) = violação.
//
// =====================================================================================
// RATCHET / BASELINE — gêmeo de pageheader-migration-guard.mjs + no-mock-in-prod.mjs
// =====================================================================================
// O baseline (scripts/casos-coverage-baseline.json) fotografa as violações ATUAIS (o
// débito legado). O gate NÃO obriga limpar o legado de uma vez (quebraria o repo — são
// 276 telas sem casos.md). Só impede PIORAR: violação NOVA (não no baseline) → exit 1.
// Tela nova nasce com trio; UC novo nasce com teste. Encolher o débito é sempre OK
// (F3 ratchet: cada tela tocada fecha o trio dela → baseline cai até 0).
//
// MODOS (idioma dos guards existentes):
//   node scripts/casos-coverage-guard.mjs                  # valida vs baseline (exit 1 se piorou)
//   node scripts/casos-coverage-guard.mjs --write-baseline  # (re)grava baseline (uso consciente)
//   node scripts/casos-coverage-guard.mjs --report          # imprime o relatório de dívida (humano)
//   node scripts/casos-coverage-guard.mjs --json            # saída JSON pra CI
//
// Refs: ADR 0264 (governança executável G-1/G-2) · ADR 0261 (enforcement faseado) ·
//       ADR 0256 (catraca knowledge-survival) · ADR 0243 casos.md (Index.casos.md Oficina).

import { readFileSync, writeFileSync, existsSync, readdirSync } from 'node:fs';
import { resolve, join, dirname, basename, relative } from 'node:path';

const ROOT = process.cwd();
const PAGES_DIR = resolve(ROOT, 'resources/js/Pages');
const BASELINE_PATH = resolve(ROOT, 'scripts/casos-coverage-baseline.json');
const TEST_DIRS = ['Modules', 'tests', 'app', 'e2e']; // onde um UC-id pode ser referenciado por teste

const MODE_WRITE = process.argv.includes('--write-baseline');
const MODE_REPORT = process.argv.includes('--report');
const MODE_JSON = process.argv.includes('--json');

const norm = (p) => relative(ROOT, p).replace(/\\/g, '/');

// ---------------------------------------------------------------------------
// Coleta de arquivos
// ---------------------------------------------------------------------------
function walk(dir, filter, acc = []) {
  if (!existsSync(dir)) return acc;
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    const full = join(dir, e.name);
    if (e.isDirectory()) {
      if (e.name === 'node_modules' || e.name === 'vendor' || e.name === '.git') continue;
      walk(full, filter, acc);
    } else if (e.isFile() && filter(full, e.name)) {
      acc.push(full);
    }
  }
  return acc;
}

// "Página roteada" = .tsx em Pages/** que NÃO está sob /_components/ e não é um arquivo
// de teste/charter/casos. É a heurística literal do handoff (ADR 0264 G-1). O refino de
// "roteada de verdade" (cruzar com routes) é F3 — por ora o baseline absorve o conjunto.
function listPages() {
  const tsx = walk(PAGES_DIR, (full, name) => name.endsWith('.tsx') && !name.endsWith('.d.ts'));
  return tsx
    .filter((f) => !norm(f).includes('/_components/'))
    .map((f) => norm(f))
    .sort((a, b) => a.localeCompare(b));
}

// ---------------------------------------------------------------------------
// G-1 — trio-de-tela
// ---------------------------------------------------------------------------
function trioViolations(pages) {
  const violations = [];
  for (const page of pages) {
    const dir = dirname(page);
    const base = basename(page, '.tsx');
    const charter = `${dir}/${base}.charter.md`;
    const casos = `${dir}/${base}.casos.md`;
    if (!existsSync(resolve(ROOT, charter))) violations.push(`trio:missing-charter:${page}`);
    if (!existsSync(resolve(ROOT, casos))) violations.push(`trio:missing-casos:${page}`);
  }
  return violations;
}

// ---------------------------------------------------------------------------
// G-2 — rastreabilidade caso↔teste
// ---------------------------------------------------------------------------
// UC-id canônico: UC-<sufixo opcional letras><dígitos>[letra]. Ex: UC-01, UC-06, UC-V05,
// UC-F02, UC-10b. Conservador pra não capturar "UC-" solto ou prosa.
const UC_RE = /\bUC-[A-Z]{0,3}\d{1,3}[a-zA-Z]?\b/g;

function listCasosFiles() {
  return walk(PAGES_DIR, (full, name) => name.endsWith('.casos.md')).map(norm).sort();
}

function ucsInCasos(casosFiles) {
  // map: ucId -> casosFile (primeira ocorrência) — declarações de UC
  const out = [];
  for (const file of casosFiles) {
    const content = readFileSync(resolve(ROOT, file), 'utf8');
    const found = new Set();
    for (const m of content.matchAll(UC_RE)) found.add(m[0].toUpperCase());
    for (const uc of found) out.push({ uc, file });
  }
  return out;
}

function buildTestCorpus() {
  // Concatena conteúdo de todos os arquivos de teste/spec (string-search dos UC-ids).
  // Teste = *Test.php, *.test.*, *.spec.*  em Modules/**/Tests, tests/**, e2e/**.
  let corpus = '';
  for (const d of TEST_DIRS) {
    const base = resolve(ROOT, d);
    const files = walk(base, (full, name) =>
      /Test\.php$/.test(name) || /\.test\.[tj]sx?$/.test(name) || /\.spec\.[tj]sx?$/.test(name),
    );
    for (const f of files) {
      try { corpus += '\n' + readFileSync(f, 'utf8'); } catch { /* ignore */ }
    }
  }
  return corpus;
}

function orphanUcViolations(ucDecls, testCorpus) {
  const violations = [];
  for (const { uc, file } of ucDecls) {
    // UC referenciado = string do id aparece no corpus de testes.
    if (!testCorpus.includes(uc)) violations.push(`uc-orphan:${file}#${uc}`);
  }
  return violations;
}

// ---------------------------------------------------------------------------
// Cálculo
// ---------------------------------------------------------------------------
function computeViolations() {
  const pages = listPages();
  const casosFiles = listCasosFiles();
  const ucDecls = ucsInCasos(casosFiles);
  const testCorpus = buildTestCorpus();

  const trio = trioViolations(pages);
  const orphans = orphanUcViolations(ucDecls, testCorpus);

  const all = [...trio, ...orphans].sort((a, b) => a.localeCompare(b));
  return {
    violations: all,
    stats: {
      pages: pages.length,
      casos_files: casosFiles.length,
      ucs_declared: ucDecls.length,
      missing_charter: trio.filter((v) => v.startsWith('trio:missing-charter')).length,
      missing_casos: trio.filter((v) => v.startsWith('trio:missing-casos')).length,
      orphan_ucs: orphans.length,
    },
  };
}

function loadBaseline() {
  if (!existsSync(BASELINE_PATH)) return null;
  return JSON.parse(readFileSync(BASELINE_PATH, 'utf8'));
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------
function main() {
  const { violations, stats } = computeViolations();

  if (MODE_JSON) {
    const baseline = loadBaseline();
    const baseSet = new Set(baseline?.violations || []);
    const novos = violations.filter((v) => !baseSet.has(v));
    console.log(JSON.stringify({ stats, total: violations.length, baseline: baseSet.size, novos, ok: novos.length === 0 }, null, 2));
    process.exit(novos.length === 0 ? 0 : 1);
  }

  if (MODE_REPORT) {
    console.log('# Relatório de dívida — casos:check (ADR 0264 G-1/G-2)\n');
    console.log(`Páginas roteadas (Pages/**, excl _components/): ${stats.pages}`);
    console.log(`Arquivos casos.md: ${stats.casos_files} · UCs declarados: ${stats.ucs_declared}\n`);
    console.log(`Telas SEM charter.md: ${stats.missing_charter}`);
    console.log(`Telas SEM casos.md:   ${stats.missing_casos}`);
    console.log(`UCs órfãos (sem teste): ${stats.orphan_ucs}`);
    console.log(`\nTOTAL de violações (débito): ${violations.length}`);
    console.log('\n→ F1 fotografa isso no baseline (não-bloqueante). F3 ratchet zera tela-a-tela.');
    process.exit(0);
  }

  if (MODE_WRITE) {
    const out = {
      _meta: {
        generated_at: new Date().toISOString(),
        gate: 'casos:check (ADR 0264 G-1 trio + G-2 rastreabilidade)',
        stats,
        nota: 'Violações ATUAIS fotografadas (débito legado). Gate falha só em violação NOVA vs este baseline (ratchet). Encolher é sempre OK. Regravar conscientemente: npm run casos:baseline:write',
        refs: ['ADR 0264', 'ADR 0261', 'ADR 0256'],
      },
      violations,
    };
    writeFileSync(BASELINE_PATH, JSON.stringify(out, null, 2) + '\n');
    console.log(`✅ Baseline gravado: ${violations.length} violações (${stats.missing_casos} sem casos · ${stats.missing_charter} sem charter · ${stats.orphan_ucs} UC órfão) → ${norm(BASELINE_PATH)}`);
    process.exit(0);
  }

  // VALIDATE
  console.log(`casos:check · ${violations.length} violações (telas: ${stats.pages}, casos.md: ${stats.casos_files})`);
  const baseline = loadBaseline();
  if (!baseline) {
    console.error(`\n❌ Baseline ausente (${norm(BASELINE_PATH)}). Rode: npm run casos:baseline:write`);
    process.exit(1);
  }
  const baseSet = new Set(baseline.violations || []);
  const novos = violations.filter((v) => !baseSet.has(v));

  if (novos.length) {
    console.error(`\n❌ ${novos.length} violação(ões) NOVA(s) de trio/rastreabilidade (não no baseline):\n`);
    for (const v of novos.slice(0, 50)) console.error('  🆕 ' + v);
    if (novos.length > 50) console.error(`  … +${novos.length - 50}`);
    console.error(
      `\nTela NOVA precisa do trio: <Nome>.tsx + <Nome>.charter.md + <Nome>.casos.md (ADR 0264 G-1).` +
        `\nUC NOVO precisa de teste citando o id (ADR 0264 G-2).` +
        `\nSe for legado movido/refatorado conscientemente: npm run casos:baseline:write`,
    );
    process.exit(1);
  }

  const delta = (baseline.violations?.length || 0) - violations.length;
  console.log(`✅ Sem violações novas (débito ${delta > 0 ? `caiu −${delta}` : 'estável'} vs baseline).`);
  process.exit(0);
}

main();
