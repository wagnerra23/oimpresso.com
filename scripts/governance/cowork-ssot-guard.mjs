#!/usr/bin/env node
// cowork-ssot-guard.mjs — MÁQUINA de fonte única do protótipo de design.
// Garante que `prototipo-ui/cowork/` é a ÚNICA fonte de design (BUILD-ONLY) e que
// não existe protótipo/dupla-fonte fora dela. Encaixado em design-memory-gate.yml
// (sem workflow novo — respeita o teto anti-proliferação, ADR 0298).
// Origem: ADR-proposta 2026-06-23-prototipo-ssot-unico-com-historico.
//
// Falha (exit 1) se:
//   R1  qualquer .md dentro de cowork/        (knowledge = canon: memory/ + prototipo-ui root, não aqui)
//   R2  bundle datado prototipo-ui/cowork-*/  (SSOT é UM cowork/, sem datados = sem 2ª fonte)
//   R3  prototipo-ui/prototipos/<dir> fora do allowlist transitório
//
// Uso: node scripts/governance/cowork-ssot-guard.mjs [--json]
import { readdirSync, existsSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = process.cwd();
const COWORK = 'prototipo-ui/cowork';

// Telas cujo design AINDA não existe no export → só sobrevive o recorte antigo.
// O DESIGN deve exportá-las pro cowork/ (ver FRESCOR). Migrou → REMOVER daqui (meta: allowlist = 0).
// 'perfil' = baseline da Fase 0 do protocolo aplicar-prototipo (2026-06-24, handoff ComVis);
// transitório como os outros — sai daqui quando o design exportar pro cowork/.
const PROTOTIPOS_ALLOWLIST = new Set(['compras-grade-matrix', 'inventario-migracao', 'perfil']);

const errors = [];

function walk(dir) {
  const abs = join(ROOT, dir);
  if (!existsSync(abs)) return [];
  const out = [];
  for (const e of readdirSync(abs, { withFileTypes: true })) {
    const rel = `${dir}/${e.name}`;
    if (e.isDirectory()) out.push(...walk(rel));
    else out.push(rel);
  }
  return out;
}

// R1 — zero .md em cowork/ (build-only)
for (const f of walk(COWORK)) {
  if (f.toLowerCase().endsWith('.md')) errors.push(`R1 .md em cowork/ (mova pro canon — memory/ ou prototipo-ui root): ${f}`);
}

// R2 — sem bundles datados cowork-*
const pu = join(ROOT, 'prototipo-ui');
if (existsSync(pu)) {
  for (const e of readdirSync(pu, { withFileTypes: true })) {
    if (e.isDirectory() && /^cowork-/.test(e.name)) errors.push(`R2 bundle datado proibido (SSOT é cowork/): prototipo-ui/${e.name}`);
  }
}

// R3 — prototipos/ só allowlist transitório
const proto = join(ROOT, 'prototipo-ui/prototipos');
if (existsSync(proto)) {
  for (const e of readdirSync(proto, { withFileTypes: true })) {
    if (e.isDirectory() && !PROTOTIPOS_ALLOWLIST.has(e.name)) errors.push(`R3 protótipo fora do cowork/ (mova o build pro cowork/): prototipo-ui/prototipos/${e.name}`);
  }
}

if (process.argv.includes('--json')) {
  console.log(JSON.stringify({ ok: errors.length === 0, errors, allowlist: [...PROTOTIPOS_ALLOWLIST] }, null, 2));
} else if (errors.length) {
  console.error(`✗ cowork-ssot-guard: ${errors.length} violação(ões) de fonte única:`);
  for (const e of errors) console.error('  - ' + e);
  console.error('\nRegra: prototipo-ui/cowork/ = ÚNICA fonte de design (BUILD-ONLY). Conhecimento = canon (memory/ + prototipo-ui root).');
  console.error('ADR: memory/decisions/proposals/2026-06-23-prototipo-ssot-unico-com-historico.md');
  if (PROTOTIPOS_ALLOWLIST.size) console.error(`Allowlist transitório (design deve exportar pro cowork/): ${[...PROTOTIPOS_ALLOWLIST].join(', ')}`);
} else {
  console.log('✓ cowork-ssot-guard: fonte única OK (cowork/ build-only · sem bundles datados · prototipos só allowlist transitório).');
}
process.exit(errors.length ? 1 : 0);
