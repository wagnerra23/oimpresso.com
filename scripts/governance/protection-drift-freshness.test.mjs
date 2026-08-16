#!/usr/bin/env node
// Meta-teste do read-side de frescor da órfã (V5 · avaliação adversarial 2026-07-12 risco nº1).
// Importa as puras de protection-drift.mjs (isMain guard garante que o import NÃO dispara
// fetchLive/rede) e prova o buraco fechado: o frescor vem do computed_at do CONTEÚDO, não do tip.
//   A) parseFloorTs aceita o formato write-side compacto 'YYYYMMDD-HHMMSS' (floor-compute.mjs)
//   B) parseFloorTs aceita ISO 8601; recusa (null) lixo/vazio/não-string
//   C) resolveOrfaFreshness lê content.computed_at e IGNORA outros campos (tip_committed_at)
//   D) ghContent decoda base64 → JSON (ghFn injetável, sem rede)
//   E) ghContent fail-closed: encoding 'none'/ausente/content vazio → LANÇA (caller reduz a stale)
// Uso: node scripts/governance/protection-drift-freshness.test.mjs
import { spawnSync } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { parseFloorTs, resolveOrfaFreshness, ghContent } from './protection-drift.mjs';

// Resolve o irmão pelo próprio arquivo (não por process.cwd()): o teste é chamado de
// vários cwd — raiz do repo no CI, pasta do script na mão.
const AQUI = dirname(fileURLToPath(import.meta.url));

let fails = 0;
const ok = (cond, msg) => { if (cond) console.log(`  ✓ ${msg}`); else { console.error(`  ✗ ${msg}`); fails++; } };
const b64 = (obj) => Buffer.from(JSON.stringify(obj), 'utf8').toString('base64');

// ── A: formato write-side compacto ──────────────────────────────────────────
ok(parseFloorTs('20260706-020001') === '2026-07-06T02:00:01.000Z', "parseFloorTs('YYYYMMDD-HHMMSS') → ISO UTC");
ok(parseFloorTs('20260711-120000') === '2026-07-11T12:00:00.000Z', 'parseFloorTs compacto zera segundos/minutos corretos');
ok(parseFloorTs('20261301-020001') === null, 'parseFloorTs recusa mês 13 (data impossível → null)');

// ── B: ISO 8601 + lixo ──────────────────────────────────────────────────────
ok(parseFloorTs('2026-07-06T02:00:01Z') === '2026-07-06T02:00:01.000Z', 'parseFloorTs passa ISO 8601');
ok(parseFloorTs('') === null && parseFloorTs('   ') === null, 'parseFloorTs recusa vazio/branco → null');
ok(parseFloorTs('não-é-data') === null, 'parseFloorTs recusa lixo → null');
ok(parseFloorTs(null) === null && parseFloorTs(undefined) === null && parseFloorTs(42) === null, 'parseFloorTs recusa não-string → null');

// ── C: resolveOrfaFreshness lê computed_at, ignora o tip ─────────────────────
ok(resolveOrfaFreshness({ computed_at: '20260706-020001', tip_committed_at: '20260712-060000' }) === '2026-07-06T02:00:01.000Z',
  'resolveOrfaFreshness usa computed_at e IGNORA tip_committed_at (o tip não conta)');
ok(resolveOrfaFreshness({ floor_count: 291 }) === null, 'resolveOrfaFreshness sem computed_at → null (fail-closed = stale)');
ok(resolveOrfaFreshness(null) === null, 'resolveOrfaFreshness(null) → null (não explode)');

// ── D: ghContent decoda base64 → JSON (ghFn injetável) ──────────────────────
const floor = { schema: 'nightly-floor/v1', floor_count: 291, computed_at: '20260706-020001' };
const okFn = () => ({ encoding: 'base64', content: b64(floor) });
ok(JSON.stringify(ghContent('r', 'br', 'p.json', okFn)) === JSON.stringify(floor), 'ghContent decoda base64 → JSON');
// content base64 com \n embutido (a API real quebra em linhas) — Buffer ignora whitespace.
const okFnNl = () => ({ encoding: 'base64', content: b64(floor).replace(/(.{40})/g, '$1\n') });
ok(ghContent('r', 'br', 'p.json', okFnNl).floor_count === 291, 'ghContent tolera \\n no base64 (formato da contents API)');

// ── E: fail-closed em encoding ruim/vazio ───────────────────────────────────
const throws = (fn) => { try { fn(); return false; } catch { return true; } };
ok(throws(() => ghContent('r', 'br', 'p.json', () => ({ encoding: 'none', content: 'x' }))), "ghContent LANÇA com encoding='none'");
ok(throws(() => ghContent('r', 'br', 'p.json', () => ({ encoding: 'base64', content: '' }))), 'ghContent LANÇA com content vazio');
ok(throws(() => ghContent('r', 'br', 'p.json', () => ({}))), 'ghContent LANÇA sem encoding (resposta inesperada)');

// ── F: UC-DOC-07 · dependência ausente é NÃO-MEDIÇÃO, não drift ─────────────
// Sem `gh` no PATH o execSync estourava e o Node cuspia o objeto de erro cru (stderr
// como array de bytes) — erro do CONSULTANTE lido como defeito do CONSULTADO
// (§5 2026-08-11). O contrato tem duas metades e as duas importam:
//   · exit 2 (≠ 1) — 1 significa "a proteção viva DIVERGIU do baseline", uma acusação
//     concreta; confundir os dois faria o watchdog reportar drift que ninguém causou.
//   · a mensagem nomeia o que faltou, em vez de despejar bytes.
// Roda o CLI de FORA, com PATH vazio: é o único jeito de exercitar o caminho real
// (o `gh()` não é exportado — assert em satélite não prova chokepoint, LC-15).
{
  const r = spawnSync(process.execPath, [join(AQUI, 'protection-drift.mjs')], {
    encoding: 'utf8', env: { ...process.env, PATH: '' },
  });
  const saida = (r.stdout || '') + (r.stderr || '');
  ok(r.status === 2, `UC-DOC-07: sem \`gh\` no PATH → exit 2 (não-medição), nunca 1 (drift real) — obtido ${r.status}`);
  ok(/NÃO CONSEGUI MEDIR/.test(saida), 'UC-DOC-07: a saída DECLARA a não-medição em vez de despejar o erro cru');
  ok(!/\[\s*\d+,\s*\d+,/.test(saida), 'UC-DOC-07: nenhum dump de array de bytes na saída (o sintoma do crash antigo)');
}

if (fails) { console.error(`\n  ✗ ${fails} caso(s) falharam — read-side de frescor da órfã NÃO garantido.\n`); process.exit(1); }
console.log('\n  ✓ read-side de frescor da órfã garantido (computed_at do conteúdo, não o tip).\n');
