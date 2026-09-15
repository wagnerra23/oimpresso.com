#!/usr/bin/env node
// @ts-check
/**
 * charter-blueprint-pointers.mjs — auditoria de PONTEIROS DE PROTÓTIPO dos Page Charters.
 *
 * Complementa charter-refs.mjs (que cobre refs markdown + frontmatter component/runbook/
 * parent_capterra). ESTE foca no elo que o loop design→code quebrava silenciosamente: o
 * ponteiro de PROTÓTIPO/BLUEPRINT do charter (`mwart_pattern_reuse.blueprint_cowork`,
 * `blueprint_cowork`, e Refs apontando pra `prototipo-ui/**` ou `ui_kits/**`). Quando esse
 * ponteiro aponta pro VÁCUO, o gate 3-way (reconcile-triplet) não tem com o que comparar a
 * coluna do meio → o protótipo some e o conflito charter×produção fica invisível.
 *
 * Caso real provado: Produto tem 3 ponteiros órfãos —
 *   prototipo-ui/cowork/Wagner/legado/produto-cockpit/  (frontmatter blueprint_cowork)
 *   prototipo-ui/cowork/Felipe/legado/produto/          (cowork-map / charter)
 *   ui_kits/cowork-2026-05-09/prod-page.jsx   (Refs)
 *
 * Determinístico, sem deps, sem LLM. case-SENSITIVE (espelha CI Linux/Hostinger).
 *
 * Uso:
 *   node scripts/governance/charter-blueprint-pointers.mjs            (texto)
 *   node scripts/governance/charter-blueprint-pointers.mjs --json
 *   node scripts/governance/charter-blueprint-pointers.mjs --strict   (exit 1 se houver órfão)
 */
import { readFileSync, readdirSync, realpathSync, statSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = process.cwd();
const PAGES = join(ROOT, 'resources/js/Pages');

function existsExact(p) {
  if (!p) return false;
  try { return realpathSync.native(p).replaceAll('\\', '/') === p.replaceAll('\\', '/'); }
  catch { return false; }
}
function dirExists(p) {
  try { return statSync(p).isDirectory(); } catch { return false; }
}

const hasUnderscoreSeg = (rel) => rel.split('/').some((s) => s && s[0] === '_');

function charterFiles() {
  const out = [];
  if (!dirExists(PAGES)) return out;
  (function walk(dir) {
    for (const e of readdirSync(dir, { withFileTypes: true })) {
      const p = join(dir, e.name);
      if (e.isDirectory()) walk(p);
      else if (e.isFile() && p.endsWith('.charter.md')) {
        const rel = p.slice(PAGES.length + 1).replaceAll('\\', '/');
        if (!hasUnderscoreSeg(rel)) out.push(rel);
      }
    }
  })(PAGES);
  return out.sort();
}

function splitFrontmatter(content) {
  const m = content.match(/^---\r?\n(.*?)\r?\n---\r?\n?(.*)$/s);
  return m ? [m[1], m[2]] : ['', content];
}

/** Coleta todos os ponteiros de protótipo declarados num charter (com a fonte). */
function pointersOf(absPath) {
  const [fm, body] = splitFrontmatter(readFileSync(absPath, 'utf8'));
  const ptrs = [];

  // 1. frontmatter blueprint_cowork (sob mwart_pattern_reuse ou raiz)
  for (const mm of fm.matchAll(/^\s*blueprint_cowork:\s*["']?(\S+?)["']?\s*$/gm)) {
    ptrs.push({ path: mm[1], src: 'frontmatter:blueprint_cowork' });
  }
  // blueprint_screenshot_approval às vezes referencia path — ignoramos (não é dir/arquivo).

  // 2. Refs no corpo: backtick-paths pra prototipo-ui/** ou ui_kits/**
  for (const mm of body.matchAll(/`(prototipo-ui\/[^`]+|ui_kits\/[^`]+)`/g)) {
    const raw = mm[1].trim();
    if (/\.(jsx|tsx|html|css|md|json)$/.test(raw) || raw.endsWith('/')) {
      ptrs.push({ path: raw, src: 'corpo:Refs' });
    }
  }
  // 3. Refs no corpo via link markdown ](prototipo-ui/...) ou ](../...prototipo-ui...)
  for (const mm of body.matchAll(/\]\(([^)\s]*(?:prototipo-ui|ui_kits)\/[^)\s]+)\)/g)) {
    const raw = mm[1].trim();
    // só os que são caminho de repo (não http)
    if (!/^https?:\/\//.test(raw)) ptrs.push({ path: raw, src: 'corpo:link-md' });
  }

  // dedup por path
  const seen = new Set();
  return ptrs.filter((p) => { const k = p.path; if (seen.has(k)) return false; seen.add(k); return true; });
}

/** Resolve um ponteiro (pode ter ../ relativo ao charter) a um caminho repo-absoluto. */
function resolvePtr(baseDir, ptrPath) {
  if (/^https?:\/\//.test(ptrPath)) return null;
  // limpa âncoras/markdown
  let clean = ptrPath.replace(/[#].*$/, '');
  if (clean.startsWith('../') || clean.startsWith('./')) {
    // relativo ao diretório do doc que declara o ponteiro
    const joined = join(baseDir, clean).replaceAll('\\', '/');
    return joined;
  }
  // repo-relative (prototipo-ui/... ou ui_kits/...)
  return join(ROOT, clean).replaceAll('\\', '/');
}

function isOrphan(absPath, rawPtr) {
  if (!absPath) return false;
  const isFile = /\.(jsx|tsx|html|css|md|json)$/.test(rawPtr);
  return isFile ? !existsExact(absPath) : !dirExists(absPath);
}

/**
 * 2a RAIZ — os docs de `memory/requisitos` (RUNBOOK, SPEC, visual-comparison, gap).
 *
 * Por que existe: e o gap que o #7335 pagou A MAO. O diretorio `prototipo-ui/prototipos`
 * morreu em mai/jun-2026 e 61 docs de requisitos seguiram apontando pro vacuo por MESES sem
 * alarme — esta maquina so olhava os charters de `resources/js/Pages`, e o charter nunca foi
 * o unico lugar onde o ponteiro de prototipo vive.
 *
 * FP MEDIDO ANTES de ligar (disciplina §5 — a familia de guard sintatico ja tem 8 lapides):
 * 1292 docs varridos, 335 ponteiros extraidos, 141 orfaos. Destes, 41 (29%) sao docs que JA
 * DECLARAM a morte na propria linha ("removido em <data>, <sha>", "PATH APAGADO", "apagado
 * em") — registro datado CORRETO, nao divida; cobra-los puniria justamente quem fez a coisa
 * certa. `declaraMorte()` os exclui, e o que sobra (100) e o sinal.
 *
 * ADVISORY, report-only: NAO entra no `--strict`, que segue sendo dos charters. O step do
 * `reconcile-triplet.yml` roda sem `--strict` (exit 0) — isto reporta, nao bloqueia.
 */
const BS = String.fromCharCode(92);  // barra invertida sem literal (colapsa no transporte — LC-26)
const REQ = join(ROOT, 'memory/requisitos');

/** A linha que carrega o ponteiro ja declara que ele morreu? Entao e registro, nao divida. */
/**
 * Placeholder NAO e ponteiro: `cowork-YYYY-MM-DD/` e `prototipo-ui/.../` sao notacao
 * generica que a prosa usa pra falar de um formato, nao caminho que um dia resolveu.
 * Anota-los com data de remocao seria carimbar o que nunca foi arquivo — falso-positivo
 * do extrator, nao divida do doc. (Medido: os 2 unicos casos no corpus, ambos em ADR UI.)
 */
function ehPlaceholder(p) {
  const S = String.fromCharCode(47), D = String.fromCharCode(46);  // / e . sem literal (LC-26)
  return p.includes('YYYY-MM-DD') || p.includes(S + D + D + D + S) || p.endsWith(S + D + D + D);
}

function declaraMorte(linha) {
  // "nunca versionado": o alvo NUNCA existiu no git (artefato externo do Cowork — zip, pasta
  // local). MEDIDO no historico completo, repo nao-raso: 0 commits tocaram esses paths. E
  // declaracao do mesmo tipo — o doc diz por que o ponteiro nao resolve — entao sai da cobranca.
  return /removido em|PATH APAGADO|apagado em|nunca versionado|N\u00c3O EXISTE|NAO EXISTE|Corrigido 20/i.test(linha);
}

function requisitosDocs() {
  const out = [];
  if (!dirExists(REQ)) return out;
  (function walk(dir) {
    for (const e of readdirSync(dir, { withFileTypes: true })) {
      const p = join(dir, e.name);
      if (e.isDirectory()) walk(p);
      else if (e.isFile() && p.endsWith('.md')) out.push(p.split(BS).join('/'));
    }
  })(REQ);
  return out.sort();
}

/** Orfaos MUDOS nos docs de requisitos (os datados ficam de fora — ver docblock acima). */
function auditRequisitos() {
  const perDoc = [];
  for (const abs of requisitosDocs()) {
    const linhas = readFileSync(abs, 'utf8').split('\n');
    const ptrs = pointersOf(abs);
    const orphans = [];
    for (const p of ptrs) {
      const alvo = resolvePtr(join(abs, '..'), p.path);
      if (ehPlaceholder(p.path)) continue;        // notacao generica, nao caminho
      if (!isOrphan(alvo, p.path)) continue;
      const linha = linhas.find((l) => l.includes(p.path)) || '';
      if (declaraMorte(linha)) continue;
      orphans.push({ path: p.path, src: p.src });
    }
    if (orphans.length) perDoc.push({ doc: abs.slice(ROOT.length + 1).split(BS).join('/'), total: ptrs.length, orphans });
  }
  return { perDoc, totalOrphans: perDoc.reduce((a, d) => a + d.orphans.length, 0) };
}


function audit() {
  const perCharter = [];
  for (const rel of charterFiles()) {
    const ptrs = pointersOf(join(PAGES, rel));
    const orphans = [];
    for (const p of ptrs) {
      const abs = resolvePtr(join(PAGES, rel, '..'), p.path);
      if (isOrphan(abs, p.path)) orphans.push({ path: p.path, src: p.src });
    }
    if (ptrs.length) perCharter.push({ charter: 'resources/js/Pages/' + rel, total: ptrs.length, orphans });
  }
  const withOrphans = perCharter.filter((c) => c.orphans.length > 0);
  const totalOrphans = withOrphans.reduce((a, c) => a + c.orphans.length, 0);
  return { perCharter, withOrphans, totalOrphans };
}

/** Advisory B1 (ponte design↔código): charters com `visual_source:` SEM `visual_source_sha:`.
 *  Sem o sha do export, não dá pra detectar DRIFT quando `cowork/` é sobrescrito no próximo
 *  handoff. Report-only — NÃO afeta --strict (é base do `<tela>.map.json` por-região, B2). */
function shaAdvisory() {
  const missing = [];
  for (const rel of charterFiles()) {
    const [fm] = splitFrontmatter(readFileSync(join(PAGES, rel), 'utf8'));
    if (!/^visual_source:\s*\S/m.test(fm)) continue;
    if (!/^visual_source_sha:\s*\S/m.test(fm)) missing.push('resources/js/Pages/' + rel);
  }
  return missing;
}

// ── CLI ──────────────────────────────────────────────────────────────────────
const json = process.argv.includes('--json');
const strict = process.argv.includes('--strict');
const r = audit();
const shaMiss = shaAdvisory();
const req = auditRequisitos();   // 2a raiz: memory/requisitos (advisory, report-only)

if (json) {
  console.log(JSON.stringify({
    tool: 'charter-blueprint-pointers',
    charters_com_ponteiro: r.perCharter.length,
    charters_com_orfao: r.withOrphans.length,
    total_orfaos: r.totalOrphans,
    detalhe: r.withOrphans,
    visual_source_sha_faltando: shaMiss,
    requisitos_docs_com_orfao: req.perDoc.length,
    requisitos_total_orfaos: req.totalOrphans,
    requisitos_detalhe: req.perDoc,
  }, null, 2));
} else {
  console.log('charter-blueprint-pointers — auditoria de ponteiros de protótipo/blueprint dos charters\n');
  console.log(`Charters com ≥1 ponteiro de protótipo: ${r.perCharter.length}`);
  console.log(`Charters com ponteiro ÓRFÃO (aponta pro vácuo): ${r.withOrphans.length}`);
  console.log(`Total de ponteiros órfãos: ${r.totalOrphans}\n`);
  for (const c of r.withOrphans) {
    console.log(`  ${c.charter}  (${c.orphans.length}/${c.total} órfão)`);
    for (const o of c.orphans) console.log(`     ✗ ${o.path}   [${o.src}]`);
  }
  if (!r.withOrphans.length) console.log('  ✓ nenhum ponteiro órfão.');
  console.log(`\n— advisory B1 (ponte design↔código): charters com visual_source: SEM visual_source_sha: = ${shaMiss.length}`);
  console.log('  (sem sha do export, drift não rastreável quando cowork/ é sobrescrito — base do mapa por-região)');
  for (const m of shaMiss) console.log(`     ⚠ ${m}`);

  console.log(`
— 2a raiz (advisory): memory/requisitos com ponteiro de prototipo ORFAO e MUDO = ${req.totalOrphans} em ${req.perDoc.length} doc(s)`);
  console.log("  (orfao cujo doc JA declara a morte na linha nao entra — e registro datado, nao divida)");
  for (const d of req.perDoc) {
    console.log(`     ${d.doc}  (${d.orphans.length}/${d.total})`);
    for (const o of d.orphans) console.log(`        ✗ ${o.path}   [${o.src}]`);
  }
  if (!req.perDoc.length) console.log("     ✓ nenhum orfao mudo em memory/requisitos.");
}

if (strict && r.totalOrphans > 0) process.exit(1);
process.exit(0);
