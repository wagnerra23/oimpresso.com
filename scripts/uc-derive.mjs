#!/usr/bin/env node
// scripts/uc-derive.mjs — Auto-derivador de vínculo UC↔teste (PoC read-only, determinístico)
// =====================================================================================
// POR QUE EXISTE
// =====================================================================================
// O casos-coverage-guard (G-2) prova cobertura por String.includes — comentário/skip
// contam verde (audit 2026-06-22: 28% honestidade real; 50 UCs, 72% teatro). E o regex
// do guard (UC-[A-Z]{0,3}) é CEGO a UC-FORJA-* (5 letras). A dívida NÃO é falta de teste
// (os it() existem com asserts), é falta de RASTREABILIDADE: o UC-id não está no título
// do teste e o manifesto não carrega a chave.
//
// Este tool DERIVA o vínculo deterministicamente (lei 6 da arquitetura correta):
//   - lê os it()/test() VIVOS (não comentário/skip) e casa o UC-id no TÍTULO
//   - cruza com o manifesto (verdict REAL do JUnit) — NÃO inventa verdict
//   - classifica cada UC: execucao-backed / live-sem-verdict / string-backed / orfao
//   - --propose: pros não-ancorados, SUGERE qual it() anotar (read-only, pra review)
//
// SEGURANÇA (respostas honestas — sessão 2026-06-22):
//   APRENDE? NÃO. Determinístico, σ=0: mesma entrada → mesma saída. Sem IA, sem rede.
//            (A autoria fuzzy do mapeamento é etapa humana/revisada; este tool só ENXERGA.)
//   SEGURO?  SIM. READ-ONLY — não escreve nada. Só lê metadado de teste + manifesto.
//            Zero business_id, zero PII, zero runtime. Tier-0 intocado.
// =====================================================================================

import { readFileSync, existsSync, readdirSync } from 'node:fs';
import { resolve, join, relative } from 'node:path';
import { ucScanRe, ucHeadRe } from './lib/uc-regex.mjs';

const ROOT = process.cwd();
const argv = process.argv.slice(2);
const getFlag = (n) => { const i = argv.indexOf(n); return i >= 0 ? argv[i + 1] : null; };
const MODULE = getFlag('--module');
const PROPOSE = argv.includes('--propose');

const PAGES = resolve(ROOT, 'resources/js/Pages');
const MANIFEST_PATH = resolve(ROOT, 'scripts/casos-test-results.json');
const TEST_DIRS = ['Modules', 'tests', 'app', 'e2e'];
// Regex de UC-id vem da fonte ÚNICA (scripts/lib/uc-regex.mjs · ADR 0264) pra não drifar:
//   ucScanRe() → instância /g fresca (matchAll no corpo + .test() pontual)
//   ucHeadRe() → âncora ^(...) pra extrair o UC declarado do heading do casos.md

if (!MODULE) {
  console.error('uso: node scripts/uc-derive.mjs --module <Mod> [--propose]');
  process.exit(2);
}

const norm = (p) => relative(ROOT, p).replace(/\\/g, '/');

function walk(dir, filter, acc = []) {
  if (!existsSync(dir)) return acc;
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    const full = join(dir, e.name);
    if (e.isDirectory()) {
      if (['node_modules', 'vendor', '.git', '.claude'].includes(e.name)) continue;
      walk(full, filter, acc);
    } else if (e.isFile() && filter(full, e.name)) acc.push(full);
  }
  return acc;
}

// ---- 1. casos.md do módulo: UCs declarados + Status + teste intencionado ----
function declaredUcs() {
  const dir = join(PAGES, MODULE);
  const out = [];
  for (const f of walk(dir, (_f, n) => n.endsWith('.casos.md'))) {
    const content = readFileSync(f, 'utf8');
    for (const block of content.split(/^##\s+/m).slice(1)) {
      const head = block.match(ucHeadRe());
      if (!head) continue;
      const glyph = block.match(/Status\s*[:：]\s*[^\n]*?([✅❌🧪⬜])/u);
      const testM = block.match(/Teste[^A-Za-z0-9]*[`'"]?([A-Za-z][A-Za-z0-9_]+)/);
      out.push({ uc: head[1].toUpperCase(), status: glyph ? glyph[1] : '–', intended: testM ? testM[1] : null, casos: norm(f) });
    }
  }
  return out;
}

// ---- 2. índice de TÍTULOS de teste vivos (exclui comentário/skip) ----
function liveTitleIndex() {
  const idx = [];
  for (const d of TEST_DIRS) {
    for (const f of walk(resolve(ROOT, d), (_f, n) => /Test\.php$/.test(n) || /\.(test|spec)\.[tj]sx?$/.test(n))) {
      let lines;
      try { lines = readFileSync(f, 'utf8').split('\n'); } catch { continue; }
      lines.forEach((line, i) => {
        const t = line.trim();
        if (/^(\/\/|#|\*|\/\*)/.test(t)) return;                          // comentário → não conta
        const matches = line.match(ucScanRe());
        if (!matches) return;
        const skip = /\b(it|test|describe)\s*\.\s*(skip|todo)\b|\bxit\b|\bxtest\b|\bxdescribe\b/.test(line);
        const isTitle = /\b(it|test|describe)\s*\(\s*['"`]/.test(line)     // it('...'), test("...")
          || /function\s+test/i.test(line)                                // PHPUnit function test_xxx
          || /#\[Test\]/.test(line);                                      // PHP attribute #[Test]
        for (const m of matches) idx.push({ uc: m.toUpperCase(), file: norm(f), line: i + 1, live: isTitle && !skip, skip, snippet: t.slice(0, 110) });
      });
    }
  }
  return idx;
}

// ---- 3. manifesto: verdict REAL (jamais inventado) ----
function manifestUcs() {
  if (!existsSync(MANIFEST_PATH)) return {};
  try { return JSON.parse(readFileSync(MANIFEST_PATH, 'utf8')).ucs || {}; } catch { return {}; }
}

// ---- 4. derivar + classificar ----
const declared = declaredUcs();
const index = liveTitleIndex();
const man = manifestUcs();
const byUc = new Map();
for (const e of index) { if (!byUc.has(e.uc)) byUc.set(e.uc, []); byUc.get(e.uc).push(e); }

const rows = declared.map((d) => {
  const refs = byUc.get(d.uc) || [];
  const live = refs.filter((r) => r.live);
  const verdict = man[d.uc] && man[d.uc].verdict ? man[d.uc].verdict : 'ausente';
  let klass;
  if (live.length && verdict === 'pass') klass = 'execucao-backed';
  else if (live.length) klass = 'live-sem-verdict';
  else if (refs.length) klass = 'string-backed';
  else klass = 'orfao';
  return { ...d, verdict, live, refs, klass };
});

// ---- output ----
const ICON = { 'execucao-backed': 'OK ', 'live-sem-verdict': '~  ', 'string-backed': 'TEA', orfao: 'ORF' };
console.log(`\n# Auto-derivador (read-only · determinístico) — módulo ${MODULE}\n`);
console.log(`UCs declarados: ${rows.length}\n`);
for (const r of rows) {
  const where = r.live.length
    ? `${r.live[0].file}:${r.live[0].line}`
    : (r.refs.length ? `${r.refs[0].file}:${r.refs[0].line} (${r.refs[0].skip ? 'skip' : 'comentário/string'})` : '—');
  console.log(`[${ICON[r.klass]}] ${r.uc.padEnd(13)} status:${r.status} verdict:${String(r.verdict).padEnd(7)} ${r.klass.padEnd(16)} ${where}`);
}
const n = (k) => rows.filter((r) => r.klass === k).length;
const honest = rows.length ? Math.round((n('execucao-backed') / rows.length) * 100) : 0;
console.log(`\nResumo: ${n('execucao-backed')} execução-backed · ${n('live-sem-verdict')} live-sem-verdict · ${n('string-backed')} string-backed · ${n('orfao')} órfão · honestidade ${honest}%`);

// ---- --propose: sugestões read-only pros não-ancorados ----
if (PROPOSE) {
  const pend = rows.filter((r) => r.klass !== 'execucao-backed');
  console.log(`\n## Propostas (read-only — NADA é escrito)\n`);
  if (!pend.length) console.log('(todos execução-backed — nada a propor)');
  for (const r of pend) {
    if (!r.intended) { console.log(`• ${r.uc}: sem "Teste:" no casos.md — autor precisa apontar o teste.`); continue; }
    const cand = [];
    for (const d of TEST_DIRS) {
      for (const f of walk(resolve(ROOT, d), (_f, nm) => nm.includes(r.intended) && (/Test\.php$/.test(nm) || /\.(test|spec)\.[tj]sx?$/.test(nm)))) {
        readFileSync(f, 'utf8').split('\n').forEach((line, i) => {
          const t = line.trim();
          if (/^(\/\/|#|\*)/.test(t)) return;
          const titleM = line.match(/\b(it|test)\s*\(\s*['"`]([^'"`]{4,90})['"`]/);
          if (titleM && !ucScanRe().test(titleM[2])) cand.push(`${norm(f)}:${i + 1}  ${titleM[1]}('${titleM[2].slice(0, 68)}')`);
        });
      }
    }
    console.log(`• ${r.uc} (${r.klass}) → casos.md cita \`${r.intended}\`. Candidatos a receber o UC-id no título:`);
    if (!cand.length) console.log(`    (nenhum it()/test() vivo sem UC-id achado em "${r.intended}" — conferir o nome no casos.md)`);
    for (const c of cand.slice(0, 5)) console.log(`    ↳ ${c}`);
  }
}
