#!/usr/bin/env node
// scripts/casos-results-collect.mjs — Coletor de test-results → manifesto por-UC (Salto #2,
//                                     infra do Gate G-7 "Status derivado do verde", ADR 0264).
//
// =====================================================================================
// POR QUE EXISTE
// =====================================================================================
// O G-5 (casos:check) trava a PRESENÇA do `Status:` por UC, mas o valor (✅/🧪/⬜/❌) é
// DECLARADO por humano e pode MENTIR (diz ✅ sem teste verde por trás). O Salto #2 deriva
// o Status do RESULTADO REAL do teste — não por regex de fonte, mas RODANDO a suíte e
// lendo o relatório de máquina (JUnit). Os testes já carregam o UC-id no TÍTULO
// (ex.: `test('UC-06 · gate de etapa…')`), então o `name` do <testcase> entrega o id.
//
//   runners → reporter JUnit → test-results/*.xml → [este coletor] → scripts/casos-test-results.json
//
// Formato único JUnit pra todos os runners (Pest --log-junit · vitest --reporter=junit ·
// Playwright reporter:junit). Um parser só (node:fs + regex, sem dep — idioma dos guards).
//
// VEREDITO por UC (agregado de todos os <testcase> que citam o id no nome):
//   fail  se ALGUM testcase do UC falhou (<failure>/<error>)
//   pass  se ≥1 rodou e NENHUM falhou (skip não conta como pass)
//   skip  se todos os testcases do UC foram pulados (<skipped>)
//
// SEGURANÇA: se NENHUM test-results for encontrado, NÃO sobrescreve o manifesto existente
// (não apaga veredito real por uma rodada vazia) — avisa e sai 0. O gate G-7 lê o
// manifesto COMMITADO (offline, rápido, required-safe) — separar produção (lenta) da
// checagem (rápida) evita o deadlock que o ADR 0261 proíbe.
//
// USO:
//   node scripts/casos-results-collect.mjs                 # lê test-results/, MERGE per-UC no manifesto
//   node scripts/casos-results-collect.mjs --results <dir> # diretório de results alternativo
//   node scripts/casos-results-collect.mjs --no-merge      # sobrescreve o manifesto inteiro (reset consciente)
//   node scripts/casos-results-collect.mjs --seed-empty    # cria manifesto vazio (bootstrap F1)
//
// Refs: ADR 0264 (G-5/G-7) · ADR 0261 (enforcement faseado, não-deadlock) · ADR 0256 (catraca).

import { readFileSync, writeFileSync, existsSync, readdirSync } from 'node:fs';
import { resolve, join, relative } from 'node:path';
import { ucScanRe } from './lib/uc-regex.mjs';

const ROOT = process.cwd();
const MANIFEST_PATH = resolve(ROOT, 'scripts/casos-test-results.json');

const argv = process.argv.slice(2);
const SEED_EMPTY = argv.includes('--seed-empty');
// --no-merge: sobrescreve o manifesto inteiro (reset consciente). Default é MERGE
// per-UC (Onda Q2): runners diferentes (Playwright e2e-gate · Pest financeiro-pest)
// produzem vereditos em workflows separados — overwrite total apagaria a prova do outro.
const NO_MERGE = argv.includes('--no-merge');
const resultsIdx = argv.indexOf('--results');
const RESULTS_DIR = resolve(ROOT, resultsIdx >= 0 ? argv[resultsIdx + 1] : 'test-results');

const norm = (p) => relative(ROOT, p).replace(/\\/g, '/');

// UC-id: regex da fonte ÚNICA scripts/lib/uc-regex.mjs (ucScanRe). Antes este arquivo tinha
// uma cópia {0,3} que driftou do guard ({0,6}-?) e o comentário MENTIA "MESMO regex" — UC
// hifenado (UC-IMP-01/UC-FORJA-01) nunca entrava no manifesto G-7 (bug 2026-06-22).

function walk(dir, filter, acc = []) {
  if (!existsSync(dir)) return acc;
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    const full = join(dir, e.name);
    if (e.isDirectory()) {
      if (e.name === 'node_modules' || e.name === 'vendor' || e.name === '.git') continue;
      walk(full, filter, acc);
    } else if (e.isFile() && filter(full, e.name)) {
      acc.push(full);
    }
  }
  return acc;
}

// Data (YYYY-MM-DD) do testsuite (atributo timestamp), se houver — pra alimentar ran_at.
function suiteDate(xml) {
  const m = xml.match(/<testsuite[^>]*\btimestamp="(\d{4}-\d{2}-\d{2})/);
  return m ? m[1] : null;
}

// Extrai [{ name, status }] de um XML JUnit. status ∈ pass|fail|skip.
function parseTestcases(xml) {
  const out = [];
  // Casa <testcase .../> (self-closing) OU <testcase ...>…</testcase>.
  const re = /<testcase\b([^>]*?)(\/>|>([\s\S]*?)<\/testcase>)/g;
  for (const m of xml.matchAll(re)) {
    const attrs = m[1];
    const inner = m[3] || '';
    const nameMatch = attrs.match(/\bname="([^"]*)"/);
    if (!nameMatch) continue;
    const name = nameMatch[1];
    let status = 'pass';
    if (/<(failure|error)\b/.test(inner)) status = 'fail';
    else if (/<skipped\b/.test(inner)) status = 'skip';
    out.push({ name, status });
  }
  return out;
}

// Agregação por UC com contadores: fail domina; senão pass (≥1) vence skip; senão skip.
function collect() {
  const files = walk(RESULTS_DIR, (full, name) => name.endsWith('.xml'));
  const ucAgg = {}; // uc -> { pass, fail, skip, date }
  let parsedAny = false;

  for (const f of files) {
    let xml;
    try { xml = readFileSync(f, 'utf8'); } catch { continue; }
    parsedAny = true;
    const date = suiteDate(xml);
    for (const { name, status } of parseTestcases(xml)) {
      const ids = new Set([...name.matchAll(ucScanRe())].map((x) => x[0].toUpperCase()));
      for (const uc of ids) {
        const a = (ucAgg[uc] ??= { pass: 0, fail: 0, skip: 0, date: null });
        a[status] += 1;
        if (date && (!a.date || date > a.date)) a.date = date;
      }
    }
  }

  // veredito final por UC.
  const ucs = {};
  for (const [uc, a] of Object.entries(ucAgg)) {
    const verdict = a.fail > 0 ? 'fail' : a.pass > 0 ? 'pass' : 'skip';
    ucs[uc] = { verdict, ran_at: a.date, tests: a.pass + a.fail + a.skip };
  }

  return { ucs, parsedAny, sources: files.map(norm) };
}

function writeManifest(ucs, sources) {
  const stats = { ucs: Object.keys(ucs).length, pass: 0, fail: 0, skip: 0 };
  for (const { verdict } of Object.values(ucs)) stats[verdict] += 1;
  const out = {
    _meta: {
      generated_at: new Date().toISOString(),
      gate: 'casos status derivado (ADR 0264 G-7 — Status por UC vem do veredito real do teste)',
      sources,
      stats,
      nota: 'Veredito por-UC derivado dos relatórios JUnit (test-results/). Lido offline pelo casos:check G-7. Regerar quando a suíte rodar: npm run casos:results. UC sem entrada aqui = sem prova capturada (G-7 marca ✅ declarado como unverified).',
      refs: ['ADR 0264', 'ADR 0261', 'ADR 0256'],
    },
    ucs,
  };
  writeFileSync(MANIFEST_PATH, JSON.stringify(out, null, 2) + '\n');
  return stats;
}

function main() {
  if (SEED_EMPTY) {
    const stats = writeManifest({}, []);
    console.log(`✅ Manifesto SEMENTE (vazio) gravado → ${norm(MANIFEST_PATH)} (${stats.ucs} UCs).`);
    process.exit(0);
  }

  const { ucs, parsedAny, sources } = collect();

  if (!parsedAny) {
    console.error(
      `⚠️  Nenhum test-results JUnit em ${norm(RESULTS_DIR)}/ — manifesto NÃO sobrescrito ` +
        `(preserva veredito real). Rode a suíte com reporter JUnit antes (ex.: npm run e2e:check).`,
    );
    process.exit(0); // não-fatal: rodada vazia não é erro, só não atualiza.
  }

  // MERGE per-UC (Onda Q2): o manifesto guarda o ÚLTIMO veredito conhecido POR UC
  // (ran_at carrega o frescor; o G-7 stale-results pega tela-mudou-depois-do-teste).
  // UC ausente da rodada atual preserva o veredito anterior — runner parcial
  // (ex.: só o Pest do Financeiro) não apaga a prova do outro (Playwright e2e).
  let preserved = 0;
  if (!NO_MERGE && existsSync(MANIFEST_PATH)) {
    try {
      const prev = JSON.parse(readFileSync(MANIFEST_PATH, 'utf8'))?.ucs || {};
      for (const [uc, entry] of Object.entries(prev)) {
        if (!(uc in ucs)) {
          ucs[uc] = entry;
          preserved += 1;
        }
      }
    } catch {
      // manifesto anterior ilegível → segue só com a rodada atual (igual --no-merge)
    }
  }

  const stats = writeManifest(ucs, sources);
  console.log(
    `✅ Manifesto gravado: ${stats.ucs} UCs (${stats.pass} pass · ${stats.fail} fail · ${stats.skip} skip) ` +
      (preserved ? `— ${preserved} preservado(s) de rodadas anteriores (merge per-UC; reset: --no-merge) ` : '') +
      `de ${sources.length} relatório(s) → ${norm(MANIFEST_PATH)}`,
  );
  process.exit(0);
}

main();
