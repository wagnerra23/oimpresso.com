#!/usr/bin/env node
// @ts-check
/**
 * doc-auto-relink.mjs — AUTO-RELIGADOR: dado um doc que MOVEU (A→B), religa os links.
 *
 * PR2 do design `proposals/2026-07-23-referencia-id-estavel-doc-links.md`. Diferente da
 * máquina de realocação (que DECIDE o destino e faz `git mv`), aqui o arquivo JÁ moveu —
 * só falta consertar quem apontava pro path antigo. É a cura do link-rot doc↔doc:
 *   - referrer MUTÁVEL           → reescreve o link relativo nativo A→B (contexto-consciente)
 *   - referrer NÃO-RELINKÁVEL    → deixa TOMBSTONE stub no path antigo A (append-only/gate-guarded)
 *
 * REUSA as primitivas já existentes (zero reimplementação):
 *   - collectIncomingReferences / searchReplaceFor / isGateGuarded / resolveReference  (adversary)
 *   - renderTombstone / replaceExact                                                    (executor)
 *   - resolveId / buildIndex                                                            (doc-id-index)
 *
 * Detecção de move: compara o índice COMMITADO (governance/doc-id-index.json) vs o corpus
 * atual. Um `id` STAMPED cujo path mudou = moveu — sobrevive inclusive a rename de PASTA
 * (Copiloto→Jana), que é a maior fonte de link morto. (id DERIVADO muda no rename de filename;
 * esse caso é coberto por git-rename na máquina de realocação, não aqui.)
 *
 * Uso:
 *   node scripts/governance/doc-auto-relink.mjs --detect            (dry-run: moves + plano de religação)
 *   node scripts/governance/doc-auto-relink.mjs --move A.md B.md    (plano de UM move explícito)
 *   node scripts/governance/doc-auto-relink.mjs --detect --apply    (aplica: reescreve mutáveis + tombstone)
 *   node scripts/governance/doc-auto-relink.mjs --selftest
 *   ... [--root <dir>]
 *
 * Refs: design 2026-07-23 · document-relocation-{adversary,executor}.mjs · doc-id-index.mjs.
 */
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, writeFileSync, mkdtempSync, mkdirSync, rmSync } from 'node:fs';
import { join, posix, resolve } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import {
  collectIncomingReferences, isGateGuarded, searchReplaceFor,
} from './document-relocation-adversary.mjs';
import { renderTombstone, replaceExact } from './document-relocation-executor.mjs';
import { buildIndex } from './doc-id-index.mjs';

const INDEX_FILE = 'governance/doc-id-index.json';
const posixify = (p) => String(p).replaceAll('\\', '/');
const git = (root, args) => execFileSync('git', ['-C', root, ...args], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 }).trim();

// Referrer que NÃO pode ser reescrito: append-only (Tier 0) OU sob gate diff-aware.
// Mesma regra da máquina (isGateGuarded importado; append-only local). O stub serve os dois.
export function isUnrelinkableReferrer(path) {
  return /^memory\/(?:decisions|sessions|handoffs)\//.test(path) || isGateGuarded(path);
}

// Destino do link reescrito: markdown-link é RELATIVO ao dir do referrer; code-span/literal, RAIZ.
// (Réplica fiel do destination() da máquina de realocação — fonte de comportamento única.)
function destination(fromFile, target, kind, fragment = '') {
  let path = target;
  if (kind === 'markdown-link') {
    path = posix.relative(posix.dirname(fromFile), target).replaceAll('\\', '/');
    if (!path.startsWith('.')) path = `./${path}`;
  }
  return `${path}${fragment ? `#${fragment}` : ''}`;
}

/**
 * Plano de religação de UM move A→B: lista de reescritas (referrers mutáveis) + se precisa tombstone.
 * NÃO escreve nada. Reusa collectIncomingReferences pra achar quem aponta pra A.
 */
export function planForMove(root, from, to, allFiles) {
  const src = posixify(from);
  const dst = posixify(to);
  const markdown = allFiles.filter((p) => p.toLowerCase().endsWith('.md'));
  const incoming = collectIncomingReferences(root, markdown, [src], allFiles).get(src.toLowerCase()) || [];
  const rewrites = [];
  const tombstoneReferrers = [];
  for (const ref of incoming) {
    if (isUnrelinkableReferrer(ref.file)) { tombstoneReferrers.push(ref.file); continue; }
    const newRaw = destination(ref.file, dst, ref.kind, ref.fragment);
    rewrites.push({ file: ref.file, kind: ref.kind, from: ref.raw, to: newRaw });
  }
  return {
    from: src, to: dst,
    rewrites,
    tombstone: [...new Set(tombstoneReferrers)].sort(),
    needs_tombstone: tombstoneReferrers.length > 0,
  };
}

/** Detecta moves comparando o índice commitado (id→path) vs o corpus atual. Só ids STAMPED. */
export function detectMoves(root) {
  const committedPath = join(root, INDEX_FILE);
  if (!existsSync(committedPath)) return { error: `índice não commitado ainda: ${INDEX_FILE} (rode doc-id-index --write e commite)`, moves: [] };
  let committed;
  try { committed = JSON.parse(readFileSync(committedPath, 'utf8')).ids || {}; }
  catch (e) { return { error: `índice ilegível: ${e.message}`, moves: [] }; }
  const current = buildIndex(root).ids;
  const moves = [];
  for (const [id, oldPath] of Object.entries(committed)) {
    const newPath = current[id];
    if (newPath && posixify(newPath) !== posixify(oldPath)) moves.push({ id, from: oldPath, to: newPath });
  }
  return { moves: moves.sort((a, b) => a.id.localeCompare(b.id)) };
}

/** Aplica UM plano de religação. Escreve mutáveis + tombstone. Assume o arquivo JÁ movido (from não existe). */
export function applyPlan(root, plan) {
  const applied = [];
  // Reescreve referrers mutáveis (contexto-consciente: markdown-link vs code-span não se pisam).
  const byFile = new Map();
  for (const rw of plan.rewrites) {
    if (!byFile.has(rw.file)) byFile.set(rw.file, []);
    byFile.get(rw.file).push(rw);
  }
  for (const [file, rws] of byFile) {
    const abs = join(root, file);
    let text = readFileSync(abs, 'utf8');
    for (const rw of rws) {
      const [search, replace] = searchReplaceFor(rw.kind, rw.from, rw.to);
      const r = replaceExact(text, search, replace); // lança se não achar (drift)
      text = r.text;
      applied.push({ file, from: rw.from, to: rw.to, count: r.count });
    }
    writeFileSync(abs, text, 'utf8');
  }
  // Tombstone no path antigo, se houver referrer não-relinkável. Nunca sobrescreve arquivo vivo.
  if (plan.needs_tombstone) {
    const oldAbs = join(root, plan.from);
    if (existsSync(oldAbs)) throw new Error(`recusado: ${plan.from} ainda existe — o auto-religador só tombstoneia path JÁ movido`);
    writeFileSync(oldAbs, renderTombstone({ source: plan.from, target: plan.to }), 'utf8');
    applied.push({ tombstone: plan.from, moved_to: plan.to });
  }
  return applied;
}

function trackedFiles(root) {
  return git(root, ['ls-files', '-z']).split('\0').filter(Boolean).map(posixify);
}

function runSelftest() {
  const cases = [];
  const check = (name, ok, ev) => cases.push({ name, ok: Boolean(ok), ev });

  const fixture = mkdtempSync(join(tmpdir(), 'oimpresso-relink-'));
  try {
    git(fixture, ['init', '-q']); git(fixture, ['config', 'user.email', 't@t.test']); git(fixture, ['config', 'user.name', 'T']);
    mkdirSync(join(fixture, 'memory/reference'), { recursive: true });
    mkdirSync(join(fixture, 'memory/requisitos/Jana'), { recursive: true });
    mkdirSync(join(fixture, 'memory/decisions'), { recursive: true });
    // B = doc que vai mover. Referrers: README (mutável), um ADR (append-only), um SPEC (gate-guarded).
    writeFileSync(join(fixture, 'memory/requisitos/Copiloto-doc.md'), '# Doc\n');
    writeFileSync(join(fixture, 'README.md'), '[doc](memory/requisitos/Copiloto-doc.md)\n');
    writeFileSync(join(fixture, 'memory/decisions/0001-cita.md'), '[doc](../requisitos/Copiloto-doc.md)\n');
    writeFileSync(join(fixture, 'memory/requisitos/Jana/SPEC.md'), '[doc](../Copiloto-doc.md)\n');
    git(fixture, ['add', '.']); git(fixture, ['commit', '-q', '-m', 'fix']);
    const files0 = trackedFiles(fixture);

    // Plano ANTES de mover (referrers todos apontam pra path antigo).
    const plan = planForMove(fixture, 'memory/requisitos/Copiloto-doc.md', 'memory/requisitos/Jana/doc.md', files0);
    check('plano acha o referrer mutável (README)', plan.rewrites.some((r) => r.file === 'README.md'), plan);
    check('README relinka pro path novo (./ prefix é convenção da máquina)', plan.rewrites.find((r) => r.file === 'README.md')?.to.endsWith('memory/requisitos/Jana/doc.md'), plan);
    check('ADR append-only NÃO vira rewrite (vai pro tombstone)', !plan.rewrites.some((r) => r.file === 'memory/decisions/0001-cita.md') && plan.tombstone.includes('memory/decisions/0001-cita.md'), plan);
    check('SPEC gate-guarded NÃO vira rewrite (vai pro tombstone)', !plan.rewrites.some((r) => r.file.endsWith('Jana/SPEC.md')) && plan.tombstone.includes('memory/requisitos/Jana/SPEC.md'), plan);
    check('needs_tombstone quando há referrer não-relinkável', plan.needs_tombstone === true, plan);

    // Simula o move real (git mv) e aplica a religação.
    git(fixture, ['mv', 'memory/requisitos/Copiloto-doc.md', 'memory/requisitos/Jana/doc.md']);
    applyPlan(fixture, plan);
    const readmeAfter = readFileSync(join(fixture, 'README.md'), 'utf8');
    check('APLICADO: README aponta pro novo path', readmeAfter.includes('memory/requisitos/Jana/doc.md'), readmeAfter);
    const stub = existsSync(join(fixture, 'memory/requisitos/Copiloto-doc.md')) ? readFileSync(join(fixture, 'memory/requisitos/Copiloto-doc.md'), 'utf8') : '';
    check('APLICADO: tombstone no path antigo', /^tombstone:\s*true$/m.test(stub) && stub.includes('memory/requisitos/Jana/doc.md'), stub);
    const adrAfter = readFileSync(join(fixture, 'memory/decisions/0001-cita.md'), 'utf8');
    check('APLICADO: ADR append-only intacto (resolve pelo stub)', adrAfter === '[doc](../requisitos/Copiloto-doc.md)\n', adrAfter);

    // detectMoves via índice commitado: id stamped sobrevive ao rename de pasta.
    const fx2 = mkdtempSync(join(tmpdir(), 'oimpresso-relink2-'));
    try {
      mkdirSync(join(fx2, 'memory/requisitos/Copiloto'), { recursive: true });
      mkdirSync(join(fx2, 'governance'), { recursive: true });
      writeFileSync(join(fx2, 'memory/requisitos/Copiloto/x.md'), '---\nid: jana-x-canon\n---\n# X\n');
      // índice "commitado" aponta pro path antigo; o corpus atual já moveu.
      writeFileSync(join(fx2, 'governance/doc-id-index.json'), JSON.stringify({ ids: { 'jana-x-canon': 'memory/requisitos/Copiloto/x.md' } }));
      // move real do arquivo pro path novo:
      mkdirSync(join(fx2, 'memory/requisitos/Jana'), { recursive: true });
      writeFileSync(join(fx2, 'memory/requisitos/Jana/x.md'), '---\nid: jana-x-canon\n---\n# X\n');
      rmSync(join(fx2, 'memory/requisitos/Copiloto/x.md'));
      const det = detectMoves(fx2);
      check('detectMoves: id stamped rastreia rename de PASTA', det.moves.some((m) => m.id === 'jana-x-canon' && m.from.includes('Copiloto') && m.to.includes('Jana')), det);
    } finally { if (fx2.startsWith(tmpdir())) rmSync(fx2, { recursive: true, force: true }); }
  } finally {
    if (fixture.startsWith(tmpdir())) rmSync(fixture, { recursive: true, force: true });
  }

  for (const c of cases) console.log(`${c.ok ? '[OK]  ' : '[FALHA]'} ${c.name}`);
  const failed = cases.filter((c) => !c.ok);
  console.log(`\n${failed.length ? 'SELFTEST FALHOU' : 'SELFTEST OK'} - ${cases.length - failed.length}/${cases.length}`);
  if (failed.length) { for (const f of failed) console.log(JSON.stringify(f.ev, null, 2)); process.exit(1); }
}

function main() {
  const args = process.argv.slice(2);
  if (args.includes('--selftest')) return runSelftest();
  const rootIdx = args.indexOf('--root');
  const root = resolve(rootIdx >= 0 ? args[rootIdx + 1] : process.cwd());
  const apply = args.includes('--apply');
  const files = trackedFiles(root);

  const moveIdx = args.indexOf('--move');
  let moves;
  if (moveIdx >= 0) {
    moves = [{ id: '(explícito)', from: posixify(args[moveIdx + 1]), to: posixify(args[moveIdx + 2]) }];
  } else if (args.includes('--detect')) {
    const det = detectMoves(root);
    if (det.error) { console.error(det.error); process.exit(2); }
    moves = det.moves;
  } else {
    console.error('uso: --detect [--apply] | --move <A.md> <B.md> [--apply] | --selftest');
    process.exit(2);
  }

  if (!moves.length) { console.log('nenhum move detectado — nada a religar.'); return; }
  for (const mv of moves) {
    const plan = planForMove(root, mv.from, mv.to, files);
    console.log(`\n${mv.id}: ${mv.from} → ${mv.to}`);
    console.log(`  religar: ${plan.rewrites.length} referrer(s) mutável(is)${plan.needs_tombstone ? ` · tombstone (${plan.tombstone.length} não-relinkável)` : ''}`);
    for (const rw of plan.rewrites) console.log(`    ${rw.file}  [${rw.kind}]  ${rw.from} → ${rw.to}`);
    for (const t of plan.tombstone) console.log(`    (stub) ← ${t}`);
    if (apply) {
      const done = applyPlan(root, plan);
      console.log(`  APLICADO: ${done.length} operação(ões).`);
    }
  }
  if (!apply) console.log('\n(dry-run — nada escrito. Use --apply pra religar.)');
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try { main(); } catch (error) { console.error(`AUTO-RELINK ERROR: ${error.message}`); process.exit(2); }
}
