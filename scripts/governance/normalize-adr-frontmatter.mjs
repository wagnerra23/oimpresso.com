#!/usr/bin/env node
// @ts-check
/**
 * normalize-adr-frontmatter.mjs — normaliza status/lifecycle de ADR pro enum canônico.
 *
 * Implementa o mapa de normalização da ADR 0257. SÓ toca frontmatter (status/lifecycle
 * + adiciona kind quando aplicável) — corpo da decisão é IMUTÁVEL (append-only).
 * O PR que rodar isto com --write precisa do label `adr-metadata-normalization`
 * (a exceção cirúrgica do gate block-adr-edits, ADR 0257).
 *
 * Uso:
 *   node scripts/governance/normalize-adr-frontmatter.mjs          (dry-run — só relata)
 *   node scripts/governance/normalize-adr-frontmatter.mjs --write  (aplica)
 *
 * Refs: ADR 0257 (modelo status/lifecycle/kind) · ADR 0105 (feature-wish) · 0256 (sentinela)
 */
import { readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = process.cwd();
const WRITE = process.argv.includes('--write');
const DIR = 'memory/decisions';

const STATUS_MAP = { accepted: 'aceito', aceita: 'aceito', proposed: 'proposto' };
const LIFECYCLE_MAP = { active: 'ativo', canon: 'ativo', feature_wish: 'historical' };

let changed = 0;
const report = [];

for (const f of readdirSync(join(ROOT, DIR))) {
  if (!/^\d{4}-.+\.md$/.test(f)) continue;
  const path = join(ROOT, DIR, f);
  const txt = readFileSync(path, 'utf8');
  if (!txt.startsWith('---')) continue; // só ADR com YAML frontmatter (tabela = migração à parte)
  const end = txt.indexOf('\n---', 3);
  if (end === -1) continue;
  const fm = txt.slice(0, end);
  const body = txt.slice(end);
  let lines = fm.split('\n');
  const diffs = [];
  let wasFeatureWish = false;
  let hasKind = lines.some((l) => /^kind:/.test(l));

  lines = lines.map((line) => {
    let m;
    if ((m = line.match(/^status:\s*["']?([A-Za-z_-]+)["']?\s*$/))) {
      const v = m[1].toLowerCase();
      if (STATUS_MAP[v]) { diffs.push(`status: ${m[1]} → ${STATUS_MAP[v]}`); return `status: ${STATUS_MAP[v]}`; }
    }
    if ((m = line.match(/^lifecycle:\s*["']?([A-Za-z_-]+)["']?\s*$/))) {
      const v = m[1].toLowerCase();
      if (v === 'feature_wish') wasFeatureWish = true;
      if (LIFECYCLE_MAP[v]) { diffs.push(`lifecycle: ${m[1]} → ${LIFECYCLE_MAP[v]}`); return `lifecycle: ${LIFECYCLE_MAP[v]}`; }
    }
    return line;
  });

  // feature_wish → adiciona o eixo kind (categoria ortogonal, ADR 0257/0105) se faltar
  if (wasFeatureWish && !hasKind) {
    const li = lines.findIndex((l) => /^lifecycle:/.test(l));
    if (li !== -1) { lines.splice(li + 1, 0, 'kind: feature-wish'); diffs.push('+ kind: feature-wish'); }
  }

  if (diffs.length) {
    changed++;
    report.push({ file: f, diffs });
    if (WRITE) writeFileSync(path, lines.join('\n') + body);
  }
}

console.log(`\n📐 normalize-adr-frontmatter — ${changed} ADR(s) ${WRITE ? 'NORMALIZADAS' : 'a normalizar (dry-run)'}\n`);
for (const r of report) {
  console.log(`  ${r.file}`);
  r.diffs.forEach((d) => console.log(`     ${d}`));
}
if (!WRITE && changed) console.log(`\nDry-run. Rode com --write (PR com label adr-metadata-normalization) pra aplicar.`);
if (!changed) console.log('✓ nenhuma ADR com drift de enum — frontmatter limpo.');
