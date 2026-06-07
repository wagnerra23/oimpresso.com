#!/usr/bin/env node
// @ts-check
/**
 * adr-supersede.mjs — supersessão ATÔMICA de ADR (modelo adr-tools/pyadr, ADR 0258).
 *
 * Estado-da-arte: supersessão é UM comando que, de uma vez, marca a ADR antiga
 * (status: superseded · lifecycle: substituido · superseded_by: [nova]) E garante
 * que a nova aponta supersedes: [antiga]. O oimpresso fazia isso em 2 lugares na
 * mão → divergia ("rebaixei e voltou"). Aqui é 1 transação só (atômica).
 *
 * SÓ toca frontmatter (não o corpo = append-only intacto). O PR que rodar --write
 * precisa do label `adr-metadata-normalization` (exceção do gate, ADR 0257).
 *
 * Uso:
 *   node scripts/governance/adr-supersede.mjs --new 0258 --old 0028          (dry-run)
 *   node scripts/governance/adr-supersede.mjs --new 0258 --old 0028 --write  (aplica)
 *   (aceita número NNNN se único, ou slug completo se o número colidir)
 *
 * Refs: ADR 0258 (processo estado-da-arte) · 0257 (status/lifecycle + exceção gate) · 0180 (colisões)
 */
import { readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = process.cwd();
const DIR = 'memory/decisions';
const WRITE = process.argv.includes('--write');
const arg = (k) => { const i = process.argv.indexOf(k); return i !== -1 ? process.argv[i + 1] : ''; };
const newRef = arg('--new');
const oldRef = arg('--old');

if (!newRef || !oldRef) {
  console.error('Uso: adr-supersede.mjs --new <num|slug> --old <num|slug> [--write]');
  process.exit(2);
}

const files = readdirSync(join(ROOT, DIR)).filter((f) => /^\d{4}-.+\.md$/.test(f));
function resolve(ref) {
  if (/^\d{4}-/.test(ref)) { const f = `${ref}.md`; return files.includes(f) ? [f] : []; }
  const num = ref.padStart(4, '0');
  return files.filter((f) => f.startsWith(`${num}-`));
}
function pick(ref, label) {
  const hits = resolve(ref);
  if (hits.length === 0) { console.error(`✗ ${label} "${ref}" não encontrada.`); process.exit(1); }
  if (hits.length > 1) { console.error(`✗ ${label} "${ref}" colide (${hits.length}). Use o slug completo:\n  ${hits.join('\n  ')}`); process.exit(1); }
  return hits[0];
}
const oldFile = pick(oldRef, 'ADR antiga (--old)');
const newFile = pick(newRef, 'ADR nova (--new)');
const oldNum = oldFile.slice(0, 4), newNum = newFile.slice(0, 4);

/** Aplica edits no frontmatter (entre os dois ---). Retorna {text, diffs}. */
function patchFrontmatter(file, edits) {
  const path = join(ROOT, DIR, file);
  const txt = readFileSync(path, 'utf8');
  if (!txt.startsWith('---')) { console.error(`✗ ${file} não tem YAML frontmatter (formato-tabela legado) — edição manual.`); process.exit(1); }
  const end = txt.indexOf('\n---', 3);
  let fm = txt.slice(0, end), body = txt.slice(end);
  const diffs = [];
  for (const [key, value] of Object.entries(edits)) {
    const re = new RegExp(`^${key}:.*$`, 'm');
    if (re.test(fm)) {
      const cur = (fm.match(re) || [])[0];
      if (cur !== `${key}: ${value}`) { fm = fm.replace(re, `${key}: ${value}`); diffs.push(`${key}: ${cur.replace(key + ':', '').trim()} → ${value}`); }
    } else {
      // insere após a linha lifecycle: (ou status:)
      const anchor = fm.match(/^lifecycle:.*$/m) || fm.match(/^status:.*$/m);
      if (anchor) { fm = fm.replace(anchor[0], `${anchor[0]}\n${key}: ${value}`); diffs.push(`+ ${key}: ${value}`); }
    }
  }
  return { path, text: fm + body, diffs };
}

// ANTIGA: rebaixa + linka (a parte que "não pegava"). Escrevemos isto.
const oldPatch = patchFrontmatter(oldFile, { status: 'superseded', lifecycle: 'substituido', superseded_by: `[${newNum}]` });

// NOVA: NÃO auto-editamos o `supersedes` (é lista — clobber perderia outros números,
// ex 0048 supersedes [0032,0035]). Só verificamos e avisamos.
const newTxt = readFileSync(join(ROOT, DIR, newFile), 'utf8');
const newFm = newTxt.slice(0, newTxt.indexOf('\n---', 3) === -1 ? undefined : newTxt.indexOf('\n---', 3));
const newHasSupersedes = new RegExp(`supersedes:[^\\n]*\\b${oldNum}\\b|supersedes:[\\s\\S]*?-[^\\n]*\\b${oldNum}\\b`).test(newFm);

console.log(`\n🔗 supersede — ${newNum} supersede ${oldNum}\n`);
console.log(`ANTIGA ${oldFile} (será editada):`); oldPatch.diffs.forEach((d) => console.log(`   ${d}`)); if (!oldPatch.diffs.length) console.log('   (já estava rebaixada — nada a fazer)');
console.log(`NOVA   ${newFile}:`); console.log(newHasSupersedes ? `   ✓ já aponta supersedes [...${oldNum}...]` : `   ⚠️ NÃO aponta supersedes: [${oldNum}] — adicione À MÃO (lista; não auto-edito pra não clobberar outros números)`);

if (WRITE) {
  writeFileSync(oldPatch.path, oldPatch.text);
  console.log(`\n✓ ANTIGA rebaixada (atômico no que importa). ${newHasSupersedes ? '' : 'Adicione supersedes na NOVA à mão. '}Abra PR com label 'adr-metadata-normalization' (ADR 0257) pra passar o gate.`);
} else {
  console.log(`\n[dry-run] rode com --write pra aplicar. PR precisa do label 'adr-metadata-normalization'.`);
}
