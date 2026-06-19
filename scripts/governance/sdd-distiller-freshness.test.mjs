#!/usr/bin/env node
// Meta-teste do read-side de distiller_freshness (ADR 0291 D-D · peça 3 do keystone).
// Importa measureDistillerFreshness de sdd-scorecard.mjs (guard isMain garante que o
// import NÃO dispara o scorecard inteiro) e prova o padrão anti-stale + determinístico:
//   A) zero portas carimbadas        → not_yet_measured (honesto; distiller não rodou)
//   B) ≥1 carimbada + 1 atrasada      → measured, value = nº atrás do doc mais novo (>7d)
//   C) carimbada e fresca             → não conta como stale
//   D) porta SEM carimbo              → entra no detail (cobertura pendente), não stale
//   E) reqDir ausente                 → not_yet_measured
// `newestDocDate` é injetado (mapa) → sem git/FS real, determinístico.
// Uso: node scripts/governance/sdd-distiller-freshness.test.mjs
import { measureDistillerFreshness } from './sdd-scorecard.mjs';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, basename } from 'node:path';

let fails = 0;
const ok = (cond, msg) => { if (cond) console.log(`  ✓ ${msg}`); else { console.error(`  ✗ ${msg}`); fails++; } };

/** Monta um memory/requisitos/ de teste. spec: { Mod: {distilledAt?, briefing?} } */
function makeReq(spec) {
  const dir = mkdtempSync(join(tmpdir(), 'distfresh-'));
  for (const [mod, cfg] of Object.entries(spec)) {
    const md = join(dir, mod);
    mkdirSync(md, { recursive: true });
    if (cfg.briefing !== false) {
      const fm = cfg.distilledAt ? `distilled_at: "${cfg.distilledAt}"\n` : '';
      writeFileSync(join(md, 'BRIEFING.md'), `---\nslug: ${mod}\n${fm}---\n# ${mod}\n`);
    }
  }
  return dir;
}
/** newestDocDate injetado a partir de um mapa por nome de módulo. */
const newestFrom = (map) => (modDir) => map[basename(modDir)] ?? null;

// ── A: zero carimbadas → notYet ─────────────────────────────────────────────
let dir = makeReq({ SemCarimbo: {}, Outra: {} });
try {
  const r = measureDistillerFreshness(dir, { newestDocDate: newestFrom({}) });
  ok(r.status === 'not_yet_measured', 'zero portas carimbadas → not_yet_measured');
  ok(r.value === null, 'sem carimbo → value null (não mente 0)');
} finally { rmSync(dir, { recursive: true, force: true }); }

// ── B/C/D: mistura fresca + atrasada + sem-carimbo + sem-porta ──────────────
dir = makeReq({
  Fresca: { distilledAt: '2026-06-15' },
  Atrasada: { distilledAt: '2026-05-01' },
  SemCarimbo: {},
  SemPorta: { briefing: false },
});
try {
  const r = measureDistillerFreshness(dir, {
    newestDocDate: newestFrom({ Fresca: '2026-06-16', Atrasada: '2026-06-18' }),
  });
  ok(r.status === 'measured', '≥1 carimbada → measured');
  ok(r.value === 1, 'value === 1 (só a Atrasada está >7d atrás do doc mais novo)');
  ok(r.direction === 'down' && r.target === 0, 'distiller_freshness DESCE pra 0');
  ok(r.detail.portas === 3, 'detail.portas = 3 (SemPorta sem BRIEFING é ignorada)');
  ok(r.detail.carimbadas === 2, 'detail.carimbadas = 2');
  ok(r.detail.sem_carimbo === 1, 'detail.sem_carimbo = 1 (cobertura pendente, não stale)');
  ok(r.detail.stale === 1, 'detail.stale = 1');
  ok(r.detail.oldest_distilled_at === '2026-05-01', 'detail.oldest_distilled_at = mais antigo');
} finally { rmSync(dir, { recursive: true, force: true }); }

// ── C isolado: fresca dentro de 7d não conta ────────────────────────────────
dir = makeReq({ Fresca: { distilledAt: '2026-06-15' } });
try {
  const r = measureDistillerFreshness(dir, { newestDocDate: newestFrom({ Fresca: '2026-06-20' }) });
  ok(r.status === 'measured' && r.value === 0, 'porta carimbada e fresca (5d) → value 0');
} finally { rmSync(dir, { recursive: true, force: true }); }

// ── E: reqDir ausente → notYet ──────────────────────────────────────────────
ok(
  measureDistillerFreshness(join(tmpdir(), `req-inexistente-${process.pid}`)).status === 'not_yet_measured',
  'reqDir ausente → not_yet_measured',
);

console.log(fails === 0 ? '\n  distiller_freshness read-side (ADR 0291 D-D): OK\n' : `\n  distiller_freshness: ${fails} FALHA(S)\n`);
process.exit(fails === 0 ? 0 : 1);
