#!/usr/bin/env node
// detectar-telas.test.mjs — anti-DRIFT do ALIAS map de detectar-telas.mjs.
//
// Por que existe (risco catalogado · auditoria 2026-06-24, Onda B item B1):
// o ALIAS de detectar-telas.mjs (vendas-create-page.jsx → Sells/Create.tsx etc) é
// uma 2ª fonte de mapeamento mockup→alvo, PARALELA aos charters (que declaram o
// mesmo par via `component:`/`repo_alvo:`/`page:`). Duas fontes do MESMO fato =
// elas podem DIVERGIR em silêncio: alguém edita o charter (Compras→Purchases) e o
// ALIAS continua apontando o velho, ou cria charter com `component: vendas-page.jsx`
// resolvendo pra um alvo que CONTRADIZ o ALIAS. Aí o gate de import resolve a tela
// pra dois lugares conforme o caminho que casa primeiro — bug exatamente da classe
// que o detectar-telas.mjs nasceu pra matar ("0 telas perdidas em silêncio").
//
// Este teste é a DEFESA MAIS BARATA: NÃO deriva o ALIAS dos charters (seria
// invasivo — mudaria o mecanismo). Só CRUZA as duas fontes e FALHA quando elas
// discordam. Duas asserções sobre o ALIAS REAL (lido do source, fonte única):
//
//   A) cada `alvo` do ALIAS EXISTE no repo (existsSync) — alias morto = drift.
//   B) nenhum charter (repo + staging opcional) que cite, em `component:`, um
//      mockup que casa o `re` de uma entry do ALIAS, resolve pra um alvo
//      DIFERENTE do `alvo` daquela entry — charter ≠ ALIAS = drift, FALHA.
//
// + META-TEST (prova que o gate MORDE): fixtures sintéticas em tmp provam que a
//   checagem B falha quando um charter contradiz o ALIAS e passa quando concorda,
//   e que a checagem A falha quando o alvo do ALIAS não existe. (Convenção do
//   projeto: o self-test prova que a sentinela morde — cf. charter-refs.test.mjs.)
//
// Node puro, sem deps/DB/rede. Rodar: node prototipo-ui/detectar-telas.test.mjs
//   exit 0 = ALIAS e charters concordam · exit 1 = drift (ou meta-test quebrou).

import { readFileSync, existsSync, mkdtempSync, mkdirSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve, basename, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url)); // prototipo-ui/
const REPO_ROOT = resolve(HERE, '..');
const SCRIPT = join(HERE, 'detectar-telas.mjs');

let fails = 0;
const check = (name, cond, extra = '') => {
  console.log(`${cond ? '[OK]' : '[FAIL]'} ${name}${cond ? '' : '  ← ' + extra}`);
  if (!cond) fails++;
};

// ── parsers ESPELHADOS de detectar-telas.mjs (mesmas regex, fidelidade exata) ──
// São cópias deliberadas: o cross-check precisa resolver o alvo de um charter do
// MESMO jeito que o script faz (senão acusaria/perdoaria drift errado). A âncora
// abaixo (FIXTURE) prova que esta cópia reproduz o byMockup do script — se o
// script mudar a heurística sem atualizar aqui, a âncora quebra e avisa.
function frontmatter(src) {
  if (!src) return {};
  const m = src.match(/^---\r?\n([\s\S]*?)\r?\n---/);
  if (!m) return {};
  const fm = {};
  for (const line of m[1].split(/\r?\n/)) {
    const mm = line.match(/^([a-z_]+):\s*(.*)$/i);
    if (mm) fm[mm[1].toLowerCase()] = mm[2].trim();
  }
  return fm;
}
function extractRepoPath(text) {
  if (!text) return null;
  let m = text.match(/resources\/js\/Pages\/[\w./-]+\.tsx/);
  if (m) return m[0];
  m = text.match(/\b([A-Z][\w]+(?:\/[A-Z][\w]+)+)\b/);
  if (m) return `resources/js/Pages/${m[1]}.tsx`;
  return null;
}
function extractMockupFiles(text) {
  if (!text) return [];
  const cleaned = text.replace(/resources\/js\/Pages\/[\w./-]+/g, ' ');
  return [...cleaned.matchAll(/[\w.-]+\.jsx/g)].map((m) => m[0]).filter((t) => !/^index\.jsx$/i.test(t));
}
// alvo que o charter declara (mesma precedência do script: repo_alvo → component → page)
function charterAlvo(fm) {
  return extractRepoPath(fm.repo_alvo) || extractRepoPath(fm.component) || extractRepoPath(fm.page);
}

// ── walk mínimo (mesma allowlist de skip do script) ───────────────────────────
function walk(dir, out = []) {
  let entries;
  try { entries = require_readdir(dir); } catch { return out; }
  const skip = new Set(['node_modules', '.git', '_arquivo', '_BACKUP-NAO-USAR', 'scraps', 'screenshots', 'uploads', 'assets']);
  for (const e of entries) {
    if (skip.has(e.name)) continue;
    const full = join(dir, e.name);
    if (e.isDirectory()) walk(full, out);
    else out.push(full);
  }
  return out;
}
// readdirSync withFileTypes sem importar fs/promises (mantém o teste síncrono e simples)
import { readdirSync } from 'node:fs';
function require_readdir(dir) { return readdirSync(dir, { withFileTypes: true }); }

// ── extrai o ALIAS REAL do source (fonte ÚNICA — não re-declara o dicionário) ──
// Parseia o bloco `const ALIAS = [ ... ];` por entry `{ re: /.../i, alvo: '...', tela: '...' }`.
function parseAliasFromSource(srcPath) {
  const src = readFileSync(srcPath, 'utf8');
  const block = src.match(/const\s+ALIAS\s*=\s*\[([\s\S]*?)\];/);
  if (!block) throw new Error('não achei `const ALIAS = [...]` em ' + srcPath);
  const entries = [];
  const reEntry = /\{\s*re:\s*(\/[^/]+\/[a-z]*)\s*,\s*alvo:\s*'([^']+)'\s*,\s*tela:\s*'([^']+)'\s*\}/g;
  let m;
  while ((m = reEntry.exec(block[1]))) {
    const reLit = m[1].match(/^\/(.*)\/([a-z]*)$/);
    entries.push({ re: new RegExp(reLit[1], reLit[2]), reSrc: m[1], alvo: m[2], tela: m[3] });
  }
  return entries;
}

// ── índice mockup→alvo a partir dos charters (mesma lógica do byMockup do script) ──
function buildByMockup(roots) {
  const byMockup = new Map(); // mockup .jsx → { alvo, charter }
  for (const root of roots) {
    if (!existsSync(root)) continue;
    for (const cf of walk(root)) {
      if (!cf.endsWith('.charter.md')) continue;
      const fm = frontmatter(readFileSync(cf, 'utf8'));
      const alvo = charterAlvo(fm);
      if (!alvo) continue;
      for (const mk of extractMockupFiles(fm.component)) {
        if (!byMockup.has(mk)) byMockup.set(mk, { alvo, charter: cf });
      }
    }
  }
  return byMockup;
}

// ─────────────────────────────────────────────────────────────────────────────
// PARTE 0 — ÂNCORA: o parser do teste reproduz o byMockup do script no FIXTURE.
// Garante que as cópias das regex acima não driftaram do detectar-telas.mjs.
// ─────────────────────────────────────────────────────────────────────────────
{
  const fxStaging = join(HERE, 'fixtures', 'detectar-telas', 'staging');
  const fxRepoPages = join(HERE, 'fixtures', 'detectar-telas', 'repo', 'resources', 'js', 'Pages');
  const by = buildByMockup([fxStaging, fxRepoPages]);
  const v = by.get('vendas-page.jsx');
  check('âncora: fixture Vendas.charter.md → vendas-page.jsx resolve Sells/Index',
    v && v.alvo === 'resources/js/Pages/Sells/Index.tsx', JSON.stringify(v));
  check('âncora: vendas-create-page.jsx NÃO tem charter (fica pro ALIAS, P0 lock)',
    !by.has('vendas-create-page.jsx'), JSON.stringify([...by.keys()]));
}

// ─────────────────────────────────────────────────────────────────────────────
// PARTE 1 — CRUZAMENTO REAL: ALIAS do source × charters do repo (+ staging?)
// ─────────────────────────────────────────────────────────────────────────────
const ALIAS = parseAliasFromSource(SCRIPT);
check('source: ALIAS parseado (≥1 entry)', ALIAS.length >= 1, `len=${ALIAS.length}`);

// roots de charter pra cruzar: repo Pages sempre; staging só se passado por flag (bundle vive fora do repo).
const stagingArg = (() => { const i = process.argv.indexOf('--staging'); return i >= 0 ? process.argv[i + 1] : null; })();
const charterRoots = [join(REPO_ROOT, 'resources', 'js', 'Pages')];
if (stagingArg) {
  const st = existsSync(join(stagingArg, 'project')) ? join(stagingArg, 'project') : stagingArg;
  if (existsSync(st)) charterRoots.push(st);
}
const byMockupReal = buildByMockup(charterRoots);

// A) cada alvo do ALIAS existe no repo
for (const a of ALIAS) {
  const abs = join(REPO_ROOT, a.alvo);
  check(`A · alvo do ALIAS existe: ${a.reSrc} → ${a.alvo}`, existsSync(abs),
    'alvo declarado no ALIAS não existe no repo — alias morto ou path errado');
}

// B) nenhum charter contradiz o ALIAS no mesmo mockup
for (const [mockup, { alvo: charterTarget, charter }] of byMockupReal) {
  const a = ALIAS.find((x) => x.re.test(mockup));
  if (!a) continue; // mockup não-aliasado: charter é a única fonte, sem conflito possível
  const charterRel = relative(REPO_ROOT, charter).replace(/\\/g, '/');
  check(`B · charter concorda com ALIAS p/ ${mockup}`,
    charterTarget === a.alvo,
    `ALIAS diz ${a.alvo}, mas ${charterRel} (component:) resolve ${charterTarget} — DRIFT: as duas fontes do mesmo par discordam`);
}

// C) FONTE ÚNICA (endurece B): o ALIAS é fallback SÓ pra mockup que NENHUM charter mapeia.
//    B pega DISCORDÂNCIA; C pega COEXISTÊNCIA — se um charter NOMEIA o mockup nos campos
//    que ESTABELECEM o link bundle→tela (`component:` ou `bundle_source:` — NÃO
//    related_prototype/visual_source, que são design-ref e o meta-test b3 já ignora),
//    a entry do ALIAS é REDUNDANTE: 2ª fonte do mesmo fato, mesmo concordando (e a
//    redundância diverge no futuro). Coexistência = 0 hoje → baseline limpo, morde o futuro.
function charterMappedMockups(roots) {
  const set = new Map(); // mockup .jsx → charter que o mapeia (component|bundle_source)
  for (const root of roots) {
    if (!existsSync(root)) continue;
    for (const cf of walk(root)) {
      if (!cf.endsWith('.charter.md')) continue;
      const fm = frontmatter(readFileSync(cf, 'utf8'));
      for (const mk of [...extractMockupFiles(fm.component), ...extractMockupFiles(fm.bundle_source)]) {
        if (!set.has(mk)) set.set(mk, cf);
      }
    }
  }
  return set;
}
const charterMapped = charterMappedMockups(charterRoots);
for (const a of ALIAS) {
  const coberto = [...charterMapped.keys()].find((mk) => a.re.test(mk));
  check(`C · fonte única: ALIAS ${a.reSrc} não coexiste com charter`,
    !coberto,
    coberto ? `charter ${relative(REPO_ROOT, charterMapped.get(coberto)).replace(/\\/g, '/')} mapeia ${coberto} (component/bundle_source) — REDUNDANTE: mova o link pro charter e apague a entry do ALIAS (1 fato = 1 fonte)` : '');
}

// ─────────────────────────────────────────────────────────────────────────────
// PARTE 2 — META-TEST: prova que o cross-check MORDE e LIBERA (sentinela honesta).
// Monta repos-fixture em tmp e roda buildByMockup + as duas checagens à mão.
// ─────────────────────────────────────────────────────────────────────────────
function mkRepo(charters) {
  const root = mkdtempSync(join(tmpdir(), 'dettelas-'));
  for (const [rel, body] of Object.entries(charters)) {
    const abs = join(root, rel);
    mkdirSync(dirname(abs), { recursive: true });
    writeFileSync(abs, body);
  }
  return root;
}
// reproduz a regra B isolada contra 1 ALIAS sintético e 1 conjunto de charters
function crossCheckB(repoRoot, aliasEntry) {
  const by = buildByMockup([join(repoRoot, 'resources', 'js', 'Pages')]);
  for (const [mockup, { alvo }] of by) {
    if (aliasEntry.re.test(mockup) && alvo !== aliasEntry.alvo) return { drift: true, mockup, alvo };
  }
  return { drift: false };
}

const aliasSynt = { re: /^foo-page\.jsx$/i, alvo: 'resources/js/Pages/Foo/Index.tsx' };

// (b1) charter contradiz o ALIAS (mesmo mockup → alvo diferente) → MORDE
{
  const root = mkRepo({
    'resources/js/Pages/Bar/Index.charter.md':
      '---\npage: /bar\ncomponent: foo-page.jsx\nrepo_alvo: resources/js/Pages/Bar/Index.tsx\n---\n# contradiz o ALIAS\n',
  });
  const r = crossCheckB(root, aliasSynt);
  check('meta B (b1) MORDE: charter aponta foo-page.jsx p/ alvo ≠ ALIAS', r.drift === true, JSON.stringify(r));
}
// (b2) charter concorda com o ALIAS → LIBERA
{
  const root = mkRepo({
    'resources/js/Pages/Foo/Index.charter.md':
      '---\npage: /foo\ncomponent: foo-page.jsx\nrepo_alvo: resources/js/Pages/Foo/Index.tsx\n---\n# concorda com o ALIAS\n',
  });
  const r = crossCheckB(root, aliasSynt);
  check('meta B (b2) LIBERA: charter concorda com o ALIAS', r.drift === false, JSON.stringify(r));
}
// (b3) charter cita o mockup só em blueprint_cowork/visual_source (NÃO em component:) → ignorado, LIBERA
//      (espelha o caso real: dezenas de charters citam *-page.jsx em blueprint_cowork, e isso NÃO é alvo)
{
  const root = mkRepo({
    'resources/js/Pages/Baz/Index.charter.md':
      '---\npage: /baz\ncomponent: resources/js/Pages/Baz/Index.tsx\nblueprint_cowork: prototipo-ui/cowork/foo-page.jsx\n---\n# só blueprint, não component\n',
  });
  const r = crossCheckB(root, aliasSynt);
  check('meta B (b3) LIBERA: mockup em blueprint_cowork (≠ component:) é ignorado', r.drift === false, JSON.stringify(r));
}
// (a1) checagem A morde quando o alvo do ALIAS não existe
{
  const root = mkRepo({ 'resources/js/Pages/Real/Index.tsx': 'export default () => null;\n' });
  const existeReal = existsSync(join(root, 'resources/js/Pages/Real/Index.tsx'));
  const existeFantasma = existsSync(join(root, aliasSynt.alvo)); // Foo/Index.tsx nunca criado
  check('meta A (a1) MORDE: alvo inexistente do ALIAS é flagrado', existeReal && !existeFantasma,
    `real=${existeReal} fantasma=${existeFantasma}`);
}
// (c1) charter MAPEIA (via bundle_source) um mockup que o ALIAS também tem → MORDE (redundante)
{
  const root = mkRepo({
    'resources/js/Pages/Foo/Index.charter.md':
      '---\npage: /foo\ncomponent: resources/js/Pages/Foo/Index.tsx\nbundle_source: foo-page.jsx\n---\n# bundle_source cobre o mockup do ALIAS\n',
  });
  const coberto = [...charterMappedMockups([join(root, 'resources/js/Pages')]).keys()].some((mk) => aliasSynt.re.test(mk));
  check('meta C (c1) MORDE: charter bundle_source cobre mockup do ALIAS (redundante)', coberto === true, `coberto=${coberto}`);
}
// (c2) charter NÃO cita o mockup do ALIAS → LIBERA (ALIAS é fallback legítimo, coexistência=0)
{
  const root = mkRepo({
    'resources/js/Pages/Bar/Index.charter.md':
      '---\npage: /bar\ncomponent: resources/js/Pages/Bar/Index.tsx\n---\n# não cita foo-page.jsx\n',
  });
  const coberto = [...charterMappedMockups([join(root, 'resources/js/Pages')]).keys()].some((mk) => aliasSynt.re.test(mk));
  check('meta C (c2) LIBERA: charter não cobre mockup do ALIAS', coberto === false, `coberto=${coberto}`);
}

console.log('');
if (fails) { console.error(`✗ detectar-telas.test.mjs: ${fails} asserção(ões) falharam (drift ALIAS↔charter ou meta-test).`); process.exit(1); }
console.log('✓ detectar-telas.test.mjs: ALIAS e charters concordam · sentinela morde · 0 drift.');
process.exit(0);
