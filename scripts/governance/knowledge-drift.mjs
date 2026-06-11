#!/usr/bin/env node
// knowledge-drift.mjs — primeira batida do "batimento" (ADR 0270 / sessão 2026-06-11).
//
// POR QUE EXISTE: o sistema de conhecimento é otimizado pra ESCREVER, não pra LER.
// Nenhum mecanismo media a DERIVADA (está ficando melhor ou pior de usar com o tempo?).
// Este script torna o drift VISÍVEL e MEDIDO — o "loop fechado por métrica" (Constituição
// v2, Princípio 4) aplicado a conhecimento. É a coisa que faz o apodrecimento gritar
// em vez de mentir 73/100 (caso SRS/MemCofre, sessão 2026-06-11).
//
// MEDE, por módulo em memory/requisitos/<Mod>/:
//   - read_path_hops : quantos docs se abre pra saber "a verdade atual" (meta: 1)
//   - porta          : tem BRIEFING.md? é auto-contida ou um índice de links?
//   - identity_drift : os docs citam Modules/<X>/ que NÃO existe no disco? (pegou o MemCofre)
//   - staleness      : a porta é mais velha (git) que o doc mais novo do módulo?
//
// NÃO recomenda ADICIONAR — toda nota ruim aponta pra DESTILAR/FUNDIR/APAGAR.
// Uso:  node scripts/governance/knowledge-drift.mjs [--json]
// Node puro (fs + git via execSync). Sem deps, sem DB, sem PHP.

import { readdirSync, statSync, readFileSync, existsSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { join } from 'node:path';

const ROOT = process.cwd();
const REQ = join(ROOT, 'memory', 'requisitos');
const JSON_OUT = process.argv.includes('--json');

// thresholds (calibrados na sessão 2026-06-11 — Jana porta=27 links era "índice")
const LINKS_INDICE = 15;   // > isso, a "porta" é índice, não verdade auto-contida
const STALE_DAYS = 30;     // porta mais velha que o doc novo por > isso = stale

function allMd(dir) {
  const out = [];
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    const p = join(dir, e.name);
    if (e.isDirectory()) out.push(...allMd(p));
    else if (e.name.endsWith('.md')) out.push(p);
  }
  return out;
}

function gitDate(file) {
  try {
    return execSync(`git log -1 --format=%cs -- "${file}"`, { cwd: ROOT, stdio: ['ignore', 'pipe', 'ignore'] })
      .toString().trim() || null;
  } catch { return null; }
}

const TRUTH_RE = /^(SPEC|README|ARCHITECTURE|BRIEFING|CAPTERRA.*|CAPTERRA-INVENTARIO|AUDIT.*|AUDITORIA.*)\.md$/i;
const MOD_REF_RE = /Modules\/([A-Z][A-Za-z0-9]+)/g;

const rows = [];
for (const mod of readdirSync(REQ, { withFileTypes: true })) {
  if (!mod.isDirectory()) continue;
  const dir = join(REQ, mod.name);
  const docs = allMd(dir);
  if (docs.length < 2) continue;

  const briefing = join(dir, 'BRIEFING.md');
  const hasDoor = existsSync(briefing);

  // porta: auto-contida ou índice?
  let lines = 0, links = 0, indice = false;
  if (hasDoor) {
    const txt = readFileSync(briefing, 'utf8');
    lines = txt.split('\n').length;
    links = (txt.match(/\]\(/g) || []).length;
    indice = links > LINKS_INDICE;
  }

  // docs concorrendo pela "verdade"
  const competing = docs.filter(d => TRUTH_RE.test(d.split('/').pop())).length;

  // read_path_hops
  const hops = !hasDoor ? docs.length : (indice ? 1 + competing : 1);

  // identity drift: docs citam Modules/<X> inexistente?
  const ghosts = new Set();
  for (const d of docs) {
    const txt = readFileSync(d, 'utf8');
    for (const m of txt.matchAll(MOD_REF_RE)) {
      if (!existsSync(join(ROOT, 'Modules', m[1]))) ghosts.add(m[1]);
    }
  }

  // staleness (git): porta mais velha que o doc mais novo?
  let stale = false, doorDate = null, newestDate = null;
  if (hasDoor) {
    doorDate = gitDate(briefing);
    for (const d of docs) {
      if (d === briefing) continue;
      const dt = gitDate(d);
      if (dt && (!newestDate || dt > newestDate)) newestDate = dt;
    }
    if (doorDate && newestDate) {
      const gap = (new Date(newestDate) - new Date(doorDate)) / 86400000;
      stale = gap > STALE_DAYS;
    }
  }

  // classificação (🔴 pior)
  let flag = '🟢';
  if (!hasDoor && docs.length >= 8) flag = '🔴';
  else if (ghosts.size > 0) flag = '🔴';
  else if (indice || stale) flag = '🟡';
  else if (!hasDoor) flag = '🟡';

  rows.push({ mod: mod.name, docs: docs.length, hops, door: hasDoor ? (indice ? 'índice' : 'ok') : 'NÃO',
    links, competing, ghosts: [...ghosts], stale, flag });
}

rows.sort((a, b) => b.hops - a.hops);

if (JSON_OUT) { console.log(JSON.stringify(rows, null, 2)); process.exit(0); }

const withDoor = rows.filter(r => r.door !== 'NÃO').length;
const selfContained = rows.filter(r => r.door === 'ok' && !r.stale).length;
const drift = rows.filter(r => r.ghosts.length).length;
const hopsArr = rows.map(r => r.hops).sort((a, b) => a - b);
const median = hopsArr[Math.floor(hopsArr.length / 2)];

console.log(`\n  BATIMENTO DO CONHECIMENTO — drift por módulo (${rows.length} módulos)\n`);
console.log(`  ${'MÓDULO'.padEnd(20)} ${'docs'.padStart(4)} ${'hops'.padStart(4)}  ${'porta'.padEnd(7)} drift/stale`);
console.log('  ' + '─'.repeat(64));
for (const r of rows) {
  const d = [r.ghosts.length ? `👻 cita Modules/${r.ghosts.join(',')} inexistente` : '', r.stale ? 'stale' : ''].filter(Boolean).join(' · ');
  console.log(`  ${r.flag} ${r.mod.padEnd(18)} ${String(r.docs).padStart(4)} ${String(r.hops).padStart(4)}  ${r.door.padEnd(7)} ${d}`);
}
console.log('  ' + '─'.repeat(64));
console.log(`\n  Cobertura de porta:        ${withDoor}/${rows.length} (${Math.round(100*withDoor/rows.length)}%)`);
console.log(`  Portas auto-contidas:      ${selfContained}/${rows.length} (${Math.round(100*selfContained/rows.length)}%)`);
console.log(`  read_path_hops mediano:    ${median}  (meta: 1)`);
console.log(`  Módulos com identity-drift:${String(drift).padStart(3)}  (docs citam Modules/X inexistente)`);
console.log(`\n  Toda linha 🔴/🟡 = recomendação SUBTRATIVA: destilar/fundir/apagar — nunca adicionar.\n`);
