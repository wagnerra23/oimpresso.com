#!/usr/bin/env node
// onboarding-paths-check.mjs — a CAMADA DETERMINÍSTICA do canário de onboarding.
//
// POR QUE EXISTE (Wagner 2026-07-12): a NOTA do juiz-IA é ruidosa (duas rodadas no
// MESMO alvo deram 88 e 78 — juízes diferentes têm rigor diferente). Um placar que
// oscila ±10 não é máquina confiável. Os ACHADOS concretos (paths quebrados), sim.
// Então este script decide o veredito ANTES do juiz, MECANICAMENTE: todo path que o
// COMECE-AQUI.md e o PAINEL-SISTEMA.md citam (link markdown OU `code` inline de repo)
// tem que existir no disco. Path morto = FAIL determinístico, sem depender do juiz.
//
// REUSA deadLinks() do system-map.mjs — MESMA lógica que já guarda a geração do painel
// (skip de <templates>, de `code` com espaço, de externos http/mailto). Fonte única.
//
// Uso (na raiz do repo):
//   node scripts/governance/onboarding-paths-check.mjs           # humano
//   node scripts/governance/onboarding-paths-check.mjs --json     # JSON (pro canário)
// Exit 0 = todos os paths vivos · Exit 1 = ≥1 path morto (veredito FAIL determinístico).

import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { deadLinks } from './system-map.mjs';

const ROOT = process.cwd();
const JSON_MODE = process.argv.includes('--json');
// as duas portas de entrada que o canário testa (ambas GERADAS por system-map.mjs)
const DOCS = ['memory/reference/COMECE-AQUI.md', 'memory/reference/PAINEL-SISTEMA.md'];

const dead = [];
for (const rel of DOCS) {
  let md;
  try {
    md = readFileSync(join(ROOT, rel), 'utf8');
  } catch {
    dead.push({ doc: rel, path: '(o próprio doc não existe)', tipo: 'doc-ausente' });
    continue;
  }
  for (const d of deadLinks(md, join(ROOT, rel))) {
    dead.push({ doc: rel, path: d, tipo: d.startsWith('(inline)') ? 'inline-repo' : 'markdown-link' });
  }
}

const ok = dead.length === 0;
if (JSON_MODE) {
  process.stdout.write(JSON.stringify({ ok, docs: DOCS, dead }, null, 2) + '\n');
} else if (ok) {
  console.log(`[onboarding-paths] OK — ${DOCS.length} docs, 0 paths mortos.`);
} else {
  console.error(`[onboarding-paths] FAIL determinístico — ${dead.length} path(s) morto(s):`);
  dead.forEach((f) => console.error(`  ✗ ${f.doc}: ${f.path}  [${f.tipo}]`));
}
process.exit(ok ? 0 : 1);
