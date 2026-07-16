#!/usr/bin/env node
// coverage-compute.mjs — write-side do coverage_pct (SDD P07 · ADR 0275 §2 fonte
// canônica `pest --coverage` na nightly · catraca C2). IRMÃO de floor-compute.mjs:
// mesmo transporte (branch órfã governance/nightly-floor + deploy key + push
// [skip ci]) e mesma filosofia anti-stale (nunca mente 0).
//
// Lê os clover.xml das últimas N nightlies VÁLIDAS do CT100 (run com clover.xml
// presente e parseável; run sem clover — ex pcov ainda não na imagem — NÃO conta)
// e computa coverage_pct = line-rate do clover MAIS RECENTE válido (a medição é um
// número único, não uma interseção como o floor — mas mantemos a janela pra trazer
// o histórico no detail e exigir ≥1 clover válido). 0 clovers → coverage_pct null →
// o read-side (sdd-scorecard.mjs measureCoverage) trata como not_yet_measured.
//
// Por que line-rate do clover do CT100 (suíte INTEIRA), nunca do lane sqlite curado:
// ADR 0275:68 — coverage só é honesto na fonte que roda a suíte inteira; medir no
// subconjunto sqlite daria número falso-baixo ("métrica de forma" proibida).
//
// Determinístico: para o MESMO conjunto de runs, mesmo output (sem `Date.now()` no
// corpo — `computed_at` é o ts do run, fato imutável da história, igual ao floor).
// Uso: node scripts/tests/coverage-compute.mjs [--runs <dir>] [--window N] [--out <file>]
import { readdirSync, readFileSync, existsSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const arg = (k, d) => { const i = process.argv.indexOf(k); return i >= 0 ? process.argv[i + 1] : d; };
const RUNS = arg('--runs', '/opt/oimpresso-fullsuite/runs');
const WINDOW = Math.max(1, parseInt(arg('--window', '3'), 10) || 3);
const OUT = arg('--out', join(process.cwd(), 'governance', 'nightly-coverage.json'));

// Extrai (covered, total) de statements do clover. O bloco canônico PHPUnit/pcov é
// o <metrics ...> agregado DENTRO de <project> (o ÚLTIMO <metrics> do arquivo, que
// soma todos os <file>). Lê via regex (sem dep XML): pega o último match de
// `<metrics ... statements="N" ... coveredstatements="M" ...>` (atributos em
// qualquer ordem). line-rate = coveredstatements / statements.
export function parseCloverLineRate(xml) {
  // V4 (nunca mente baixo): guard anti-truncamento. Um coverage killed mid-flush (o
  // timeout -k mata o pcov no teto — exit 124/137) deixa um clover TRUNCADO cujo ULTIMO
  // <metrics> e um agregado PARCIAL de <file>, nao o <metrics> do <project> — daria um
  // coverage_pct falso-baixo que a catraca C2 (so-sobe) travaria pra sempre. pcov/PHPUnit
  // so escrevem o </coverage> de fechamento no flush COMPLETO; sua ausencia = clover
  // incompleto -> nao conta (read-side fica not_yet_measured, honesto).
  if (!/<\/coverage>\s*$/.test(xml)) return null;
  const blocks = [...xml.matchAll(/<metrics\b[^>]*>/g)].map((m) => m[0]);
  if (!blocks.length) return null;
  const last = blocks[blocks.length - 1]; // <metrics> do <project> (agregado)
  const attr = (name) => {
    const m = last.match(new RegExp(`\\b${name}="(\\d+)"`));
    return m ? parseInt(m[1], 10) : null;
  };
  const total = attr('statements');
  const covered = attr('coveredstatements');
  if (total === null || covered === null || total <= 0) return null;
  return { covered, total, pct: Math.round((1000 * covered) / total) / 10 };
}

// runs com clover válido, em ordem cronológica (nome = YYYYMMDD-HHMMSS — igual floor)
export function validCoverageRuns(runsDir = RUNS) {
  if (!existsSync(runsDir)) return [];
  const names = readdirSync(runsDir, { withFileTypes: true })
    .filter((e) => e.isDirectory() && /^\d{8}-\d{6}$/.test(e.name))
    .map((e) => e.name).sort();
  const out = [];
  for (const name of names) {
    const cp = join(runsDir, name, 'clover.xml');
    if (!existsSync(cp)) continue; // run sem clover (pcov ausente/coverage off) não conta
    let lr;
    try { lr = parseCloverLineRate(readFileSync(cp, 'utf8')); } catch { continue; }
    if (!lr) continue; // clover malformado/sem statements → não conta
    let sha = null;
    const lp = join(runsDir, name, 'run.log');
    if (existsSync(lp)) { const m = readFileSync(lp, 'utf8').match(/sha=([0-9a-f]{7,40})/); if (m) sha = m[1]; }
    out.push({ ts: name, sha, ...lr });
  }
  return out;
}

// coverage = line-rate do clover mais recente válido na janela (≥1 obrigatório)
export function computeCoverage(runs, window = WINDOW) {
  const w = runs.slice(-window);
  if (w.length < 1) {
    return {
      schema: 'nightly-coverage/v1 (SDD P07 · ADR 0275)', coverage_pct: null,
      covered: null, total: null, runs: [], computed_at: null, measured_of: 0,
      note: 'aguardando >=1 nightly com clover valido (pcov na imagem CT100); read-side trata como not_yet_measured',
    };
  }
  const latest = w[w.length - 1];
  return {
    schema: 'nightly-coverage/v1 (SDD P07 · ADR 0275)',
    coverage_pct: latest.pct,
    covered: latest.covered,
    total: latest.total,
    runs: w.map((r) => ({ sha: r.sha, ts: r.ts, coverage_pct: r.pct, covered: r.covered, total: r.total })),
    computed_at: latest.ts,
    measured_of: w.length,
    note: `coverage_pct = line-rate do clover mais recente valido (suite inteira CT100 · ${w.length} run(s) na janela)`,
  };
}

// CLI (só quando executado direto; importável p/ teste)
import { realpathSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
const isMain = (() => { try { return realpathSync(process.argv[1]) === fileURLToPath(import.meta.url); } catch { return false; } })();
if (isMain) {
  const cov = computeCoverage(validCoverageRuns(RUNS), WINDOW);
  writeFileSync(OUT, JSON.stringify(cov, null, 2) + '\n', 'utf8');
  console.log(`coverage-compute → ${OUT}: coverage_pct=${cov.coverage_pct} (measured_of=${cov.measured_of})`);
}
