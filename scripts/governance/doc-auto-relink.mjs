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
 * ── MODO --orfaos (2026-08-15): a dívida que o --detect NÃO alcança ────────────
 * O `--detect` só enxerga doc que moveu E tem `id` STAMPED no índice commitado. A dívida
 * REAL de link morto é maior e mais velha: o `deadlink-gate` (required) carrega 1.082 links
 * mortos em 568 arquivos congelados em `governance/deadlink-baseline.json` — o ratchet impede
 * PIORAR, e ninguém paga. Medição 2026-08-15: 55% desses têm o basename resolvendo pra UM
 * único arquivo real (o caso clássico `decisions/proposals/NNNN.md` → a proposta virou ADR
 * aceita e mudou de pasta), e 72,5% da dívida mora em área MUTÁVEL.
 *
 * Este modo fecha o elo detectar→consertar que faltava: o CI já roda `--detect` dry-run
 * ("nunca --apply em CI", e correto), mas nada nunca propôs o conserto do resíduo.
 *
 * As 5 travas (o que o torna seguro o bastante pra existir):
 *   1. referrer não-relinkável   → PULA (reusa isUnrelinkableReferrer: decisions/sessions/
 *                                  handoffs append-only + gate-guarded). Nunca edita ADR.
 *   2. alvo AMBÍGUO (N basenames)→ PULA. Adivinhar destino é a doença do guard sintático.
 *   3. alvo INEXISTENTE          → PULA (link pra doc deletado de verdade não tem conserto).
 *   4. reescrita ANCORADA        → replaceExact (lança se o texto driftou), nunca replace solto
 *                                  — a lápide §5 2026-08-02 é exatamente esse erro.
 *   5. cap explícito `--max N`   → sem big-bang (§5 2026-07-12: backfill em massa de legado).
 * Dry-run é o default; `--apply` é sempre explícito.
 *
 * Uso:
 *   node scripts/governance/doc-auto-relink.mjs --detect            (dry-run: moves + plano de religação)
 *   node scripts/governance/doc-auto-relink.mjs --move A.md B.md    (plano de UM move explícito)
 *   node scripts/governance/doc-auto-relink.mjs --detect --apply    (aplica: reescreve mutáveis + tombstone)
 *   node scripts/governance/doc-auto-relink.mjs --orfaos            (dry-run: link morto religável por alvo único)
 *   node scripts/governance/doc-auto-relink.mjs --orfaos --apply --max 20   (aplica, com teto)
 *   node scripts/governance/doc-auto-relink.mjs --orfaos --escopo memory/requisitos/  (recorte)
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

// ── MODO --orfaos ──────────────────────────────────────────────────────────────
// Link markdown interno. Fragmento (#ancora) preservado; http/mailto/âncora-pura fora.
const MD_LINK = /\[[^\]]*\]\(([^)\s]+)\)/g;

/**
 * Propõe religação do link morto RESIDUAL: aquele cujo basename resolve pra UM único
 * arquivo real do repo. NÃO escreve nada. É o complemento do planForMove — lá o move é
 * conhecido (índice de ids); aqui só o sintoma é conhecido (o link não abre).
 *
 * Retorna { proposals, skipped } — `skipped` é o disclosure honesto (por que cada um ficou
 * de fora), porque instrumento que só mostra o que achou esconde o denominador.
 */
export function planOrphanRelinks(root, allFiles, { escopo = null, max = Infinity } = {}) {
  const byBase = new Map();
  for (const p of allFiles) {
    const b = posix.basename(p);
    if (!byBase.has(b)) byBase.set(b, []);
    byBase.get(b).push(p);
  }

  const proposals = [];
  const skipped = { nao_relinkavel: 0, ambiguo: 0, sem_alvo: 0, fora_escopo: 0, root_relative: 0 };
  const seen = new Set(); // dedupe (file, rawHref) — replaceExact já troca todas as ocorrências

  for (const file of allFiles) {
    if (!file.toLowerCase().endsWith('.md')) continue;
    if (escopo && !file.startsWith(escopo)) { skipped.fora_escopo++; continue; }
    const abs = join(root, file);
    if (!existsSync(abs)) continue;
    const text = readFileSync(abs, 'utf8');

    for (const m of text.matchAll(MD_LINK)) {
      const raw = m[1];
      if (/^(?:https?:|mailto:|#)/.test(raw)) continue;
      const [pathPart, fragment = ''] = raw.split('#');
      if (!pathPart) continue;
      // Vivo? nada a fazer. (resolve relativo ao dir do referrer — é a semântica do markdown.)
      if (existsSync(resolve(join(root, posix.dirname(file)), pathPart))) continue;

      // Trava 1: referrer append-only / gate-guarded nunca é reescrito.
      if (isUnrelinkableReferrer(file)) { skipped.nao_relinkavel++; continue; }

      const cands = byBase.get(posix.basename(pathPart)) || [];
      if (cands.length === 0) { skipped.sem_alvo++; continue; }   // trava 3
      if (cands.length > 1) { skipped.ambiguo++; continue; }      // trava 2
      // Trava 6 (medida 2026-08-15: 100 de 633 propostas = 15,8% de FP): o link já é o path
      // EXATO do alvo a partir da RAIZ do repo — convenção root-relative, usada de propósito
      // em `.claude/agents/*.md` e afins. Não resolve como relativo, mas não está quebrado;
      // reescrever pra `../../` seria trocar a convenção do autor por conta própria.
      if (pathPart === cands[0]) { skipped.root_relative++; continue; }

      const key = `${file} ${raw}`;
      if (seen.has(key)) continue;
      seen.add(key);

      const to = destination(file, cands[0], 'markdown-link', fragment);
      if (to === raw) continue; // já é o caminho certo (nada a reescrever)
      proposals.push({ file, kind: 'markdown-link', from: raw, to, target: cands[0] });
      if (proposals.length >= max) return { proposals, skipped, capped: true }; // trava 5
    }
  }
  return { proposals, skipped, capped: false };
}

/** Aplica propostas do --orfaos. Reescrita ANCORADA (trava 4): replaceExact lança em drift. */
export function applyOrphanRelinks(root, proposals) {
  const byFile = new Map();
  for (const p of proposals) {
    if (!byFile.has(p.file)) byFile.set(p.file, []);
    byFile.get(p.file).push(p);
  }
  const applied = [];
  for (const [file, list] of byFile) {
    const abs = join(root, file);
    let text = readFileSync(abs, 'utf8');
    for (const p of list) {
      const [search, replace] = searchReplaceFor(p.kind, p.from, p.to);
      const r = replaceExact(text, search, replace);
      text = r.text;
      applied.push({ file, from: p.from, to: p.to, count: r.count });
    }
    writeFileSync(abs, text, 'utf8');
  }
  return applied;
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

    // ── --orfaos: bite-test (morde o religável, SOLTA os 4 casos que não pode tocar) ──
    const fx3 = mkdtempSync(join(tmpdir(), 'oimpresso-orfaos-'));
    try {
      git(fx3, ['init', '-q']); git(fx3, ['config', 'user.email', 't@t.test']); git(fx3, ['config', 'user.name', 'T']);
      mkdirSync(join(fx3, 'memory/decisions'), { recursive: true });
      mkdirSync(join(fx3, 'memory/requisitos/Jana'), { recursive: true });
      mkdirSync(join(fx3, 'memory/reference'), { recursive: true });
      mkdirSync(join(fx3, 'dup/a'), { recursive: true }); mkdirSync(join(fx3, 'dup/b'), { recursive: true });

      // alvo real, único no repo:
      writeFileSync(join(fx3, 'memory/decisions/0320-aceita.md'), '# aceita\n');
      // alvo AMBÍGUO (mesmo basename em 2 lugares):
      writeFileSync(join(fx3, 'dup/a/dobrado.md'), '# a\n');
      writeFileSync(join(fx3, 'dup/b/dobrado.md'), '# b\n');
      // referrer MUTÁVEL com: (1) morto religável, (2) morto ambíguo, (3) morto sem alvo, (4) VIVO
      writeFileSync(join(fx3, 'memory/reference/guia.md'),
        '[a](../decisions/proposals/0320-aceita.md)\n'
        + '[b](../../dup/dobrado.md)\n'
        + '[c](../nunca-existiu.md)\n'
        + '[d](../decisions/0320-aceita.md)\n'
        + '[e](memory/decisions/0320-aceita.md)\n');
      // referrer APPEND-ONLY com o MESMO link morto religável — não pode ser tocado:
      writeFileSync(join(fx3, 'memory/decisions/0001-cita.md'), '[a](proposals/0320-aceita.md)\n');
      git(fx3, ['add', '.']); git(fx3, ['commit', '-q', '-m', 'fx']);

      const files3 = trackedFiles(fx3);
      const { proposals, skipped } = planOrphanRelinks(fx3, files3);

      check('--orfaos MORDE: link morto com alvo único vira proposta',
        proposals.some((p) => p.file === 'memory/reference/guia.md' && p.from.includes('proposals/0320-aceita.md') && p.target === 'memory/decisions/0320-aceita.md'), proposals);
      check('--orfaos SOLTA: alvo ambíguo (2 basenames) não vira proposta',
        !proposals.some((p) => p.from.includes('dobrado.md')) && skipped.ambiguo >= 1, { proposals, skipped });
      check('--orfaos SOLTA: alvo inexistente não vira proposta',
        !proposals.some((p) => p.from.includes('nunca-existiu')) && skipped.sem_alvo >= 1, { proposals, skipped });
      check('--orfaos SOLTA: link VIVO não vira proposta (controle negativo)',
        !proposals.some((p) => p.from === '../decisions/0320-aceita.md'), proposals);
      check('--orfaos SOLTA: referrer append-only (ADR) nunca vira proposta',
        !proposals.some((p) => p.file.startsWith('memory/decisions/')) && skipped.nao_relinkavel >= 1, { proposals, skipped });
      check('--orfaos SOLTA: link ROOT-RELATIVE válido não vira proposta (trava 6 — 15,8% de FP medido)',
        !proposals.some((p) => p.from === 'memory/decisions/0320-aceita.md') && skipped.root_relative >= 1, { proposals, skipped });

      // aplica e faz TESTE DE IDENTIDADE: só o link alvo muda; o resto do arquivo fica idêntico.
      const antes = readFileSync(join(fx3, 'memory/reference/guia.md'), 'utf8');
      applyOrphanRelinks(fx3, proposals);
      const depois = readFileSync(join(fx3, 'memory/reference/guia.md'), 'utf8');
      check('APLICADO: o link religado aponta pro alvo real', depois.includes('](../decisions/0320-aceita.md)'), depois);
      check('IDENTIDADE: só a linha do link religável mudou (as outras 4 intactas)',
        antes.split('\n').filter((l, i) => l !== depois.split('\n')[i]).length === 1, { antes, depois });
      const adr3 = readFileSync(join(fx3, 'memory/decisions/0001-cita.md'), 'utf8');
      check('APLICADO: ADR append-only byte-a-byte intacta', adr3 === '[a](proposals/0320-aceita.md)\n', adr3);

      // cap: --max limita o big-bang.
      const capped = planOrphanRelinks(fx3, files3, { max: 1 });
      check('--max N corta a leva (anti big-bang)', capped.proposals.length <= 1, capped);
    } finally { if (fx3.startsWith(tmpdir())) rmSync(fx3, { recursive: true, force: true }); }

    // ── --orfaos: casos de USO que o bite-test acima não cobria (2026-08-15) ──────
    // Buracos que a pergunta "como eu uso?" expôs: --escopo e --max entraram no CLI
    // sem caso próprio, e 3 formas de link que aparecem no corpus real (fragmento,
    // ocorrência repetida, code-span) nunca foram exercitadas.
    const fx4 = mkdtempSync(join(tmpdir(), 'oimpresso-orfaos-uso-'));
    try {
      git(fx4, ['init', '-q']); git(fx4, ['config', 'user.email', 't@t.test']); git(fx4, ['config', 'user.name', 'T']);
      mkdirSync(join(fx4, 'memory/requisitos/Jana'), { recursive: true });
      mkdirSync(join(fx4, 'memory/reference'), { recursive: true });
      writeFileSync(join(fx4, 'memory/requisitos/Jana/alvo.md'), '# alvo\n');
      // (a) fragmento #ancora precisa sobreviver ao religamento
      // (b) o MESMO href morto 2× no arquivo — dedupe na proposta, replaceExact troca as 2
      // (c) code-span com o mesmo path: NÃO é markdown-link, não pode ser tocado
      writeFileSync(join(fx4, 'memory/reference/uso.md'),
        '[x](../antigo/alvo.md#secao-3)\n'
        + '[y](../antigo/repetido.md)\n'
        + '[z](../antigo/repetido.md)\n'
        + 'veja `../antigo/alvo.md` no code-span\n');
      writeFileSync(join(fx4, 'memory/requisitos/Jana/repetido.md'), '# repetido\n');
      // arquivo em OUTRO escopo, pra provar o recorte de --escopo
      mkdirSync(join(fx4, 'outra-area'), { recursive: true });
      writeFileSync(join(fx4, 'outra-area/fora.md'), '[w](../antigo/alvo.md)\n');
      git(fx4, ['add', '.']); git(fx4, ['commit', '-q', '-m', 'fx4']);
      const files4 = trackedFiles(fx4);

      const r4 = planOrphanRelinks(fx4, files4);
      const comFrag = r4.proposals.find((p) => p.from.includes('alvo.md#secao-3'));
      check('USO: fragmento #ancora é PRESERVADO no link religado',
        Boolean(comFrag) && comFrag.to.endsWith('#secao-3'), comFrag);
      check('USO: href morto repetido vira UMA proposta (dedupe por file+href)',
        r4.proposals.filter((p) => p.from.includes('repetido.md')).length === 1, r4.proposals);

      // --escopo recorta de verdade (caso de uso "pago um módulo por vez").
      // MEDIDO ANTES do apply de propósito: depois de religar não sobra link morto
      // pra propor, e o assert viraria verde-por-vacuidade (foi como ele falhou primeiro).
      const soReference = planOrphanRelinks(fx4, files4, { escopo: 'memory/reference/' });
      check('USO: --escopo restringe às propostas daquele prefixo',
        soReference.proposals.length > 0 && soReference.proposals.every((p) => p.file.startsWith('memory/reference/')),
        soReference.proposals);
      check('USO: --escopo contabiliza o que ficou de fora (fora_escopo > 0)',
        soReference.skipped.fora_escopo > 0, soReference.skipped);

      const antes4 = readFileSync(join(fx4, 'memory/reference/uso.md'), 'utf8');
      applyOrphanRelinks(fx4, r4.proposals.filter((p) => p.file === 'memory/reference/uso.md'));
      const depois4 = readFileSync(join(fx4, 'memory/reference/uso.md'), 'utf8');
      check('USO: as DUAS ocorrências do href repetido foram religadas',
        !depois4.includes('](../antigo/repetido.md)'), depois4);
      check('USO: code-span com o mesmo path fica INTACTO (só markdown-link é alvo)',
        depois4.includes('`../antigo/alvo.md`'), depois4);
      check('USO: nenhuma linha extra criada/removida pelo apply',
        antes4.split('\n').length === depois4.split('\n').length, { antes4, depois4 });

      // idempotência: re-rodar depois de religar não propõe nada (o link já está vivo)
      const rerun = planOrphanRelinks(fx4, trackedFiles(fx4), { escopo: 'memory/reference/' });
      check('USO: re-rodar após o apply é NO-OP (idempotente)', rerun.proposals.length === 0, rerun.proposals);
    } finally { if (fx4.startsWith(tmpdir())) rmSync(fx4, { recursive: true, force: true }); }
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

  if (args.includes('--orfaos')) {
    const escIdx = args.indexOf('--escopo');
    const maxIdx = args.indexOf('--max');
    const escopo = escIdx >= 0 ? posixify(args[escIdx + 1]) : null;
    const max = maxIdx >= 0 ? Number(args[maxIdx + 1]) : Infinity;
    const { proposals, skipped, capped } = planOrphanRelinks(root, files, { escopo, max });

    console.log(`[orfaos] link morto RELIGÁVEL (basename com alvo único, referrer mutável): ${proposals.length}${capped ? ` (cortado em --max ${max})` : ''}`);
    console.log(`[orfaos] não propostos — ambíguo: ${skipped.ambiguo} · sem alvo: ${skipped.sem_alvo} · referrer append-only/gate-guarded: ${skipped.nao_relinkavel}`);
    for (const p of proposals) console.log(`    ${p.file}\n      ${p.from}  →  ${p.to}`);
    if (!proposals.length) { console.log('(nada religável neste escopo.)'); return; }
    if (apply) {
      const done = applyOrphanRelinks(root, proposals);
      console.log(`\n  APLICADO: ${done.length} link(s) religado(s) em ${new Set(done.map((d) => d.file)).size} arquivo(s).`);
    } else {
      console.log('\n(dry-run — nada escrito. Use --apply pra religar.)');
    }
    return;
  }

  const moveIdx = args.indexOf('--move');
  let moves;
  if (moveIdx >= 0) {
    moves = [{ id: '(explícito)', from: posixify(args[moveIdx + 1]), to: posixify(args[moveIdx + 2]) }];
  } else if (args.includes('--detect')) {
    const det = detectMoves(root);
    if (det.error) { console.error(det.error); process.exit(2); }
    moves = det.moves;
  } else {
    console.error('uso: --detect [--apply] | --move <A.md> <B.md> [--apply] | --orfaos [--escopo <dir>] [--max N] [--apply] | --selftest');
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
