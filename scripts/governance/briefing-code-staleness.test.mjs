#!/usr/bin/env node
// @ts-check
/**
 * briefing-code-staleness.test.mjs — self-test adversarial do núcleo puro.
 *
 * Prova, sem git nem FS (datas injetadas), que o sentinela:
 *   · MORDE  — porta atrás do código além do limiar → stale (com o caso REAL do
 *              incidente #3714: Compras porta 05-21 vs código 07-01 = 41d).
 *   · LIBERA — porta em dia / porta refrescada depois do código → NÃO stale
 *              (com o estado REAL pós-#3714: porta 07-03 vs código 07-01).
 *   · não gera FALSO-POSITIVO — sem porta, sem código, ou datas ausentes → não avaliado.
 *   · respeita o LIMIAR exato na fronteira (30d não morde; 31d morde).
 *
 * Determinístico (Date.parse UTC) → mesma resposta em qualquer máquina/CI.
 * Uso: node scripts/governance/briefing-code-staleness.test.mjs
 */
import { classifyCodeStaleness, declaredDoorDate } from './briefing-code-staleness.mjs';

let fails = 0;
const ok = (cond, msg) => { if (cond) console.log(`  ✓ ${msg}`); else { console.error(`  ✗ ${msg}`); fails++; } };

const D = (over = {}) => ({ hasDoor: true, moduleCodeExists: true, doorDate: null, codeDate: null, staleDays: 30, ...over });

console.log('\n  briefing-code-staleness — self-test do núcleo puro\n');

// ── A) MORDE: caso REAL do incidente (#3714) — Compras pré-refresh ──────────
{
  const r = classifyCodeStaleness(D({ doorDate: '2026-05-21', codeDate: '2026-07-01' }));
  ok(r.evaluated && r.stale && r.gapDays === 41,
    `MORDE: Compras porta 05-21 vs código 07-01 → stale, gap=41d (obtido: stale=${r.stale}, gap=${r.gapDays})`);
}

// ── B) LIBERA: porta fresca (poucos dias à frente) ─────────────────────────
{
  const r = classifyCodeStaleness(D({ doorDate: '2026-07-01', codeDate: '2026-07-03' }));
  ok(r.evaluated && !r.stale && r.gapDays === 2,
    `LIBERA: porta 07-01 vs código 07-03 → não stale, gap=2d (obtido: stale=${r.stale}, gap=${r.gapDays})`);
}

// ── C) LIBERA: estado REAL pós-#3714 — porta refrescada DEPOIS do código ────
// Prova que o próprio fix do incidente zera o alerta (gap negativo → fresco).
{
  const r = classifyCodeStaleness(D({ doorDate: '2026-07-03', codeDate: '2026-07-01' }));
  ok(r.evaluated && !r.stale && r.gapDays === -2,
    `LIBERA: pós-refresh porta 07-03 vs código 07-01 → não stale, gap=-2d (obtido: stale=${r.stale}, gap=${r.gapDays})`);
}

// ── D) NÃO AVALIA: módulo com código mas SEM porta (BRIEFING ausente) ───────
// Cobertura ausente é outro sinal (knowledge-drift `door: NÃO`); aqui não é stale.
{
  const r = classifyCodeStaleness(D({ hasDoor: false, doorDate: null, codeDate: '2026-07-01' }));
  ok(!r.evaluated && !r.stale && r.gapDays === null,
    `NÃO AVALIA: sem porta → evaluated=false, não stale (obtido: evaluated=${r.evaluated}, stale=${r.stale})`);
}

// ── E) NÃO AVALIA: requisitos dir sem módulo no disco (_DesignSystem, Infra…) ─
{
  const r = classifyCodeStaleness(D({ moduleCodeExists: false, doorDate: '2026-01-01', codeDate: '2026-07-01' }));
  ok(!r.evaluated && !r.stale,
    `NÃO AVALIA: sem código no disco → evaluated=false, não stale (obtido: evaluated=${r.evaluated})`);
}

// ── F) FRONTEIRA do limiar: 30d não morde; 31d morde ───────────────────────
{
  const at = classifyCodeStaleness(D({ doorDate: '2026-06-01', codeDate: '2026-07-01' })); // exatamente 30d
  ok(at.evaluated && !at.stale && at.gapDays === 30,
    `FRONTEIRA: gap=30d exatamente → NÃO morde (>30 é estrito) (obtido: stale=${at.stale}, gap=${at.gapDays})`);
  const over = classifyCodeStaleness(D({ doorDate: '2026-06-01', codeDate: '2026-07-02' })); // 31d
  ok(over.evaluated && over.stale && over.gapDays === 31,
    `FRONTEIRA: gap=31d → MORDE (obtido: stale=${over.stale}, gap=${over.gapDays})`);
}

// ── G) LIMIAR TUNÁVEL: staleDays=21 morde o que 30 libera ──────────────────
{
  const loose = classifyCodeStaleness(D({ doorDate: '2026-06-05', codeDate: '2026-06-30', staleDays: 30 })); // 25d
  const tight = classifyCodeStaleness(D({ doorDate: '2026-06-05', codeDate: '2026-06-30', staleDays: 21 })); // 25d
  ok(!loose.stale && tight.stale,
    `TUNÁVEL: gap=25d → limiar 30 libera, limiar 21 morde (obtido: 30=${loose.stale}, 21=${tight.stale})`);
}

// ── H) declaredDoorDate: caso REAL do incidente — frontmatter updated_at ────
// Ground-truth do BRIEFING do Compras pré-#3714 (data-git mentia 06-08; conteúdo 05-21).
{
  const compras = '---\nslug: compras-briefing\nstatus: scaffold\nupdated_at: 2026-05-21\nversion: 0.1\n---\n# BRIEFING\n- Estado: Wave 1 scaffold (2026-05-21)\n';
  ok(declaredDoorDate(compras) === '2026-05-21',
    `declaredDoorDate: frontmatter updated_at não-quoted → 2026-05-21 (obtido: ${declaredDoorDate(compras)})`);
}

// ── I) declaredDoorDate: pega o MAIS RECENTE entre carimbos (updated_at vs distilled_at) ─
{
  const both = '---\nupdated_at: 2026-05-21\ndistilled_at: "2026-07-02"\n---\n';
  ok(declaredDoorDate(both) === '2026-07-02',
    `declaredDoorDate: max(updated_at 05-21, distilled_at 07-02) → 2026-07-02 (obtido: ${declaredDoorDate(both)})`);
}

// ── J) declaredDoorDate: rodapé legado **Atualizado:** (sem frontmatter date) ─
{
  const legacy = '> **Estado:** legado | **Atualizado:** 2026-05-16 | **Owner:** [W]\n';
  ok(declaredDoorDate(legacy) === '2026-05-16',
    `declaredDoorDate: rodapé **Atualizado:** → 2026-05-16 (obtido: ${declaredDoorDate(legacy)})`);
}

// ── K) declaredDoorDate: sem carimbo → null (aciona fallback git no scan real) ─
{
  ok(declaredDoorDate('# BRIEFING\nsem nenhuma data aqui\n') === null,
    `declaredDoorDate: sem carimbo → null (obtido: ${declaredDoorDate('# BRIEFING\nsem data\n')})`);
}

// ── L) INTEGRAÇÃO do fix: data DECLARADA morde onde a data-git passaria batido ─
// O núcleo do aprendizado: git=06-08 (mecânico) daria 23d<30 (passa); declarada=05-21 dá 41d (morde).
{
  const viaGit = classifyCodeStaleness(D({ doorDate: '2026-06-08', codeDate: '2026-07-01' })); // data-git mecânica
  const viaDeclarado = classifyCodeStaleness(D({ doorDate: '2026-05-21', codeDate: '2026-07-01' })); // data declarada
  ok(!viaGit.stale && viaDeclarado.stale,
    `FIX: data-git 06-08 (23d) passaria batido; data declarada 05-21 (41d) MORDE (obtido: git=${viaGit.stale}, declarado=${viaDeclarado.stale})`);
}

console.log(`\n  ${fails === 0 ? '✅ TODOS os casos passaram' : `❌ ${fails} caso(s) falharam`}\n`);
process.exit(fails === 0 ? 0 : 1);
