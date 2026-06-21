#!/usr/bin/env node
// charter-us-lint.mjs — lint do campo canônico `related_us` nos Page Charters
// (resources/js/Pages/**/*.charter.md). Fecha a divergência #1 do roadmap SDD:
// a metade "us:" do backfill foi re-escopada sem ADR — 148 charters, só 16 com
// link de US e naming inconsistente (3 `us:` × 13 `related_us:`).
//
// O QUE FAZ:
//   - Declara `related_us` como o ÚNICO campo canônico (alinhado a related_adrs).
//   - Conta charters_sem_us (cobertura do join US→tela — fonte SA-A5/P10).
//   - Flaga o campo legacy `us:` (regressão de naming — já migrado nos 3 legacy).
//   - Valida o shape de cada slug contra o pattern do charter.schema.json.
//
// MODO --check (advisory-de-nascença, promovível a ratchet depois):
//   exit 1 se um charter NOVO/TOCADO (git diff vs BASE, ou --files-from) NÃO tiver
//   `related_us` válido. Charters pré-existentes (132 sem nada) NÃO bloqueiam —
//   o backfill é lote IA futuro (roadmap SDD P10). Só morde o que nasce/muda agora.
//
// COUNTERFACTUAL (DoD): charter tocado sem related_us → --check exit 1;
//                       charter tocado com related_us válido → --check exit 0.
//
// USO (raiz do repo):
//   node scripts/governance/charter-us-lint.mjs            # relatório de cobertura (exit 0)
//   node scripts/governance/charter-us-lint.mjs --json     # pro Daily Brief / agregador
//   node scripts/governance/charter-us-lint.mjs --check    # morde charters NOVOS/tocados (CI/pre-commit)
//   node scripts/governance/charter-us-lint.mjs --check --files-from=<arquivo>
//                                                          # lista explícita de tocados (1 path/linha)
// Node puro, sem deps, sem rede. Lê o pattern do próprio charter.schema.json (fonte única).

import { readdirSync, readFileSync, existsSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { execSync } from 'node:child_process';

const ROOT = process.cwd();
const JSON_OUT = process.argv.includes('--json');
const CHECK = process.argv.includes('--check');
const FILES_FROM = (process.argv.find((a) => a.startsWith('--files-from=')) || '').split('=')[1] || null;
const BASE = process.env.BASE_REF || 'origin/main';

const PAGES_DIR = 'resources/js/Pages';
const SCHEMA_REL = 'scripts/memory-schemas/charter.schema.json';

// --- pattern canônico de slug US — lido do schema (fonte única; sem hard-code divergente) ---
function loadSlugPattern() {
  const fallback = '^US-[A-Za-z0-9]+(?:-[A-Za-z0-9]+)+$';
  try {
    const schema = JSON.parse(readFileSync(join(ROOT, SCHEMA_REL), 'utf8'));
    const p = schema?.properties?.related_us?.items?.pattern;
    return p || fallback;
  } catch {
    return fallback;
  }
}
const SLUG_RE = new RegExp(loadSlugPattern());

// --- walk recursivo: todos os *.charter.md sob Pages/ ---
function walkCharters(dir, acc = []) {
  if (!existsSync(dir)) return acc;
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    const full = join(dir, e.name);
    if (e.isDirectory()) walkCharters(full, acc);
    else if (e.isFile() && e.name.endsWith('.charter.md')) acc.push(full);
  }
  return acc;
}

// --- extrai frontmatter YAML (entre os --- iniciais) sem dep externa ---
function frontmatter(body) {
  const m = body.match(/^---\r?\n([\s\S]*?)\r?\n---/);
  return m ? m[1] : '';
}

// Lê related_us (array inline `[A, B]`) OU o legacy us: (escalar OU array).
// Retorna { hasRelated, relatedSlugs[], hasLegacyUs, legacySlugs[] }.
function parseUsFields(fm) {
  const lines = fm.split(/\r?\n/);
  const grab = (key) => {
    const re = new RegExp(`^${key}:\\s*(.+?)\\s*$`, 'i');
    for (const l of lines) {
      const mm = l.match(re);
      if (mm) return mm[1].trim();
    }
    return null;
  };
  const toSlugs = (raw) => {
    if (raw == null) return [];
    const inner = raw.replace(/^\[/, '').replace(/\]$/, '');
    return inner.split(',').map((s) => s.trim().replace(/^["']|["']$/g, '')).filter(Boolean);
  };
  const relatedRaw = grab('related_us');
  const legacyRaw = grab('us');
  return {
    hasRelated: relatedRaw != null,
    relatedSlugs: toSlugs(relatedRaw),
    hasLegacyUs: legacyRaw != null,
    legacySlugs: toSlugs(legacyRaw),
  };
}

// --- conjunto de charters NOVOS/TOCADOS (pro --check) ---
function touchedCharters() {
  let names = [];
  if (FILES_FROM && existsSync(FILES_FROM)) {
    names = readFileSync(FILES_FROM, 'utf8').split(/\r?\n/);
  } else {
    try {
      const out = execSync(`git diff --name-only --diff-filter=ACMR ${BASE}...HEAD`, {
        cwd: ROOT, stdio: ['ignore', 'pipe', 'ignore'],
      }).toString();
      names = out.split(/\r?\n/);
    } catch {
      names = []; // sem git/sem base → --check vira no-op gracioso (não inventa bloqueio)
    }
  }
  return new Set(
    names.map((l) => l.trim()).filter((l) => l.endsWith('.charter.md')).map((l) => l.replace(/\\/g, '/'))
  );
}

// --- análise ---
const charters = walkCharters(join(ROOT, PAGES_DIR)).sort();
const touched = CHECK ? touchedCharters() : new Set();

let semUs = 0;
let comUs = 0;
const legacyKeyHits = []; // charters que ainda usam `us:` (regressão de naming)
const invalidSlugHits = []; // charters com slug fora do pattern
const blockers = []; // charters NOVOS/tocados sem related_us válido (mordem em --check)

for (const abs of charters) {
  const rel = abs.replace(ROOT, '').replace(/\\/g, '/').replace(/^\//, '');
  const body = readFileSync(abs, 'utf8');
  const f = parseUsFields(frontmatter(body));

  const validSlugs = f.relatedSlugs.filter((s) => SLUG_RE.test(s));
  const badSlugs = f.relatedSlugs.filter((s) => !SLUG_RE.test(s));
  const hasValidRelated = f.hasRelated && validSlugs.length > 0;

  if (hasValidRelated) comUs++; else semUs++;
  if (f.hasLegacyUs) legacyKeyHits.push(rel);
  if (badSlugs.length) invalidSlugHits.push({ charter: rel, slugs: badSlugs });

  if (CHECK && touched.has(rel)) {
    if (!f.hasRelated) {
      blockers.push({ charter: rel, motivo: 'charter novo/tocado SEM related_us (declare a US que esta tela atende)' });
    } else if (badSlugs.length) {
      blockers.push({ charter: rel, motivo: `related_us com slug fora do padrão US-…: ${badSlugs.join(', ')}` });
    } else if (validSlugs.length === 0) {
      blockers.push({ charter: rel, motivo: 'related_us vazio (declare ao menos 1 slug US-…)' });
    } else if (f.hasLegacyUs) {
      blockers.push({ charter: rel, motivo: 'campo legacy `us:` presente — migre 100% pra related_us (zero divergência)' });
    }
  }
}

const total = charters.length;
const coverage = total ? ((comUs / total) * 100).toFixed(1) : '0.0';

if (JSON_OUT) {
  console.log(JSON.stringify({
    ok: CHECK ? blockers.length === 0 : true,
    total_charters: total,
    charters_com_us: comUs,
    charters_sem_us: semUs,
    cobertura_pct: Number(coverage),
    legacy_us_key: legacyKeyHits,
    slugs_invalidos: invalidSlugHits,
    blockers,
    mode: CHECK ? 'check' : 'report',
  }, null, 2));
  process.exit(CHECK && blockers.length ? 1 : 0);
}

console.log(`\n  charter-us-lint — ${total} charters · ${comUs} com related_us · ${semUs} sem (cobertura ${coverage}%)\n`);
if (legacyKeyHits.length) {
  console.log(`  🟡 ${legacyKeyHits.length} charter(s) ainda com campo legacy \`us:\` (canônico é related_us):`);
  for (const c of legacyKeyHits) console.log(`     - ${c}`);
}
if (invalidSlugHits.length) {
  console.log(`  🟡 ${invalidSlugHits.length} charter(s) com slug fora do padrão US-…:`);
  for (const h of invalidSlugHits) console.log(`     - ${h.charter}: ${h.slugs.join(', ')}`);
}
if (!CHECK) {
  console.log(`\n  ℹ Advisory de cobertura. ${semUs} charters sem related_us = lote IA futuro (roadmap SDD P10).`);
  console.log('    --check só morde charters NOVOS/tocados (advisory-de-nascença).\n');
  process.exit(0);
}

if (blockers.length) {
  console.error(`\n  🔴 ${blockers.length} charter(s) NOVO(s)/tocado(s) sem related_us válido:`);
  for (const b of blockers) console.error(`     - ${b.charter}: ${b.motivo}`);
  console.error('\n  ✗ --check morde: charter que nasce/muda agora declara a US que atende.\n');
  process.exit(1);
}
console.log('  ✓ --check: todos os charters NOVOS/tocados têm related_us válido.\n');
process.exit(0);
