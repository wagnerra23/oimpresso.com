#!/usr/bin/env node
// @ts-check
/**
 * shipped-log-generate.test.mjs — controle-negativo do gerador (sem rede).
 * Roda no governance-script-tests.yml. Fixtures-armadilha cobrem os gaps que
 * reprovaram a v1: título não-convencional, acento/alias de scope, revert,
 * borda BRT×UTC, e truncação (cross-check). Exit 1 em qualquer falha.
 */
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';
import {
  parseTitle, normScope, isDS, reconcileReverts, groupByArea,
  crossCheck, dayList, inBrtRange, markDeployed,
  evalShippedHealth, parseShippedMeta, pickLiveLogs, FRESH_DAYS, SHIPPED_DIR,
} from './shipped-log-generate.mjs';

const tests = [];
const t = (name, fn) => tests.push([name, fn]);

// ── markDeployed (G8 — merge ≠ deploy) ──
t('markDeployed: PR antes do deploy → no ar; depois → aguardando', () => {
  const prs = [
    { number: 1, mergedAt: '2026-06-20T10:00:00Z' },
    { number: 2, mergedAt: '2026-06-22T10:00:00Z' },
  ];
  const { onAir, waiting } = markDeployed(prs, '2026-06-21T00:00:00Z');
  assert.equal(prs[0]._deployed, true);
  assert.equal(prs[1]._deployed, false);
  assert.equal(onAir, 1); assert.equal(waiting, 1);
});
t('markDeployed: sem deploy_at → _deployed null, contagem null (degrada)', () => {
  const prs = [{ number: 1, mergedAt: '2026-06-20T10:00:00Z' }];
  const { onAir, waiting } = markDeployed(prs, null);
  assert.equal(prs[0]._deployed, null);
  assert.equal(onAir, null); assert.equal(waiting, null);
});
t('markDeployed: groupByArea propaga deployed pro row', () => {
  const prs = [{ number: 9, title: 'feat(x): y', mergedAt: '2026-06-20T10:00:00Z' }];
  markDeployed(prs, '2026-06-21T00:00:00Z');
  const { sorted } = groupByArea(prs);
  assert.equal(sorted.find(([a]) => a === 'x')[1].meaningful[0].deployed, true);
});

// ── parseTitle / normScope ──
t('parseTitle conventional simples', () => {
  assert.deepEqual(parseTitle('feat(financeiro): nova cobrança'), { type: 'feat', scope: 'financeiro', subject: 'nova cobrança' });
});
t('parseTitle título NÃO-convencional vira outros (não some)', () => {
  const r = parseTitle('PR Onda B: faxina geral');
  assert.equal(r.type, 'outros');
  assert.equal(r.scope, '');
});
t('normScope tira acento (governança → governance via alias)', () => {
  assert.equal(normScope('governança'), 'governance');
});
t('normScope alias caixa-unif → caixa-unificada', () => {
  assert.equal(normScope('caixa-unif'), 'caixa-unificada');
  assert.equal(normScope('caixa'), 'caixa-unificada');
});
t('parseTitle com bang (feat!: breaking)', () => {
  assert.equal(parseTitle('feat(api)!: quebra contrato').type, 'feat');
});

// ── isDS (inclui o falso-positivo conhecido G9) ──
t('isDS por scope', () => assert.equal(isDS('feat(ui): x', 'ui'), true));
t('isDS por título (redesign)', () => assert.equal(isDS('feat(x): redesign fiel ao protótipo', 'x'), true));
t('isDS falso-NEGATIVO esperado: financeiro puro não é DS', () => {
  assert.equal(isDS('feat(financeiro): baixa título', 'financeiro'), false);
});

// ── reconcileReverts ──
t('reconcileReverts casa par #revertido', () => {
  const m = reconcileReverts([
    { number: 2107, title: 'revert: PR2 endereço (#2104) — regressão' },
    { number: 2104, title: 'feat(cliente): endereço na venda' },
  ]);
  assert.equal(m.get(2104), 2107);
  assert.equal(m.size, 1);
});
t('reconcileReverts ignora revert sem #ref', () => {
  assert.equal(reconcileReverts([{ number: 9, title: 'revert: algo sem ref' }]).size, 0);
});

// ── groupByArea ──
t('groupByArea separa produto de ruído e marca DS', () => {
  const prs = [
    { number: 1, title: 'feat(ui): botão', mergedAt: '2026-06-01T10:00:00Z' },
    { number: 2, title: 'docs(x): readme', mergedAt: '2026-06-01T11:00:00Z' },
    { number: 3, title: 'feat(financeiro): baixa', mergedAt: '2026-06-01T12:00:00Z' },
  ];
  const { sorted, dsAll, totalMean, totalNoise } = groupByArea(prs);
  assert.equal(totalMean, 2);
  assert.equal(totalNoise, 1);
  assert.equal(dsAll.length, 1); // só o ui
  const ui = sorted.find(([a]) => a === 'ui');
  assert.equal(ui[1].meaningful.length, 1);
});
t('groupByArea anota revert na linha', () => {
  const { sorted } = groupByArea([{ number: 2104, title: 'feat(cliente): x', mergedAt: '2026-06-02T10:00:00Z' }], new Map([[2104, 2107]]));
  const row = sorted.find(([a]) => a === 'cliente')[1].meaningful[0];
  assert.equal(row.rev, 2107);
});

// ── crossCheck (anti-truncação) ──
t('crossCheck ok quando bate', () => assert.equal(crossCheck(50, 50, false).ok, true));
t('crossCheck FALHA quando diverge', () => assert.equal(crossCheck(49, 50, false).ok, false));
t('crossCheck FALHA quando sub-janela bateu no teto', () => assert.equal(crossCheck(1000, 1000, true).ok, false));
t('crossCheck ok (pulado) quando não há total independente', () => assert.equal(crossCheck(50, null, false).ok, true));

// ── borda BRT × UTC ──
t('inBrtRange inclui noite BRT do último dia (29/jun 01:00 UTC = 28/jun 22:00 BRT)', () => {
  assert.equal(inBrtRange('2026-06-29T01:00:00Z', '2026-05-31', '2026-06-28'), true);
});
t('inBrtRange exclui já-29/jun BRT (29/jun 05:00 UTC = 29/jun 02:00 BRT)', () => {
  assert.equal(inBrtRange('2026-06-29T05:00:00Z', '2026-05-31', '2026-06-28'), false);
});
t('dayList cobre margem ±1 dia', () => {
  const d = dayList('2026-06-01', '2026-06-02');
  assert.deepEqual(d, ['2026-05-31', '2026-06-01', '2026-06-02', '2026-06-03']);
});

// ── evalShippedHealth (freshness) — o gate que NUNCA teve como reprovar ──────────
// Contexto: até 2026-08-04 o --check pulava todo arquivo com `status: parcial`, e o
// gerador carimba `parcial` sempre que `until >= hoje` — que é o que o cron SEMPRE
// passa. Conjunto verificado vazio por construção. Estes casos são o controle-negativo
// que faltava (esta função tinha ZERO teste, por isso a isenção invertida sobreviveu).
const HOJE = Date.parse('2026-08-04T00:00:00Z');
const dia = (n) => new Date(HOJE - n * 86400000).toISOString().slice(0, 10);
const log = ({ status = 'parcial', gen, until = gen, cycle = 'CYCLE-08' }) =>
  `<!-- GERADO -->\n---\nstatus: ${status}\ncycle: ${cycle}\nwindow: "2026-05-31..${until}"\ngenerated: "${gen}"\n---\n`;

t('MORDE: log parcial gerado há 9d (cron morto) — o caso que passava verde', () => {
  const f = evalShippedHealth([{ name: 'CYCLE-08.md', text: log({ gen: dia(9) }) }], HOJE);
  assert.equal(f.length, 1);
  assert.match(f[0].issue, /STALE/);
});
t('MORDE: sem campo generated → não dá pra medir frescor', () => {
  const f = evalShippedHealth([{ name: 'CYCLE-08.md', text: '---\nstatus: parcial\n---\n' }], HOJE);
  assert.equal(f.length, 1);
  assert.equal(f[0].issue, 'sem campo generated');
});
t('MORDE: cron morto na transição de cycle (só o log velho existe, nada mais novo)', () => {
  const f = evalShippedHealth([{ name: 'CYCLE-08.md', text: log({ gen: dia(9), cycle: 'CYCLE-08' }) }], HOJE);
  assert.equal(f.length, 1); // não escapa por ser o único
});
t('MORDE: empate de janela → todos os vivos são cobrados', () => {
  const f = evalShippedHealth([
    { name: 'A.md', text: log({ gen: dia(9), until: dia(9), cycle: 'A' }) },
    { name: 'B.md', text: log({ gen: dia(9), until: dia(9), cycle: 'B' }) },
  ], HOJE);
  assert.equal(f.length, 2);
});
t('negativo: log parcial gerado hoje → verde (o estado normal do cron)', () => {
  assert.equal(evalShippedHealth([{ name: 'CYCLE-08.md', text: log({ gen: dia(0) }) }], HOJE).length, 0);
});
t(`negativo: exatamente FRESH_DAYS (${FRESH_DAYS}d) ainda é fresco — gap máx. do cron seg→qui`, () => {
  assert.equal(evalShippedHealth([{ name: 'CYCLE-08.md', text: log({ gen: dia(FRESH_DAYS) }) }], HOJE).length, 0);
  assert.equal(evalShippedHealth([{ name: 'CYCLE-08.md', text: log({ gen: dia(FRESH_DAYS + 1) }) }], HOJE).length, 1);
});
t('negativo: cycle FECHADO fica exempto (senão vermelho eterno) quando há vivo fresco', () => {
  // fixture usa `parcial` no histórico DE PROPÓSITO: é o que o registro real tem (13/13
  // medido). Com `ativo` aqui o caso passaria verde até num mutante que elegesse por
  // rótulo — não discriminaria a regressão que ele existe pra travar.
  const f = evalShippedHealth([
    { name: 'CYCLE-07.md', text: log({ status: 'parcial', gen: '2026-05-30', until: '2026-05-30', cycle: 'CYCLE-07' }) },
    { name: 'CYCLE-08.md', text: log({ gen: dia(1), cycle: 'CYCLE-08' }) },
  ], HOJE);
  assert.equal(f.length, 0);
});
t('MORDE (fail-closed documentado): janela forjada no futuro sequestra a eleição do vivo', () => {
  const f = evalShippedHealth([
    { name: 'BOGUS.md', text: log({ gen: '2026-06-01', until: '2027-01-01', cycle: 'BOGUS' }) },
    { name: 'CYCLE-08.md', text: log({ gen: dia(1), cycle: 'CYCLE-08' }) },
  ], HOJE);
  assert.equal(f.length, 1);
  assert.equal(f[0].cycle, 'BOGUS.md'); // reprova até regenerar/remover o forjado — nunca falha ABERTO
});
t('negativo: virada de cycle — log velho congela como `parcial` e AINDA assim é isentado', () => {
  // o caso que o conserto ingênuo (só apagar a isenção) quebraria: nada aqui é `ativo`.
  const f = evalShippedHealth([
    { name: 'CYCLE-08.md', text: log({ status: 'parcial', gen: dia(15), until: dia(15), cycle: 'CYCLE-08' }) },
    { name: 'CYCLE-09.md', text: log({ status: 'parcial', gen: dia(1), until: dia(1), cycle: 'CYCLE-09' }) },
  ], HOJE);
  assert.equal(f.length, 0);
});
t('pickLiveLogs escolhe por fim-de-janela, não por nome nem por status', () => {
  // os DOIS são `parcial` — o estado real do registro. Se este teste usasse `ativo` no
  // histórico, o nome dele mentiria: um mutante que elegesse por rótulo passaria.
  const metas = parseShippedMeta([
    { name: 'CYCLE-07.md', text: log({ status: 'parcial', gen: '2026-05-30', until: '2026-05-30' }) },
    { name: 'CYCLE-08.md', text: log({ status: 'parcial', gen: dia(1), until: dia(1) }) },
  ]);
  assert.deepEqual(pickLiveLogs(metas).map((m) => m.name), ['CYCLE-08.md']);
});

// ── bite-test E2E: prova que o COMANDO do gate (`--check`) morde, não só a função ──
// (correção-do-mecanismo ≠ invocação: o gate roda o CLI, então o CLI é que tem que reprovar)
function checkEmFixture(files) {
  const root = mkdtempSync(join(tmpdir(), 'shipped-check-'));
  try {
    const dir = join(root, SHIPPED_DIR);
    mkdirSync(dir, { recursive: true });
    for (const [name, text] of Object.entries(files)) writeFileSync(join(dir, name), text);
    const gen = fileURLToPath(new URL('./shipped-log-generate.mjs', import.meta.url));
    try {
      execFileSync(process.execPath, [gen, '--check'], { cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
      return 0;
    } catch (e) { return e.status ?? 1; }
  } finally { rmSync(root, { recursive: true, force: true }); }
}
const HOJE_REAL = (n) => new Date(Date.now() - n * 86400000).toISOString().slice(0, 10);
t('E2E --check: fixture RUIM (parcial velho) → exit ≠ 0', () => {
  assert.notEqual(checkEmFixture({ 'CYCLE-08.md': log({ gen: HOJE_REAL(9), until: HOJE_REAL(9) }) }), 0);
});
t('E2E --check: fixture BOA (parcial de hoje) → exit 0', () => {
  assert.equal(checkEmFixture({ 'CYCLE-08.md': log({ gen: HOJE_REAL(0), until: HOJE_REAL(0) }) }), 0);
});
t('E2E --check: registro vazio (dir existe, 0 log) → exit ≠ 0, não fica mudo', () => {
  assert.notEqual(checkEmFixture({}), 0);
});

// ── runner ──
let pass = 0, fail = 0;
for (const [name, fn] of tests) {
  try { fn(); pass++; } catch (e) { fail++; console.error(`✗ ${name}\n  ${e.message}`); }
}
console.log(`${fail ? '✗' : '✓'} shipped-log-generate.test.mjs — ${pass}/${tests.length} passaram${fail ? `, ${fail} FALHARAM` : ''}`);
process.exit(fail ? 1 : 0);
