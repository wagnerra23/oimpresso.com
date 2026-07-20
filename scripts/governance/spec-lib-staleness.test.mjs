#!/usr/bin/env node
// @ts-check
/**
 * spec-lib-staleness.test.mjs — self-test adversarial do núcleo puro (bite/release).
 *
 * Prova, sem git nem FS (datas/séries injetadas), que o sentinela:
 *   · DETECTA transição REAL de versão e IGNORA o None mecânico do squash (a lição
 *     que separa "a lib bumpou" de "o lock quebrou por 1 commit").
 *   · MORDE  — doc refrescado antes do bump, bump além da folga → stale.
 *   · LIBERA — doc refrescado depois do bump (caso real hoje) / lib estável / doc ausente.
 *   · respeita o LIMIAR exato na fronteira (30d não morde; 31d morde).
 *
 * Determinístico (Date.parse UTC) → mesma resposta em qualquer máquina/CI.
 * Uso: node scripts/governance/spec-lib-staleness.test.mjs
 */
import { lastVersionTransition, classifyLibDoc, LIB_DOC_MAP } from './spec-lib-staleness.mjs';

let fails = 0;
const ok = (cond, msg) => { if (cond) console.log(`  ✓ ${msg}`); else { console.error(`  ✗ ${msg}`); fails++; } };

console.log('\n  spec-lib-staleness — self-test do núcleo puro\n');

// ── A) TRANSIÇÃO REAL: v9.0.6→10.0.6, pulando o None do squash (caso nwidart) ──
{
  const r = lastVersionTransition([
    { date: '2026-07-03', version: '10.0.6' },
    { date: '2026-06-08', version: null },     // lock quebrado do squash — NÃO é transição
    { date: '2026-05-28', version: '10.0.6' },
    { date: '2026-04-23', version: 'v9.0.6' },
  ]);
  ok(r.current === '10.0.6' && r.changedAt === '2026-05-28' && r.from === 'v9.0.6',
    `TRANSIÇÃO: v9.0.6→10.0.6 em 05-28, None do squash ignorado (obtido: at=${r.changedAt}, from=${r.from})`);
}

// ── B) ESTÁVEL: só um valor concreto visível → sem transição (horizonte do squash) ─
{
  const r = lastVersionTransition([{ date: '2026-07-03', version: 'v0.6.3' }, { date: '2026-05-28', version: 'v0.6.3' }]);
  ok(r.current === 'v0.6.3' && r.changedAt === null,
    `ESTÁVEL: só v0.6.3 → changedAt null (obtido: at=${r.changedAt})`);
}

// ── C) None mecânico NÃO fabrica transição concreto↔None ──────────────────────
{
  const r = lastVersionTransition([{ date: '2026-07-03', version: '3.13.0' }, { date: '2026-06-08', version: null }, { date: '2026-05-28', version: '3.13.0' }]);
  ok(r.changedAt === null, `SEM FALSO-POSITIVO: concreto→None→concreto (mesmo valor) → sem transição (obtido: at=${r.changedAt})`);
}

// ── D) MORDE: doc anterior ao bump, bump além da folga → stale ─────────────────
{
  const r = classifyLibDoc({ docExists: true, docDate: '2026-04-01', changedAt: '2026-05-28', staleDays: 30 });
  ok(r.evaluated && r.stale && r.gapDays === 57,
    `MORDE: doc 04-01 vs bump 05-28 (57d) → stale (obtido: stale=${r.stale}, gap=${r.gapDays})`);
}

// ── E) LIBERA: doc refrescado DEPOIS do bump → fresco (estado REAL hoje) ───────
{
  const r = classifyLibDoc({ docExists: true, docDate: '2026-07-14', changedAt: '2026-05-28', staleDays: 30 });
  ok(r.evaluated && !r.stale && r.gapDays === -47,
    `LIBERA: doc 07-14 vs bump 05-28 → fresco, gap=-47d (obtido: stale=${r.stale}, gap=${r.gapDays})`);
}

// ── F) LIBERA: lib estável (sem bump visível) + doc velho → NÃO avaliada ───────
// A fixture que impede o falso-positivo de horizonte-de-squash (G3): sem transição,
// nada pra estar atrás, mesmo com doc de 6 meses atrás.
{
  const r = classifyLibDoc({ docExists: true, docDate: '2026-01-01', changedAt: null, staleDays: 30 });
  ok(!r.evaluated && !r.stale,
    `LIBERA: lib estável + doc 01-01 → não avaliada (obtido: evaluated=${r.evaluated}, stale=${r.stale})`);
}

// ── G) NÃO AVALIA: doc mapeado ausente (cobertura ≠ staleness) ────────────────
{
  const r = classifyLibDoc({ docExists: false, docDate: null, changedAt: '2026-05-28', staleDays: 30 });
  ok(!r.evaluated && !r.stale,
    `NÃO AVALIA: doc ausente → evaluated=false (obtido: evaluated=${r.evaluated})`);
}

// ── H) FRONTEIRA do limiar: 30d não morde; 31d morde ─────────────────────────
{
  const at = classifyLibDoc({ docExists: true, docDate: '2026-04-28', changedAt: '2026-05-28', staleDays: 30 }); // 30d
  const over = classifyLibDoc({ docExists: true, docDate: '2026-04-27', changedAt: '2026-05-28', staleDays: 30 }); // 31d
  ok(!at.stale && at.gapDays === 30 && over.stale && over.gapDays === 31,
    `FRONTEIRA: 30d não morde, 31d morde (obtido: 30=${at.stale}/${at.gapDays}, 31=${over.stale}/${over.gapDays})`);
}

// ── I) LIMIAR TUNÁVEL: staleDays=21 morde o que 30 libera ────────────────────
{
  const loose = classifyLibDoc({ docExists: true, docDate: '2026-05-03', changedAt: '2026-05-28', staleDays: 30 }); // 25d
  const tight = classifyLibDoc({ docExists: true, docDate: '2026-05-03', changedAt: '2026-05-28', staleDays: 21 }); // 25d
  ok(!loose.stale && tight.stale,
    `TUNÁVEL: gap=25d → limiar 30 libera, 21 morde (obtido: 30=${loose.stale}, 21=${tight.stale})`);
}

// ── J) MAPA curado: 4 libs, docs .md sem duplicata por lib ────────────────────
{
  const okMap = Object.keys(LIB_DOC_MAP).length === 4 && Object.entries(LIB_DOC_MAP).every(([lib, docs]) =>
    lib.includes('/') && docs.length && new Set(docs).size === docs.length && docs.every((d) => d.endsWith('.md')));
  ok(okMap, `MAPA: 4 libs curadas, docs .md sem duplicata (obtido: ${Object.keys(LIB_DOC_MAP).join(', ')})`);
}

console.log(`\n  ${fails === 0 ? '✅ TODOS os casos passaram' : `❌ ${fails} caso(s) falharam`}\n`);
process.exit(fails === 0 ? 0 : 1);
