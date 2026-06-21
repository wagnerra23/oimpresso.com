#!/usr/bin/env node
// ghost-fix.mjs — codemod de ghost-names em memory/requisitos/** (Semana 0, frente KL).
//
// POR QUE EXISTE: knowledge-drift.mjs DETECTA docs citando Modules/<X> que não existe
// (39/61 módulos citantes, 27 nomes em 2026-06-12). Este script CORRIGE a fatia Classe A:
// renames 1:1 com evidência dura (ADR + commit), curados em governance/ghost-rename-map.json.
// Nome sem evidência fica em 'excluded' no map e NUNCA é tocado — vai pra fila humana.
// Plano-mãe: memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md (§1 Classe A).
//
// ESCOPO HARDCODED: memory/requisitos/**/*.md EXCETO **/adr/** (ADRs são append-only,
// ADR 0094 Art.3 — um ADR de rename CITA o nome antigo como FATO). Só a forma 'Modules/<Nome>'
// (mesma token-boundary do knowledge-drift.mjs). Forma namespace 'Modules\<Nome>' fica fora do v1.
//
// Uso:
//   node scripts/governance/ghost-fix.mjs            # dry-run (default) — relatório, 0 writes
//   node scripts/governance/ghost-fix.mjs --write    # aplica (FASE 2 — só após Wagner revisar o map)
//   node scripts/governance/ghost-fix.mjs --json     # relatório em JSON
//
// Idempotente: re-run pós --write = 0 ocorrências mapeáveis = 0 diffs.
// Node puro (fs/path). Sem deps, sem DB, sem PHP.

import { readdirSync, readFileSync, writeFileSync, existsSync } from 'node:fs';
import { join, sep } from 'node:path';

const ROOT = process.cwd();
const SCOPE = join(ROOT, 'memory', 'requisitos'); // HARDCODED — não aceita path por arg
const MAP_FILE = join(ROOT, 'governance', 'ghost-rename-map.json');
const WRITE = process.argv.includes('--write');
const JSON_OUT = process.argv.includes('--json');

if (!existsSync(SCOPE) || !existsSync(MAP_FILE)) {
  console.error(`ERRO: rodar da raiz do repo (faltou ${!existsSync(SCOPE) ? SCOPE : MAP_FILE})`);
  process.exit(2);
}

const map = JSON.parse(readFileSync(MAP_FILE, 'utf8'));
const renames = map.renames ?? {};
const excluded = new Set(Object.keys(map.excluded ?? {}));

// ── valida o map ANTES de qualquer coisa (anti-ghost-novo) ──────────────────
for (const [from, r] of Object.entries(renames)) {
  if (!existsSync(join(ROOT, 'Modules', r.to))) {
    console.error(`ERRO no map: rename ${from}->${r.to} mas Modules/${r.to} NÃO existe no disco — corrigir o map antes.`);
    process.exit(2);
  }
  if (existsSync(join(ROOT, 'Modules', from))) {
    console.error(`ERRO no map: '${from}' existe em Modules/ — não é ghost; remover do map.`);
    process.exit(2);
  }
}

// ── varre o escopo ───────────────────────────────────────────────────────────
// ADRs são append-only (ADR 0094 Art.3): um ADR de rename CITA o nome antigo como FATO
// (ex: memory/requisitos/MemCofre/adr/0008-rename-docvault-para-memcofre.md). O codemod
// NUNCA pode reescrevê-lo — pula a subárvore adr/ inteira, em qualquer profundidade.
function allMd(dir) {
  const out = [];
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    const p = join(dir, e.name);
    if (e.isDirectory()) {
      if (e.name === 'adr') continue; // hard-skip append-only — ver comentário acima
      out.push(...allMd(p));
    } else if (e.name.endsWith('.md')) out.push(p);
  }
  return out;
}

// mesma token-boundary do knowledge-drift.mjs (nome = [A-Z][A-Za-z0-9]+, maximal munch)
const REF_RE = /Modules\/([A-Z][A-Za-z0-9]+)((?:\/[A-Za-z0-9_.\-*{}]+)*)/g;

const perModule = new Map(); // <Mod de memory/requisitos> -> stats
const unknownGhosts = new Map(); // nome -> count (ghost sem entry no map — fila)
let filesChanged = 0, totalMapped = 0, totalExcluded = 0, pathMissing = 0;

for (const file of allMd(SCOPE)) {
  const rel = file.slice(ROOT.length + 1).split(sep).join('/');
  const parts = rel.split('/');
  const mod = parts.length > 3 ? parts[2] : '(raiz)'; // memory/requisitos/<Mod>/... vs arquivo solto na raiz
  const txt = readFileSync(file, 'utf8');
  let changed = false;

  const stats = perModule.get(mod) ?? { mapped: 0, excluded: 0, unknown: 0, files: new Set(), byName: {} };
  const out = txt.replace(REF_RE, (whole, name, subpath) => {
    if (existsSync(join(ROOT, 'Modules', name))) return whole; // não é ghost
    if (renames[name]) {
      const to = renames[name].to;
      stats.mapped++; totalMapped++;
      stats.byName[name] = (stats.byName[name] ?? 0) + 1;
      stats.files.add(rel);
      // sinal de path-rot pra fila humana: o subpath existe no destino?
      if (subpath && !existsSync(join(ROOT, 'Modules', to, ...subpath.split('/').filter(Boolean)))) pathMissing++;
      changed = true;
      return `Modules/${to}${subpath}`;
    }
    if (excluded.has(name)) { stats.excluded++; totalExcluded++; }
    else { stats.unknown++; unknownGhosts.set(name, (unknownGhosts.get(name) ?? 0) + 1); }
    return whole;
  });
  perModule.set(mod, stats);

  if (changed && WRITE) { writeFileSync(file, out); filesChanged++; }
  else if (changed) filesChanged++;
}

// ── relatório ────────────────────────────────────────────────────────────────
const rows = [...perModule.entries()]
  .filter(([, s]) => s.mapped + s.excluded + s.unknown > 0)
  .map(([mod, s]) => ({ mod, mapped: s.mapped, excluded: s.excluded, unknown: s.unknown,
    files: [...s.files], byName: s.byName }))
  .sort((a, b) => b.mapped - a.mapped);

const summary = {
  mode: WRITE ? 'WRITE' : 'DRY-RUN',
  scope: 'memory/requisitos/**/*.md (exceto **/adr/** — append-only)',
  occurrences_mapped: totalMapped,
  occurrences_excluded_by_map: totalExcluded,
  occurrences_unknown: [...unknownGhosts.values()].reduce((a, b) => a + b, 0),
  files_with_changes: filesChanged,
  mapped_target_subpath_missing: pathMissing,
  unknown_names: Object.fromEntries(unknownGhosts),
};

if (JSON_OUT) { console.log(JSON.stringify({ summary, perModule: rows }, null, 2)); process.exit(0); }

console.log(`\n  GHOST-FIX — ${summary.mode} (map v${map.version}, ${Object.keys(renames).length} renames curados)\n`);
console.log(`  ${'MÓDULO (memory/requisitos)'.padEnd(28)} ${'map'.padStart(4)} ${'excl'.padStart(5)} ${'desc'.padStart(5)}  renames aplicáveis`);
console.log('  ' + '─'.repeat(78));
for (const r of rows) {
  const names = Object.entries(r.byName).map(([n, c]) => `${n}→${renames[n].to}×${c}`).join(' ');
  console.log(`  ${r.mod.padEnd(28)} ${String(r.mapped).padStart(4)} ${String(r.excluded).padStart(5)} ${String(r.unknown).padStart(5)}  ${names}`);
}
console.log('  ' + '─'.repeat(78));
console.log(`\n  Ocorrências mapeáveis (Classe A):   ${totalMapped} em ${filesChanged} arquivos ${WRITE ? '— APLICADO' : '— nada escrito (dry-run)'}`);
console.log(`  Excluídas pelo map (B/C/ambíguo):   ${totalExcluded}  → ficam pra lápide/reescrita/fila humana`);
console.log(`  Ghosts NÃO catalogados no map:      ${summary.occurrences_unknown}  ${summary.occurrences_unknown ? JSON.stringify(summary.unknown_names) : ''}`);
console.log(`  Subpath inexistente no destino:     ${pathMissing}  (nome corrige, mas o path interno precisa de revisão)\n`);
if (!WRITE) console.log('  Aplicação real: --write (FASE 2 — só após Wagner aprovar governance/ghost-rename-map.json)\n');
