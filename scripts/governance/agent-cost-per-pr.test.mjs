#!/usr/bin/env node
// @ts-check
// SELF-TEST de agent-cost-per-pr.mjs — prova que o join de 2 sinais MORDE (branch
// vira custo; sessão que cita /pull/N vira custo diluído) e LIBERA (PR sem sinal =
// SEM MATCH publicado; branch alheia não vaza; sessão casada por branch NÃO
// re-atribui por citação; modelo sem preço não inventa USD), e que a matemática de
// cache confere (read 0.1× · write 5m 1.25× · write 1h 2×).
// Hermético: fixtures em memória — zero gh/rede (fs só em tmpdir, pro round-trip de
// encoding UTF-8 do snapshot: título PT-BR não-ASCII tem que sobreviver sem mojibake/BOM).
// Exit 0 = passa.

import {
  buildReport, parseUsageLine, custoUSD, resolvePreco, aggregatePorBranch,
  aggregatePorModelo, extractPrMentions, PRECOS_USD_MTOK, CACHE_MULT,
} from './agent-cost-per-pr.mjs';
import { mkdtempSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

let fails = 0;
const check = (name, cond) => { console.log(`${cond ? '[OK]  ' : '[FAIL]'} ${name}`); if (!cond) fails++; };

// ── resolvePreco: prefixo cobre id com sufixo de data; desconhecido → null ──────
check('resolvePreco id exato', resolvePreco('claude-opus-4-8') === PRECOS_USD_MTOK['claude-opus-4-8']);
check('resolvePreco sufixo de data (haiku-4-5-20251001)', resolvePreco('claude-haiku-4-5-20251001') === PRECOS_USD_MTOK['claude-haiku-4-5']);
check('resolvePreco desconhecido → null', resolvePreco('claude-foo-9') === null);

// ── custoUSD: matemática de cache confere com números redondos ──────────────────
// opus 4.8 ($5 in / $25 out): 1M in=5 + 100k out=2.5 + 2M read×0.1=1.0 + 400k w5m×1.25=2.5 + 100k w1h×2=1.0 → $12
const T = { input: 1_000_000, output: 100_000, cache_read: 2_000_000, cache_5m: 400_000, cache_1h: 100_000 };
check('custoUSD opus 4.8 = $12.00 (in+out+read0.1+w5m1.25+w1h2)', custoUSD(T, 'claude-opus-4-8') === 12);
check('custoUSD modelo desconhecido → null (não inventa)', custoUSD(T, 'claude-foo-9') === null);
check('CACHE_MULT canon', CACHE_MULT.read === 0.1 && CACHE_MULT.write_5m === 1.25 && CACHE_MULT.write_1h === 2.0);

// ── parseUsageLine: formato real do JSONL do Claude Code ────────────────────────
const linha = JSON.stringify({
  type: 'assistant', gitBranch: 'claude/a', message: {
    model: 'claude-opus-4-8',
    usage: { input_tokens: 10, output_tokens: 20, cache_read_input_tokens: 30, cache_creation_input_tokens: 40, cache_creation: { ephemeral_5m_input_tokens: 15, ephemeral_1h_input_tokens: 25 } },
  },
});
const e1 = parseUsageLine(linha);
check('parseUsageLine extrai branch/model/tokens', e1 && e1.branch === 'claude/a' && e1.model === 'claude-opus-4-8' && e1.input === 10 && e1.output === 20 && e1.cache_read === 30);
check('parseUsageLine usa breakdown 5m/1h quando existe', e1 && e1.cache_5m === 15 && e1.cache_1h === 25);
const semBreakdown = JSON.stringify({ type: 'assistant', gitBranch: 'x', message: { model: 'm', usage: { input_tokens: 1, output_tokens: 1, cache_creation_input_tokens: 99 } } });
const e2 = parseUsageLine(semBreakdown);
check('parseUsageLine sem breakdown → tudo vira write 5m (G4)', e2 && e2.cache_5m === 99 && e2.cache_1h === 0);
check('parseUsageLine ignora user/tool/ruído', parseUsageLine('{"type":"user","message":{"usage":{}}}') === null && parseUsageLine('lixo não-json "usage"') === null && parseUsageLine('{"type":"assistant","message":{}}') === null);

// ── extractPrMentions ────────────────────────────────────────────────────────────
check('extractPrMentions acha /pull/N e deduplica', JSON.stringify(extractPrMentions('https://github.com/o/r/pull/10 e de novo /pull/10 e /pull/11')) === '[10,11]');
check('extractPrMentions fronteira: /pull/100 não casa 10', !extractPrMentions('/pull/100').includes(10));

// ── fixture de PRs + sessões (join de 2 sinais morde/libera) ────────────────────
// título do #10 é não-ASCII de PROPÓSITO (resíduo (d) adversário 2026-07-13: fixtures
// ASCII-only deixariam mojibake de encoding Windows — proibições: Set-Content BOM/cp1252 —
// atravessar o pipeline report→snapshot sem nenhum teste gritar).
const TITULO_PT = 'feat: memória canônica — adversário à prova [CC]';
const PRS = [
  { number: 10, title: TITULO_PT, author: { login: 'x' }, headRefName: 'claude/a', createdAt: '2026-07-01T00:00:00Z', mergedAt: '2026-07-02T00:00:00Z' },
  { number: 11, title: 'feat: b [CC]', author: { login: 'x' }, headRefName: 'claude/b', createdAt: '2026-07-03T00:00:00Z', mergedAt: '2026-07-04T00:00:00Z' },
  { number: 12, title: 'feat: c [CC]', author: { login: 'x' }, headRefName: 'claude/c', createdAt: '2026-07-05T00:00:00Z', mergedAt: '2026-07-06T00:00:00Z' },
  { number: 15, title: 'feat: d [CC]', author: { login: 'x' }, headRefName: 'claude/d', createdAt: '2026-07-05T12:00:00Z', mergedAt: '2026-07-06T12:00:00Z' },
  // humano — fora da janela do agente mesmo com branch coberta por usage
  { number: 13, title: 'feat: humano', author: { login: 'wagnerra23' }, headRefName: 'claude/a', createdAt: '2026-07-05T00:00:00Z', mergedAt: '2026-07-07T00:00:00Z' },
  // agente ABERTO (sem mergedAt) — não entra
  { number: 14, title: 'wip [CC]', author: { login: 'x' }, headRefName: 'claude/e', createdAt: '2026-07-08T00:00:00Z', mergedAt: null },
];
const ent = (branch, model, input, output = 0) => ({ branch, model, input, output, cache_read: 0, cache_5m: 0, cache_1h: 0 });
const SESSIONS = [
  // Sinal 1: 2 sessões na branch do #10 (agregam) — opus $12 + haiku (1M in + 200k out) $2 → $14
  { id: 's1', entries: [{ branch: 'claude/a', model: 'claude-opus-4-8', ...T }], pr_mentions: [] },
  // s2 casa por branch E cita #12 — a citação NÃO deve re-atribuir (anti dupla contagem)
  { id: 's2', entries: [ent('claude/a', 'claude-haiku-4-5', 1_000_000, 200_000)], pr_mentions: [12] },
  // Sinal 2: sessão de worktree (branch alheia) que CITA #12 e #15 → custo diluído /2
  // haiku 4M in = $4 → $2 pra cada
  { id: 's3', entries: [ent('claude/worktree-xyz', 'claude-haiku-4-5', 4_000_000)], pr_mentions: [12, 15, 999] },
  // modelo sem preço citando #15 → tokens contam, USD marcado incompleto
  { id: 's4', entries: [ent('claude/worktree-abc', 'claude-foo-9', 1_000_000)], pr_mentions: [15] },
  // nem branch nem citação → fora da janela (haiku 1M in = $1)
  { id: 's5', entries: [ent('claude/unrelated', 'claude-haiku-4-5', 1_000_000)], pr_mentions: [999] },
  // sem branch → contado em msgs_sem_branch, não atribuído
  { id: 's6', entries: [ent(null, 'claude-opus-4-8', 1, 1)], pr_mentions: [] },
];

const r = buildReport({ prs: PRS, usage: undefined, sessions: SESSIONS, generated: '2026-07-12' });

check('janela = 4 PRs do agente (humano #13 e aberto #14 excluídos)', r.janela.prs === 4 && !r.por_pr.some((p) => p.pr === 13 || p.pr === 14));
const pr10 = r.por_pr.find((p) => p.pr === 10);
check('MORDE branch: #10 agrega 2 sessões = $14.00', pr10 && pr10.matched && pr10.usd === 14 && pr10.sinais.includes('branch'));
const pr12 = r.por_pr.find((p) => p.pr === 12);
check('MORDE citação: #12 = $2.00 (diluído /2, SEM a citação da s2 casada por branch)', pr12 && pr12.matched && pr12.usd === 2 && pr12.sinais.join('') === 'citacao');
const pr15 = r.por_pr.find((p) => p.pr === 15);
check('MORDE citação: #15 = $2.00 + parcial do modelo sem preço', pr15 && pr15.usd === 2 && pr15.usd_incompleto === true);
const pr11 = r.por_pr.find((p) => p.pr === 11);
check('DECLARA: #11 sem sinal → sem match (não inventa custo)', pr11 && pr11.matched === false && pr11.usd === null);
check('sem_match_pct = 25% publicado', r.join.sem_match === 1 && r.join.sem_match_pct === 25);
check('join separa sinais: 1 por branch, 2 por citação', r.join.matched_por_branch === 1 && r.join.matched_por_citacao === 2);
check('modelo desconhecido listado', r.join.modelos_desconhecidos.includes('claude-foo-9'));
check('LIBERA: sessão sem sinal vira fora da janela ($1.00)', r.custo.usd_fora_da_janela === 1);
check('msgs sem branch contadas, não atribuídas', r.join.msgs_sem_branch === 1);
check('total matched = 14+2+2 = $18.00', r.custo.total_usd_matched === 18);

// ── encoding PT-BR sobrevive report → snapshot em disco (mesma escrita do --snapshot) ──
const MOJIBAKE = /Ã[£©¡§µ­ª‚ƒ†]|â€|Ãƒ|�/; // Ã£/Ã©/â€œ/â€”/U+FFFD etc
check('título PT-BR intacto no por_pr (sem mojibake)', pr10 && pr10.title === TITULO_PT);
check('título PT-BR intacto no top_prs', r.custo.top_prs.some((t) => t.pr === 10 && t.title === TITULO_PT));
const snapDir = mkdtempSync(join(tmpdir(), 'costsnap-'));
try {
  const snapFile = join(snapDir, 'snap.json');
  writeFileSync(snapFile, JSON.stringify(r, null, 2) + '\n'); // idêntico ao caminho --snapshot
  const buf = readFileSync(snapFile);
  check('snapshot em disco sem BOM UTF-8', !(buf[0] === 0xEF && buf[1] === 0xBB && buf[2] === 0xBF));
  const texto = buf.toString('utf8');
  check('snapshot em disco sem padrões de mojibake', !MOJIBAKE.test(texto));
  const volta = JSON.parse(texto);
  check('round-trip disco preserva título PT-BR byte a byte', volta.por_pr.find((p) => p.pr === 10)?.title === TITULO_PT);
} finally { rmSync(snapDir, { recursive: true, force: true }); }

// ── LIBERA: sem sessões → tudo sem match (100%), nada inventado ──────────────────
const r2 = buildReport({ prs: PRS, sessions: [], generated: '2026-07-12' });
check('sem sessões: 100% sem match, total $0', r2.join.sem_match_pct === 100 && r2.custo.total_usd_matched === 0);

// ── janela corta em N (mais recente primeiro) ───────────────────────────────────
const r3 = buildReport({ prs: PRS, sessions: SESSIONS, prWindow: 1, generated: '2026-07-12' });
check('prWindow=1 pega só o merge mais recente (#15)', r3.janela.prs === 1 && r3.por_pr[0].pr === 15);

// ── agregadores diretos (redundância de defesa) ─────────────────────────────────
const agg = aggregatePorBranch([ent('b1', 'm', 1, 2), ent('b1', 'm', 10)]);
check('aggregatePorBranch soma por (branch, modelo)', agg.porBranch.get('b1').get('m').input === 11);
check('aggregatePorModelo ignora branch', aggregatePorModelo([ent('b1', 'm', 1), ent('b2', 'm', 2)]).get('m').input === 3);

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — join 2-sinais morde (branch com $ certo de cache; citação diluída) e libera (sem match declarado, sem dupla contagem, modelo sem preço não inventa USD).');
process.exit(fails ? 1 : 0);
