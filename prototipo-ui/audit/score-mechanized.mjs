#!/usr/bin/env node
// score-mechanized.mjs — scorer DETERMINÍSTICO (zero LLM) da metade mecanizável da GOLDEN-REFERENCE.
//
// Lê cada resources/js/Pages/**/*.tsx, roda os regex das regras mecanizadas (R1,R2,R3,R4,R6,R7,R9),
// puxa a contagem ds/* real por arquivo de config/eslint-baseline.json (os 6 ds/* vivem sob
// no-restricted-syntax — agregado, não split por subtipo), e escreve 1 design-report.json por tela.
// As regras JULGADAS (R5 gradient · R8 PT-BR · R10 overflow-chain) ficam status:"n/a" pendentes do agente LLM.
//
// É a "frente paralela-segura" rodando SEM token de agente: a evidência mecanizada é reproduzível
// por qualquer um que rode este script. O agente LLM só agrega o julgamento das ~3 regras restantes.
//
// Uso: node prototipo-ui/audit/score-mechanized.mjs [--limit N] [--only <substr>]

import { readFileSync, writeFileSync, existsSync, readdirSync, statSync, mkdirSync } from 'node:fs';
import { join, dirname, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execSync } from 'node:child_process';

const HERE = dirname(fileURLToPath(import.meta.url));
const REPO = join(HERE, '..', '..');
const REPORTS = join(HERE, 'reports');
const PAGES = join(REPO, 'resources/js/Pages');

const argv = process.argv.slice(2);
const getArg = (f) => { const i = argv.indexOf(f); return i >= 0 ? argv[i + 1] : null; };
const LIMIT = getArg('--limit') ? parseInt(getArg('--limit'), 10) : Infinity;
const ONLY = getArg('--only');

// peso por regra (GOLDEN-REFERENCE). Só as mecanizadas entram no dedutor determinístico.
const WEIGHT = { R1: 3, R2: 3, R3: 1, R4: 1, R6: 1, R7: 2, R9: 2 };
const JUDGED = { R5: 1, R8: 1, R10: 2 }; // pendentes do agente — NÃO deduzem aqui
const ALL_IDS = ['R1', 'R2', 'R3', 'R4', 'R5', 'R6', 'R7', 'R8', 'R9', 'R10'];

function loadDsMap() {
  const f = join(REPO, 'config/eslint-baseline.json');
  const map = {};
  if (!existsSync(f)) { console.warn('[score] config/eslint-baseline.json ausente — ds_total=0'); return map; }
  try {
    const j = JSON.parse(readFileSync(f, 'utf8'));
    for (const [key, n] of Object.entries(j.counts || {})) {
      const sep = key.lastIndexOf('|');
      const file = key.slice(0, sep), rule = key.slice(sep + 1);
      if (rule === 'no-restricted-syntax') map[file] = (map[file] || 0) + (n || 1);
    }
  } catch (e) { console.warn('[score] baseline parse falhou:', e.message); }
  return map;
}

function walk(dir, acc = []) {
  for (const name of readdirSync(dir)) {
    const p = join(dir, name);
    if (statSync(p).isDirectory()) walk(p, acc);
    else if (name.endsWith('.tsx') && !name.includes('.charter')) acc.push(p);
  }
  return acc;
}

const RX = {
  hex: /#[0-9a-fA-F]{3,8}\b/g,
  colorFn: /\b(?:oklch|rgba?|hsla?)\s*\(/g,
  native: /<(?:select|input|textarea|table)[\s/>]/g,
  ls: /localStorage\.(?:get|set|remove)Item\s*\(\s*[`'"]([^`'"]+)/g,
  svg: /<svg[\s/>]/g,
  iconLib: /from\s+['"](?:react-icons|@heroicons|@tabler\/icons|feather)/g,
  // R6 = emoji REAL (plano suplementar). NÃO inclui dingbats BMP (✓✕★✦⚙⬇) — esses são
  // glyphs de UI (smell de R4 "usar lucide", não "emoji"). Calibrado 2026-05-31 após FP nos goldens.
  emoji: /[\u{1F000}-\u{1FAFF}]/gu,
  statusFill: /\bbg-(?:red|rose|green|emerald|amber|yellow|orange|sky|blue|indigo|violet)-(?:50|100|200)\b/g,
  main: /<main[\s/>]/g,
};

function detect(src) {
  const h = {};
  h.R1 = [...(src.match(RX.hex) || []).filter((x) => !/^#(?:fff|ffffff|000|000000)$/i.test(x)), ...(src.match(RX.colorFn) || [])];
  h.R2 = src.match(RX.native) || [];
  h.R3 = []; let m; RX.ls.lastIndex = 0; while ((m = RX.ls.exec(src))) if (!m[1].startsWith('oimpresso.')) h.R3.push(m[1]);
  h.R4 = [...(src.match(RX.svg) || []), ...(src.match(RX.iconLib) || [])];
  h.R6 = src.match(RX.emoji) || [];
  h.R7 = src.match(RX.statusFill) || [];
  h.R9 = src.match(RX.main) || [];
  return h;
}

function evidenceOf(id, hits) {
  const uniq = [...new Set(hits.map(String))].slice(0, 4).join(' · ');
  return `${hits.length}× — ${uniq}`;
}

function scoreFile(abs, dsMap, sha) {
  const rel = relative(REPO, abs).replace(/\\/g, '/');
  const screen = rel.replace(/^resources\/js\/Pages\//, '').replace(/\.tsx$/, '');
  let src;
  try { src = readFileSync(abs, 'utf8'); } catch { return null; }
  const h = detect(src);

  const rules = []; let deduct = 0;
  for (const id of ALL_IDS) {
    if (WEIGHT[id] != null) {
      const fail = h[id].length > 0;
      if (fail) deduct += WEIGHT[id] * 4;
      rules.push({ id, status: fail ? 'fail' : 'pass', mechanized: true, ...(fail ? { evidence: evidenceOf(id, h[id]) } : {}) });
    } else {
      rules.push({ id, status: 'n/a', mechanized: false, evidence: 'regra julgada — pendente do agente LLM' });
    }
  }

  const ds = dsMap[rel] || 0;
  const nota = Math.max(0, 100 - deduct - Math.min(ds, 20));
  const nivel = nota >= 95 ? 'Champion' : nota >= 85 ? 'Leader' : nota >= 70 ? 'Advanced' : nota >= 50 ? 'Developing' : 'Beginner';
  const failed = rules.filter((r) => r.mechanized && r.status === 'fail').map((r) => r.id);

  return {
    $schema: '../design-report.schema.json',
    screen, file: rel, measured_against_sha: sha,
    scored_by: 'mechanized-v1', scored_at: new Date().toISOString().slice(0, 10),
    archetype: 'other', persona: 'misto',
    nota, nivel, rules,
    ds_violations: { total: ds, by_rule: {} },
    top_gaps: failed.length ? [{ dim: 'Pre-Flight-conformance', best_of_class: 'Linear/Stripe (zero cor crua, componente DS, sem nativo)', fix: `Regras mecanizadas falhando: ${failed.join(', ')} — ver GOLDEN-REFERENCE`, esforco: failed.length > 3 ? 'L' : 'M' }] : [],
    resumo: `Score mecanizado (regex + ds/*). ${failed.length} regra(s) mecanizada(s) falhando + ds/*=${ds}. Regras julgadas (R5/R8/R10) pendentes do agente LLM — nota é TETO provisório.`,
    evidence_links: [],
  };
}

function main() {
  let sha = 'unknown';
  try { sha = execSync('git rev-parse --short HEAD', { cwd: REPO }).toString().trim(); } catch {}
  if (!existsSync(REPORTS)) mkdirSync(REPORTS, { recursive: true });
  const dsMap = loadDsMap();

  let files = walk(PAGES).filter((p) => !/[\\/]_/.test(relative(PAGES, p))); // pula _components/_form/etc
  if (ONLY) files = files.filter((p) => p.replace(/\\/g, '/').includes(ONLY));
  files = files.slice(0, LIMIT);

  let written = 0;
  for (const abs of files) {
    const rep = scoreFile(abs, dsMap, sha);
    if (!rep) continue;
    const slug = rep.screen.replace(/\//g, '__');
    writeFileSync(join(REPORTS, `${slug}.design-report.json`), JSON.stringify(rep, null, 2), 'utf8');
    written++;
  }
  console.log(`[score] ${written} tela(s) pontuadas (mecanizado) contra HEAD ${sha} · ds-map ${Object.keys(dsMap).length} arquivos`);
  console.log(`[score] reports em prototipo-ui/audit/reports/ — rode consolidate.mjs pro placar`);
}

main();
