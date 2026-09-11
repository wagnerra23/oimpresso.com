#!/usr/bin/env node
// cowork-ssot-guard.mjs — trava a organização física do protótipo de design.
//
// Estrutura aceita:
//   prototipo-ui/
//     cowork/{Wagner,Felipe}/   fontes de tela, separadas pela conta de origem
//     design-system/            espelho canônico do DS
//
// Máquinas ficam em scripts/design/, contratos/alvos em governance/design/, testes em
// tests/Design/ e documentação em memory/reference/prototipo-ui/. O Git guarda histórico;
// nenhuma segunda cópia física é aceita. Handoffs são a única documentação admitida dentro
// do build e vivem exclusivamente em cowork/<dono>/handoffs/.
//
// Uso: node scripts/governance/cowork-ssot-guard.mjs [--json]
import { readdirSync, existsSync, readFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { join } from 'node:path';

const ROOT = process.cwd();
const COWORK = 'prototipo-ui/cowork';
const DONOS = new Set(['Wagner', 'Felipe']);

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

// R1 — a raiz do protótipo contém somente as duas áreas autorizadas.
const pu = join(ROOT, 'prototipo-ui');
if (existsSync(pu)) {
  for (const e of readdirSync(pu, { withFileTypes: true })) {
    if (!e.isDirectory() || !['cowork', 'design-system'].includes(e.name)) {
      errors.push(`R1 item proibido na raiz de prototipo-ui/: prototipo-ui/${e.name}`);
    }
  }
}

// R2 — cowork/ contém somente os dois donos e nenhum arquivo solto.
const coworkAbs = join(ROOT, COWORK);
if (existsSync(coworkAbs)) {
  for (const e of readdirSync(coworkAbs, { withFileTypes: true })) {
    if (!e.isDirectory() || !DONOS.has(e.name)) {
      errors.push(`R2 item proibido em cowork/ (donos aceitos: Wagner, Felipe): ${COWORK}/${e.name}`);
    }
  }
}

// R3 — build-only, exceto o canal explícito de handoff de cada dono.
for (const f of walk(COWORK)) {
  if (f.toLowerCase().endsWith('.md') && !/^prototipo-ui\/cowork\/(Wagner|Felipe)\/handoffs\/[^/]+\.md$/i.test(f)) {
    errors.push(`R3 documentação fora de cowork/<dono>/handoffs/: ${f}`);
  }
}

// R4 — zero conteúdo duplicado em disco dentro de prototipo-ui/, inclusive caches ignorados.
// O Git já preserva o histórico. Uma segunda cópia física no working tree cria dois donos,
// âncoras ambíguas e importações que parecem mover arquivos. A comparação é pelos bytes.
const byHash = new Map();
for (const rel of walk('prototipo-ui')) {
  const hash = createHash('sha256').update(readFileSync(join(ROOT, rel))).digest('hex');
  const paths = byHash.get(hash) || [];
  paths.push(rel);
  byHash.set(hash, paths);
}
for (const paths of byHash.values()) {
  if (paths.length > 1) errors.push(`R4 conteúdo duplicado (mantenha um único dono): ${paths.join(' = ')}`);
}

if (process.argv.includes('--json')) {
  console.log(JSON.stringify({
    ok: errors.length === 0,
    errors,
    donos: [...DONOS],
  }, null, 2));
} else if (errors.length) {
  console.error(`✗ cowork-ssot-guard: ${errors.length} violação(ões) de fonte única:`);
  for (const e of errors) console.error('  - ' + e);
  console.error('\nRegra: prototipo-ui/ só contém cowork/{Wagner,Felipe}/ e design-system/; histórico vive no Git.');
} else {
  console.log('✓ cowork-ssot-guard: estrutura mínima e fonte única OK (Wagner + Felipe + design-system).');
}
process.exit(errors.length ? 1 : 0);
