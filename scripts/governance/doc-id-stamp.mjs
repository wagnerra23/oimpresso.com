#!/usr/bin/env node
// @ts-check
/**
 * doc-id-stamp.mjs — STAMPER: adiciona `id:` no frontmatter dos docs SEM id.
 *
 * PR5+ do design proposals/2026-07-23-referencia-id-estavel-doc-links.md (decisão [W]
 * "físico em todos"). Adiciona SÓ o campo `id:` — nenhuma outra mudança de conteúdo.
 *
 * ID = slug do caminho relativo a memory/ sem extensão (ex.: memory/reference/foo.md →
 * `reference-foo`). É ÚNICO por construção (path é único) e, uma vez carimbado, FICA FIXO
 * no frontmatter — não muda quando o doc move (o auto-religador rastreia por ele). O fato
 * de ter sido derivado do path no momento do stamp é irrelevante depois: vira identidade.
 *
 * SEGURANÇA (o muro do §5 2026-07-12): por default só carimba as famílias GRACE/WARN
 * (reference/governance/research/audits/dominios) — que NÃO estão sob anchor-lint (SPEC-only)
 * nem distiller_freshness (requisitos-only). requisitos/ e charter são GATE-TOXIC e ficam de
 * fora até a ADR do PR4 (gates cientes de id-only) — passe --include-toxic só com essa ADR aceita.
 * Exclui: gerados (authority/AUTO-GERADO), _-prefixados, o set PROTECTED, e docs que já têm id.
 *
 * Uso:
 *   node scripts/governance/doc-id-stamp.mjs                 (dry-run: conta + amostra + colisões)
 *   node scripts/governance/doc-id-stamp.mjs --apply         (escreve os id: nos arquivos seguros)
 *   node scripts/governance/doc-id-stamp.mjs --selftest
 *   ... [--root <dir>] [--include-toxic] (requisitos/charter — SÓ com ADR PR4 aceita)
 */
import { readdirSync, readFileSync, writeFileSync, mkdtempSync, mkdirSync, rmSync } from 'node:fs';
import { join, resolve, relative, sep } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';

// SEGURO = famílias que NÃO estão sob NENHUM gate diff-aware que morda ao ser tocado.
// audits/ e research/ SAÍRAM (2026-07-23): são HISTORY append-only (o PII scan mordeu CNPJs
// pré-existentes em research/prospeccao ao tocar; audits/research são fósseis datados — id
// deles é derivado/deferido, não stampado). O muro §5 2026-07-12 vale pra TODO gate diff-aware
// (memory-schema, anchor-lint, distiller, append-only-canon, PII), não só os 3 que eu checara.
// dominios/ SAIU tb (2026-07-23): é o corpus Migration Factory GERADO (wr-comercial/.../tabelas/*.md
// com auto_generated:true) — 40/54 eram dicts cujo marcador fica além dos 600 chars do detector
// (frontmatter longo), risco de carimbar gerado. Baixo valor pra link-rot. Defere pra pass própria.
const SAFE_PREFIXES = ['memory/reference/', 'memory/governance/'];
const TOXIC_PREFIXES = ['memory/requisitos/', 'resources/js/Pages/', 'memory/audits/', 'memory/research/', 'memory/dominios/'];
const PROTECTED = new Set([
  'README.md', 'AGENTS.md', 'CLAUDE.md', 'DESIGN.md', 'INFRA.md', 'TEAM.md',
  'CODE_NOTES.md', 'MEMORY_TEAM_ONBOARDING.md',
  'memory/governance/CONSTITUTION.md', // edição exige label constitution-amendment + audit cascade (§10.4)
]);
// Prefixos append-only/cerimoniais dentro de governance/ que o stamp NÃO toca.
const EXCLUDE_PREFIXES = ['memory/governance/audit-', 'memory/governance/shipped/'];
// PII: regex EXATO do .github/scripts/pii-scan.sh (só formatado). Tocar arquivo com CPF/CNPJ
// literal acorda o scan (required) sobre PII pré-existente — pula (defere) esses.
const CPF_RE = /[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}/;
const CNPJ_RE = /[0-9]{2}\.[0-9]{3}\.[0-9]{3}\/[0-9]{4}-[0-9]{2}/;

const slugify = (v) => v.normalize('NFD').replace(/[̀-ͯ]/g, '')
  .replace(/\.md$/i, '').replace(/([a-z0-9])([A-Z])/g, '$1-$2')
  .toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

// id = slug do path relativo a memory/ (ou a raiz do repo pra docs fora de memory/).
export function idForPath(relPath) {
  const base = relPath.startsWith('memory/') ? relPath.slice('memory/'.length) : relPath;
  return slugify(base);
}

function hasFrontmatter(text) { return text.startsWith('---'); }
function frontmatterHasId(text) {
  if (!hasFrontmatter(text)) return false;
  const end = text.indexOf('\n---', 3);
  const fm = end === -1 ? text : text.slice(0, end);
  return /^id:\s*\S/mi.test(fm);
}
// Gerado (não carimbar — o gerador reescreve). Marcador REAL de arquivo gerado, não prosa:
// authority:generated no frontmatter, OU AUTO-GENERATED/DO NOT EDIT, OU "GERADO … NÃO EDITAR"
// em proximidade (alinhado com looksGenerated do document-relocation-adversary). NÃO casa uma
// menção solta a "gerado automaticamente" no corpo (falso-positivo que sub-carimbava — 2026-07-23).
function looksGenerated(text) {
  // Frontmatter INTEIRO (marcador pode ficar longe do topo — ex. wr-comercial tem 99 colunas
  // antes do auto_generated) + os primeiros 600 chars do corpo.
  const fmEnd = text.startsWith('---') ? text.indexOf('\n---', 3) : -1;
  const head = (fmEnd >= 0 ? text.slice(0, fmEnd + 600) : text.slice(0, 600));
  return /^authority:\s*generated/mi.test(head)
    || /^auto_generated:\s*true/mi.test(head)
    || /(AUTO[- ]GENERATED|DO NOT EDIT)/i.test(head)
    || /(GERAD[OA])[^\n]{0,160}(N[ÃA]O[- ]EDIT|não editar)/i.test(head)
    || /arquivo gerado automaticamente por `?(?:module:requirements|[\w-]+\.mjs)`?/i.test(head);
}

/** Insere `id:` no frontmatter (cria o bloco se não houver). Idempotente. Preserva o resto. LF. */
export function stampId(text, id) {
  if (frontmatterHasId(text)) return text; // idempotente
  if (hasFrontmatter(text)) {
    // insere logo após o `---` de abertura (1ª linha do frontmatter)
    return text.replace(/^---\r?\n/, `---\nid: ${id}\n`);
  }
  return `---\nid: ${id}\n---\n\n${text}`;
}

function walk(rootAbs, relBase, acc) {
  let entries;
  try { entries = readdirSync(join(rootAbs, relBase), { withFileTypes: true }); } catch { return acc; }
  for (const e of entries) {
    if (e.name === '.git' || e.name === 'node_modules' || e.name === 'vendor') continue;
    const rel = relBase ? `${relBase}/${e.name}` : e.name;
    if (e.isDirectory()) walk(rootAbs, rel, acc);
    else if (e.name.toLowerCase().endsWith('.md') && !e.name.startsWith('_')) acc.push(rel);
  }
  return acc;
}

export function collectTargets(root, { includeToxic = false } = {}) {
  const prefixes = includeToxic ? [...SAFE_PREFIXES, ...TOXIC_PREFIXES] : SAFE_PREFIXES;
  const all = walk(root, '', []).map((p) => p.replaceAll('\\', '/'));
  return all.filter((p) => prefixes.some((pre) => p.startsWith(pre))
    && !PROTECTED.has(p) && !EXCLUDE_PREFIXES.some((pre) => p.startsWith(pre)));
}

// Famílias sob schema com campos OBRIGATÓRIOS: criar frontmatter só-`id:` num doc que hoje
// NÃO tem frontmatter deixaria o bloco faltando os required (name/description/type/authority
// no reference) → warning grace + doc parcial. Esses precisam de frontmatter COMPLETO
// (curadoria), não stamp mecânico → deferidos. reference/ é a única família segura mapeada com
// required (governance/research/audits/dominios são NÃO-mapeados → id-only é livre lá).
const REQUIRED_SCHEMA_PREFIXES = ['memory/reference/'];

export function plan(root, opts = {}) {
  const targets = collectTargets(root, opts);
  const toStamp = []; const skipped = { hasId: 0, generated: 0, deferredNoFrontmatter: 0, pii: 0 }; const byId = new Map();
  for (const rel of targets) {
    let text = '';
    try { text = readFileSync(join(root, rel), 'utf8'); } catch { continue; }
    if (frontmatterHasId(text)) { skipped.hasId++; continue; }
    if (looksGenerated(text)) { skipped.generated++; continue; }
    if (CPF_RE.test(text) || CNPJ_RE.test(text)) { skipped.pii++; continue; } // tocar acordaria o PII scan
    if (!hasFrontmatter(text) && REQUIRED_SCHEMA_PREFIXES.some((pre) => rel.startsWith(pre))) { skipped.deferredNoFrontmatter++; continue; }
    const id = idForPath(rel);
    if (!byId.has(id)) byId.set(id, []);
    byId.get(id).push(rel);
    toStamp.push({ rel, id, hadFrontmatter: hasFrontmatter(text) });
  }
  const collisions = [...byId.entries()].filter(([, ps]) => ps.length > 1).map(([id, paths]) => ({ id, paths }));
  return { toStamp, skipped, collisions, total: targets.length };
}

function runSelftest() {
  const cases = [];
  const ok = (n, c) => cases.push({ n, ok: Boolean(c) });
  ok('idForPath: reference', idForPath('memory/reference/feedback-hostinger.md') === 'reference-feedback-hostinger');
  ok('idForPath: subpasta preserva unicidade', idForPath('memory/governance/scorecards/x.md') === 'governance-scorecards-x');
  ok('stampId cria frontmatter quando não há', stampId('# Título\n\ncorpo\n', 'reference-x') === '---\nid: reference-x\n---\n\n# Título\n\ncorpo\n');
  ok('stampId insere em frontmatter existente', stampId('---\ntitle: X\n---\n\ncorpo\n', 'reference-x') === '---\nid: reference-x\ntitle: X\n---\n\ncorpo\n');
  ok('stampId idempotente (já tem id)', stampId('---\nid: reference-x\n---\n', 'reference-y') === '---\nid: reference-x\n---\n');
  ok('frontmatterHasId detecta', frontmatterHasId('---\nid: a\n---\n') === true && frontmatterHasId('---\ntitle: a\n---\n') === false);
  ok('looksGenerated pega authority:generated', looksGenerated('---\nauthority: generated\n---\n') === true);

  const fx = mkdtempSync(join(tmpdir(), 'oimpresso-stamp-'));
  try {
    mkdirSync(join(fx, 'memory/reference'), { recursive: true });
    mkdirSync(join(fx, 'memory/governance'), { recursive: true });
    mkdirSync(join(fx, 'memory/dominios'), { recursive: true });
    mkdirSync(join(fx, 'memory/requisitos/Jana'), { recursive: true });
    writeFileSync(join(fx, 'memory/reference/a.md'), '---\ntype: reference\n---\n# A\n'); // com frontmatter → carimba
    writeFileSync(join(fx, 'memory/reference/sem-fm.md'), '# Sem frontmatter\n');          // reference sem fm → DEFERE
    writeFileSync(join(fx, 'memory/governance/notes.md'), '# Governance sem fm\n');         // família não-mapeada → carimba
    writeFileSync(join(fx, 'memory/governance/CONSTITUTION.md'), '# Constituição\n');       // PROTECTED → não toca
    writeFileSync(join(fx, 'memory/governance/cliente-x.md'), '# Cliente\n\nCNPJ 12.345.678/0001-99\n'); // PII → pula
    writeFileSync(join(fx, 'memory/reference/b.md'), '---\nid: reference-b\n---\n# B\n'); // já tem id
    writeFileSync(join(fx, 'memory/reference/gen.md'), '---\nauthority: generated\n---\n# Gerado\n');
    writeFileSync(join(fx, 'memory/requisitos/Jana/SPEC.md'), '# SPEC gate-toxic\n');
    const p = plan(fx);
    ok('plan: carimba reference/a (com frontmatter)', p.toStamp.some((t) => t.rel === 'memory/reference/a.md' && t.id === 'reference-a'));
    ok('plan: DEFERE reference sem frontmatter (evita required parcial)', p.skipped.deferredNoFrontmatter === 1 && !p.toStamp.some((t) => t.rel.endsWith('sem-fm.md')));
    ok('plan: carimba família NÃO-mapeada sem frontmatter (governance)', p.toStamp.some((t) => t.rel === 'memory/governance/notes.md' && t.id === 'governance-notes'));
    ok('plan: NÃO toca CONSTITUTION.md (PROTECTED)', !p.toStamp.some((t) => t.rel.endsWith('CONSTITUTION.md')));
    ok('plan: PULA arquivo com CNPJ (PII scan acordaria)', p.skipped.pii === 1 && !p.toStamp.some((t) => t.rel.endsWith('cliente-x.md')));
    ok('plan: pula quem já tem id', p.skipped.hasId === 1 && !p.toStamp.some((t) => t.rel.endsWith('/b.md')));
    ok('plan: pula gerado', p.skipped.generated === 1);
    ok('plan: NÃO toca gate-toxic (requisitos/audits/research) sem --include-toxic', !p.toStamp.some((t) => t.rel.includes('requisitos')));
    ok('plan: com --include-toxic pega requisitos', plan(fx, { includeToxic: true }).toStamp.some((t) => t.rel.includes('requisitos/Jana/SPEC')));
    ok('plan: sem colisão no caso feliz', p.collisions.length === 0);
  } finally { if (fx.startsWith(tmpdir())) rmSync(fx, { recursive: true, force: true }); }

  for (const c of cases) console.log(`${c.ok ? '[OK]  ' : '[FALHA]'} ${c.n}`);
  const f = cases.filter((c) => !c.ok);
  console.log(`\n${f.length ? 'SELFTEST FALHOU' : 'SELFTEST OK'} - ${cases.length - f.length}/${cases.length}`);
  if (f.length) process.exit(1);
}

function main() {
  const args = process.argv.slice(2);
  if (args.includes('--selftest')) return runSelftest();
  const rootIdx = args.indexOf('--root');
  const root = resolve(rootIdx >= 0 ? args[rootIdx + 1] : process.cwd());
  const includeToxic = args.includes('--include-toxic');
  const p = plan(root, { includeToxic });

  console.log(`[doc-id-stamp] alvos: ${p.total} · a carimbar: ${p.toStamp.length} · já com id: ${p.skipped.hasId} · gerados: ${p.skipped.generated} · PII (pulados): ${p.skipped.pii} · reference sem-fm (deferidos): ${p.skipped.deferredNoFrontmatter}`);
  if (p.collisions.length) {
    console.error(`\nCOLISÃO (${p.collisions.length}) — dois paths geram o mesmo id; resolva antes de aplicar:`);
    for (const c of p.collisions) console.error(`  ${c.id}: ${c.paths.join(' , ')}`);
    process.exit(1);
  }
  const byFamily = {};
  for (const t of p.toStamp) { const fam = t.rel.split('/')[1]; byFamily[fam] = (byFamily[fam] || 0) + 1; }
  console.log(`  por família: ${JSON.stringify(byFamily)}`);
  for (const t of p.toStamp.slice(0, 8)) console.log(`   + ${t.rel}  →  id: ${t.id}`);

  if (args.includes('--apply')) {
    let n = 0;
    for (const t of p.toStamp) {
      const abs = join(root, t.rel);
      writeFileSync(abs, stampId(readFileSync(abs, 'utf8'), t.id), 'utf8');
      n++;
    }
    console.log(`\nAPLICADO: ${n} arquivo(s) carimbado(s) com id:.`);
  } else {
    console.log('\n(dry-run — nada escrito. Use --apply.)');
  }
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try { main(); } catch (e) { console.error(`DOC-ID-STAMP ERROR: ${e.message}`); process.exit(2); }
}
