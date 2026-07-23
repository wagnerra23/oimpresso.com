#!/usr/bin/env node
// @ts-check
/**
 * doc-id-index.mjs — GERADOR determinístico do índice `id → path atual` do corpus memory/.
 *
 * Por quê (grade estado-da-arte 2026-07-23, item 2): ~1105 links doc↔doc mortos (7,5%)
 * porque path relativo apodrece no move/rename. A cura é referência por ID ESTÁVEL que
 * sobrevive ao move (padrão Antora page-aliases). Este é o PR1 do design
 * `proposals/2026-07-23-referencia-id-estavel-doc-links.md`: a FONTE-DE-VERDADE `id → path`.
 * NÃO muta conteúdo de doc nenhum — só lê e emite o índice. O auto-religador (PR2) e o
 * deadlink-gate (PR3) consomem este índice.
 *
 * ID por família (decisão [W] 2026-07-23 "físico em todos", refinada — filename JÁ é o id
 * físico dos append-only, não se duplica):
 *   - memory/decisions/NNNN-*.md  → id = stem do filename `NNNN-slug`  (DERIVADO; ÚNICO por filesystem)
 *   - memory/sessions/*.md        → id = stem do filename  (DERIVADO; fóssil append-only, não move)
 *   - memory/handoffs/*.md        → id = stem do filename  (idem)
 *   - qualquer doc com `id:` no frontmatter → id = esse valor  (STAMPED; sobrevive a move de path)
 *   - demais (movable sem stamp)  → SEM id ainda → entra na worklist `unstamped` (a ser stampado)
 *
 * NOTA (achado 2026-07-23): o NÚMERO da ADR NÃO é único no repo (14 números com 2-3 ADRs cada —
 * drift de numeração paralela, cf. ADR 0180). Por isso o id de ADR = stem COMPLETO (`0170-onda5-...`),
 * não `adr-0170`. Custo: rename de slug mantendo número muda o id → o auto-religador (PR2) cobre.
 *
 * Uso:
 *   node scripts/governance/doc-id-index.mjs            (dry-run: resumo + colisões)
 *   node scripts/governance/doc-id-index.mjs --json     (imprime o índice completo em JSON)
 *   node scripts/governance/doc-id-index.mjs --write    (grava governance/doc-id-index.json)
 *   node scripts/governance/doc-id-index.mjs --check     (CI: exit 1 se o gerado ≠ commitado, OU se houver colisão)
 *   node scripts/governance/doc-id-index.mjs --selftest  (fixture hermético)
 *   ... [--root <dir>] pra corpus alternativo (testes)
 *
 * Refs: ADR 0256 (survival, fonte única gerada) · design proposal 2026-07-23 · deadlink-gate.mjs (irmão detector).
 */
import { readdirSync, readFileSync, writeFileSync, existsSync, mkdtempSync, mkdirSync, rmSync } from 'node:fs';
import { join, resolve, relative, sep } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';

const SCHEMA_VERSION = 1;
const OUT = 'governance/doc-id-index.json';
const SCAN_ROOTS = ['memory']; // corpus doc↔doc; charters (Pages/**) entram num PR posterior.
const SKIP_DIRS = new Set(['.git', 'node_modules', 'vendor', '.worktrees']);

/** Extrai o valor de um campo escalar simples do frontmatter YAML (sem dep externa). */
function frontmatterId(text) {
  if (!text.startsWith('---')) return '';
  const end = text.indexOf('\n---', 3);
  const fm = end === -1 ? text.slice(0, 4000) : text.slice(0, end);
  const m = fm.match(/^id:\s*(.+)$/mi);
  return m ? m[1].trim().replace(/^["']|["']$/g, '') : '';
}

/** Caminha o corpus e devolve paths posix relativos ao root, ordenados. */
function walkMarkdown(root, scanRoots = SCAN_ROOTS) {
  const out = [];
  const walk = (absolute, rel) => {
    let entries;
    try { entries = readdirSync(absolute, { withFileTypes: true }); } catch { return; }
    for (const entry of entries) {
      if (entry.isDirectory() && SKIP_DIRS.has(entry.name)) continue;
      const childRel = rel ? `${rel}/${entry.name}` : entry.name;
      const childAbs = join(absolute, entry.name);
      if (entry.isDirectory()) walk(childAbs, childRel);
      // `_`-prefixados (_TEMPLATE, _INDEX*, _overview) = meta/gerados, não conteúdo endereçável.
      else if (entry.isFile() && entry.name.toLowerCase().endsWith('.md') && !entry.name.startsWith('_')) out.push(childRel);
    }
  };
  for (const sr of scanRoots) walk(join(root, sr), sr);
  return out.sort();
}

/** Resolve o id de um doc pela família. Retorna { id, source } ou { id: null, source: 'unstamped' }. */
export function resolveId(relPath, text) {
  const stamped = frontmatterId(text);
  if (stamped) return { id: stamped, source: 'stamped' };
  const stem = relPath.replace(/^.*\//, '').replace(/\.md$/i, '');
  if (/^memory\/decisions\/\d{4}-.+\.md$/.test(relPath)) return { id: stem, source: 'derived-adr' };
  const fossil = relPath.match(/^memory\/(sessions|handoffs)\/.+\.md$/);
  if (fossil) return { id: stem, source: `derived-${fossil[1]}` };
  return { id: null, source: 'unstamped' };
}

/** Constrói o índice a partir do corpus no root. Determinístico. */
export function buildIndex(root, scanRoots = SCAN_ROOTS) {
  const files = walkMarkdown(root, scanRoots);
  const byId = new Map();      // id -> [paths]
  const unstamped = [];
  const bySource = {};
  for (const relPath of files) {
    let text = '';
    try { text = readFileSync(join(root, relPath), 'utf8'); } catch { /* ilegível → trata como unstamped */ }
    const { id, source } = resolveId(relPath, text);
    bySource[source] = (bySource[source] ?? 0) + 1;
    if (!id) { unstamped.push(relPath); continue; }
    if (!byId.has(id)) byId.set(id, []);
    byId.get(id).push(relPath);
  }
  const ids = {};
  const collisions = [];
  for (const [id, paths] of [...byId.entries()].sort(([a], [b]) => a.localeCompare(b))) {
    if (paths.length > 1) collisions.push({ id, paths: paths.sort() });
    ids[id] = paths.sort()[0];
  }
  return {
    schema_version: SCHEMA_VERSION,
    ids,                              // id -> path canônico atual
    unstamped: unstamped.sort(),      // worklist do backfill (docs movable sem id estável)
    collisions,                       // id repetido = ERRO (dois docs reivindicam a mesma identidade)
    stats: { total: files.length, resolved: Object.keys(ids).length, unstamped: unstamped.length, collisions: collisions.length, by_source: bySource },
  };
}

/** Serialização canônica (determinística) pro --write/--check. */
function serialize(index) {
  return `${JSON.stringify(index, null, 2)}\n`;
}

function runSelftest() {
  const cases = [];
  const check = (name, ok, ev) => { cases.push({ name, ok: Boolean(ok), ev }); };

  // Contrato puro de resolveId (sem tocar disco).
  check('ADR deriva id do stem completo (único)', resolveId('memory/decisions/0094-constituicao.md', '# x').id === '0094-constituicao');
  check('ADR: número duplicado NÃO colide (slugs diferentes → ids diferentes)',
    resolveId('memory/decisions/0170-onda5.md', '# x').id !== resolveId('memory/decisions/0170-bancos.md', '# x').id);
  check('session deriva id do stem', resolveId('memory/sessions/2026-04-18-session-01.md', '# x').id === '2026-04-18-session-01');
  check('handoff deriva id do stem', resolveId('memory/handoffs/2026-05-10-2230-pivot.md', '# x').id === '2026-05-10-2230-pivot');
  check('stamped vence a derivação', resolveId('memory/decisions/0094-x.md', '---\nid: constituicao-v2\n---\n').id === 'constituicao-v2');
  check('movable sem stamp = unstamped (sem id)', resolveId('memory/reference/foo.md', '# só corpo').id === null);
  check('stamped em reference resolve', resolveId('memory/reference/foo.md', '---\nid: foo-canon\n---\n').id === 'foo-canon');

  // Fixture hermético em disco: build completo + colisão.
  const fixture = mkdtempSync(join(tmpdir(), 'oimpresso-doc-id-'));
  try {
    mkdirSync(join(fixture, 'memory/decisions'), { recursive: true });
    mkdirSync(join(fixture, 'memory/sessions'), { recursive: true });
    mkdirSync(join(fixture, 'memory/reference'), { recursive: true });
    writeFileSync(join(fixture, 'memory/decisions/0094-constituicao.md'), '# Constituição\n');
    writeFileSync(join(fixture, 'memory/sessions/2026-04-18-session-01.md'), '# Sessão\n');
    writeFileSync(join(fixture, 'memory/reference/stamped.md'), '---\nid: guia-canon\n---\n# Guia\n');
    writeFileSync(join(fixture, 'memory/reference/sem-id.md'), '# Sem id ainda\n');
    const idx = buildIndex(fixture);
    check('build resolve ADR+session+stamped', idx.ids['0094-constituicao'] === 'memory/decisions/0094-constituicao.md'
      && idx.ids['2026-04-18-session-01'] === 'memory/sessions/2026-04-18-session-01.md'
      && idx.ids['guia-canon'] === 'memory/reference/stamped.md', idx.ids);
    check('build lista o unstamped (worklist)', idx.unstamped.includes('memory/reference/sem-id.md'), idx.unstamped);
    check('build sem colisão no caso feliz', idx.collisions.length === 0, idx.collisions);
    // Colisão: dois docs com o MESMO id stamped.
    writeFileSync(join(fixture, 'memory/reference/dup-a.md'), '---\nid: repetido\n---\n');
    writeFileSync(join(fixture, 'memory/reference/dup-b.md'), '---\nid: repetido\n---\n');
    const idx2 = buildIndex(fixture);
    check('MORDE: id repetido vira colisão', idx2.collisions.some((c) => c.id === 'repetido' && c.paths.length === 2), idx2.collisions);
    // Determinismo: mesmo corpus → mesma serialização.
    check('determinístico (mesmo input → mesmo output)', serialize(buildIndex(fixture)) === serialize(buildIndex(fixture)));
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
  const index = buildIndex(root);

  if (args.includes('--json')) { console.log(serialize(index).trimEnd()); return; }

  if (args.includes('--write')) {
    writeFileSync(join(root, OUT), serialize(index), 'utf8');
    console.log(`escrito ${OUT} — ${index.stats.resolved} ids, ${index.stats.unstamped} sem id, ${index.stats.collisions} colisão(ões)`);
    return;
  }

  if (args.includes('--check')) {
    if (index.collisions.length) {
      console.error(`FALHA: ${index.collisions.length} colisão(ões) de id (dois docs reivindicam a mesma identidade):`);
      for (const c of index.collisions) console.error(`  ${c.id}: ${c.paths.join(' , ')}`);
      process.exit(1);
    }
    const current = existsSync(join(root, OUT)) ? readFileSync(join(root, OUT), 'utf8') : '';
    if (current !== serialize(index)) {
      console.error(`FALHA: ${OUT} está em drift vs o corpus — rode --write e commite.`);
      process.exit(1);
    }
    console.log(`OK: índice em dia — ${index.stats.resolved} ids, 0 colisão.`);
    return;
  }

  // dry-run: resumo
  console.log(`corpus: ${index.stats.total} docs`);
  console.log(`  id resolvido: ${index.stats.resolved}`);
  console.log(`  sem id (worklist backfill): ${index.stats.unstamped}`);
  console.log(`  colisões: ${index.stats.collisions}`);
  console.log(`  por origem: ${JSON.stringify(index.stats.by_source)}`);
  if (index.collisions.length) {
    console.log('\ncolisões:');
    for (const c of index.collisions) console.log(`  ${c.id}: ${c.paths.join(' , ')}`);
  }
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try { main(); } catch (error) { console.error(`DOC-ID-INDEX ERROR: ${error.message}`); process.exit(2); }
}
