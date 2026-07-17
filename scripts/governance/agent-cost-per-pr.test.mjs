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
  PR_FETCH_LIMIT, DEFAULT_DAYS, renderHuman, renderBriefMd, renderPrBlockMd,
  linhaIdade, avisoSnapshot, IDADE_SUSPEITA_DIAS,
  derivaLimiarIdade, TOLERANCIA_STALENESS,
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

check('janela = 4 PRs do agente (humano #13 e aberto #14 excluídos)', r.janela.prs_no_universo === 4 && !r.por_pr.some((p) => p.pr === 13 || p.pr === 14));
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
check('LIBERA: sessão sem sinal vira resíduo ($1.00)', r.residuo.usd === 1);
check('msgs sem branch contadas, não atribuídas', r.join.msgs_sem_branch === 1);
check('total atribuído = 14+2+2 = $18.00', r.custo.total_usd_atribuido === 18);

// ── CONTRATO: a SESSÃO é a unidade de custo, não a branch-por-mensagem ──────────
// Regressão medida em 2026-07-17 (live): o padrão real é gastar na branch da WORKTREE e
// só criar a branch de tópico no fim — o branch do PR marcava apenas a CAUDA. O desenho
// antigo atribuía só a cauda ao PR e DESCARTAVA o corpo em `usd_fora_da_janela`, fazendo
// PRs de sessão inteira aparecerem com $4,22 ao lado de $444 casados por citação.
// Fixture: $10 no corpo (worktree) + $1 na cauda (branch do #11). Antes: #11 = $1.
const SESSAO_CAUDA = [{
  id: 'cauda',
  entries: [
    ent('claude/worktree-onde-o-trabalho-aconteceu', 'claude-haiku-4-5', 10_000_000), // corpo $10
    ent('claude/b', 'claude-haiku-4-5', 1_000_000),                                    // cauda $1 (#11)
  ],
  pr_mentions: [],
}];
const rc = buildReport({ prs: PRS, sessions: SESSAO_CAUDA, generated: '2026-07-12' });
const pr11c = rc.por_pr.find((p) => p.pr === 11);
check('SESSÃO é a unidade: #11 leva a sessão INTEIRA ($11), não só a cauda ($1)', pr11c && pr11c.usd === 11);
check('corpo da sessão NÃO vira resíduo quando ela produziu PR', rc.residuo.usd === 0);
check('cobertura de alocação = 100% (todo o dinheiro tem dono)', rc.custo.cobertura_alocacao_pct === 100);

// ── CONTRATO: cobertura é do lado do DINHEIRO, não do lado do PR ────────────────
// O `sem_match_pct` antigo dizia 0% (todo PR recebia custo) enquanto ~88% do dinheiro
// não tinha dono. Fixture: sessão de $9 sem PR + sessão de $1 no #11 → 4 PRs, 0% "sem
// match" seria mentira; a verdade é 10% de cobertura.
const SESSAO_RESIDUO = [
  { id: 'paga', entries: [ent('claude/b', 'claude-haiku-4-5', 1_000_000)], pr_mentions: [] },      // $1 → #11
  { id: 'orfa', entries: [ent('claude/explorei-e-nao-virou-pr', 'claude-haiku-4-5', 9_000_000)], pr_mentions: [] }, // $9
];
const rr = buildReport({ prs: PRS, sessions: SESSAO_RESIDUO, generated: '2026-07-12' });
check('cobertura do DINHEIRO = 10% ($1 de $10) — não confunde com cobertura do PR', rr.custo.cobertura_alocacao_pct === 10 && rr.custo.total_usd_escaneado === 10);
check('resíduo DECOMPOSTO por categoria (não só declarado)', rr.residuo.usd === 9 && rr.residuo.por_categoria['sessão sem PR na janela (exploração/análise/sub-agente/PR aberto)'] === 9);

// ── CONTRATO: janela COERENTE — PR fora da janela de tempo não entra no universo ──
const rj = buildReport({ prs: PRS, sessions: SESSIONS, days: 3, generated: '2026-07-12' });
check('janela 3d: PRs mergeados antes de 2026-07-09 saem do universo', rj.janela.prs_no_universo === 0 && rj.janela.desde === '2026-07-09');
check('resíduo main/HEAD é categoria própria', buildReport({
  prs: PRS, generated: '2026-07-12',
  sessions: [{ id: 'm', entries: [ent('main', 'claude-haiku-4-5', 1_000_000)], pr_mentions: [] }],
}).residuo.por_categoria['main/HEAD (trabalho fora de branch de PR)'] === 1);

// ── CONTRATO: cap do fetch é DECLARADO, nunca silencioso ────────────────────────
// Medido 2026-07-17: com ~30 PRs/dia, o `limit:200` alcançava só ~4 dias — com janela de
// 14d o universo vinha 4× menor (104 vs 435 PRs [CC]) e todo PR ausente virava resíduo
// FALSO. O cap continua existindo; o relatório é que não pode calar sobre ele.
check('PR_FETCH_LIMIT cobre a janela default (>=30 PRs/dia × DEFAULT_DAYS)', PR_FETCH_LIMIT >= 30 * DEFAULT_DAYS);
check('fonte_truncada AUSENTE quando o fetch não bateu no cap', buildReport({ prs: PRS, sessions: [], generated: '2026-07-12' }).janela.fonte_truncada === undefined);
check('fonte_truncada DECLARADA no json quando o fetch bateu no cap',
  buildReport({ prs: PRS, sessions: [], generated: '2026-07-12', fonteTruncada: true }).janela.fonte_truncada === true);
check('fonte truncada GRITA no texto humano (não some no rodapé)',
  renderHuman(buildReport({ prs: PRS, sessions: [], generated: '2026-07-12', fonteTruncada: true })).includes('FONTE TRUNCADA'));
check('fonte truncada GRITA no brief markdown',
  renderBriefMd(buildReport({ prs: PRS, sessions: [], generated: '2026-07-12', fonteTruncada: true })).includes('FONTE TRUNCADA'));

// ── CONTRATO: bloco `--pr N` leva o número pro PR — relato, nunca gate ──────────
const blocoPago = renderPrBlockMd(r, r.por_pr.find((p) => p.pr === 10));
check('--pr: bloco traz o custo REAL do PR (#10 = $14.00)', blocoPago.includes('$14.00') && blocoPago.includes('advisory'));
check('--pr: bloco publica cobertura de alocação junto (contexto do número)', blocoPago.includes('cobertura de alocação'));
check('--pr: PR sem sessão casada DECLARA "não medido" (não inventa $0)', renderPrBlockMd(r, r.por_pr.find((p) => p.pr === 11)).includes('não medido'));
check('--pr: bloco é RELATO — não fala em bloquear/gate/falhar', !/\b(bloqueia merge|reprova|falha o PR)\b/i.test(blocoPago) && blocoPago.includes('não bloqueia'));
check('--pr: bloco NÃO traz valores R$ (Tier 0 — só USD)', !/R\$/.test(blocoPago));

// ── CONTRATO: nenhum número sai sem a IDADE colada nele ─────────────────────────
// Incidente de origem (2026-07-17): a grade citou "96,8% órfão" de um snapshot de 4 dias
// como fato vivo — e o diagnóstico estava invertido (matched_por_branch 0 vs 12 ao vivo).
// O campo `generated` JÁ existia e não impediu: data exige subtração contra hoje. Idade
// não exige nada. O limiar antigo (14d) não disparou porque 4 < 14 — por isso o alerta é
// calibrado pela meia-vida MEDIDA (4d), não por um número arbitrário.
check('idade 0 → diz "AO VIVO" (não cala: dizer que é vivo é informação)', linhaIdade(0).includes('AO VIVO'));
check('idade 4d (o caso REAL do incidente) → GRITA "não é medição viva"', /MEDIDO HÁ 4d/.test(linhaIdade(4)) && linhaIdade(4).includes('NÃO cite'));
check('limiar default ainda é 3d (14d × 20%), mas agora DERIVADO', IDADE_SUSPEITA_DIAS === 3 && 4 > IDADE_SUSPEITA_DIAS);
check('idade 1d ainda declara que é retrato (não finge vivo)', linhaIdade(1).includes('retrato'));
check('idade AUSENTE não passa em silêncio (declara desconhecida)', linhaIdade(undefined).includes('DESCONHECIDA'));

// ── CONTRATO: a calibração é DERIVADA da janela, não cravada (evolução 2026-07-17) ──
// O "3 dias" era magic number que quebrava silencioso se --days mudasse — a MESMA doença
// de snapshot velho que o script mede, uma camada acima. Agora o dia-limite = tolerância
// (política estável) × janela (dado), e AUTO-ESCALA.
check('derivaLimiarIdade(14) = 3 (janela default)', derivaLimiarIdade(14) === 3);
check('AUTO-ESCALA: janela 30d → 6d · 90d → 18d (agregado longo tolera mais idade)', derivaLimiarIdade(30) === 6 && derivaLimiarIdade(90) === 18);
check('piso em 1d (janela minúscula não zera o limiar)', derivaLimiarIdade(3) === 1 && derivaLimiarIdade(1) === 1);
check('tolerância é a política estável (0.2 = 20% de rotação)', TOLERANCIA_STALENESS === 0.2);
// o limiar FLUI pro texto: mesma idade, veredito diferente conforme a janela
check('limiar flui: idade 5d é RETRATO numa janela de 30d (limite 6) …', linhaIdade(5, derivaLimiarIdade(30)).includes('retrato') && !linhaIdade(5, derivaLimiarIdade(30)).includes('VELHO'));
check('… mas é VELHO numa janela de 14d (limite 3) — mesma idade, veredito derivado', linhaIdade(5, derivaLimiarIdade(14)).includes('VELHO'));

// ── CONTRATO: velocidade DERIVADA do dado + calibração à vista no relatório ──────
// Fixture: 3 PRs [CC] numa janela de 6d → 0.5 PR/dia. O número sai do dado em mãos,
// não de "~30/dia" cravado.
const PRS_VEL = [
  { number: 21, title: 'a [CC]', author: { login: 'x' }, headRefName: 'c/a', createdAt: '2026-07-10T00:00:00Z', mergedAt: '2026-07-11T00:00:00Z' },
  { number: 22, title: 'b [CC]', author: { login: 'x' }, headRefName: 'c/b', createdAt: '2026-07-11T00:00:00Z', mergedAt: '2026-07-12T00:00:00Z' },
  { number: 23, title: 'c [CC]', author: { login: 'x' }, headRefName: 'c/c', createdAt: '2026-07-12T00:00:00Z', mergedAt: '2026-07-13T00:00:00Z' },
];
const rv = buildReport({ prs: PRS_VEL, sessions: [], days: 6, generated: '2026-07-14' });
check('velocidade DERIVADA do dado: 3 PRs / 6d = 0.5/dia', rv.calibracao.velocidade_prs_dia === 0.5);
check('calibração publica limiar derivado + tolerância + data dos preços', rv.calibracao.limiar_idade_dias === derivaLimiarIdade(6) && rv.calibracao.tolerancia_staleness === 0.2 && rv.calibracao.precos_atualizados_em === '2026-07-12');
check('calibração aparece no texto humano (velocidade + rotação à vista)', renderHuman(rv, 0).includes('PRs/dia') && renderHuman(rv, 0).includes('rotação'));
check('preços datados no brief (idade dos preços também visível, não só a da medição)', renderBriefMd(r, 0).includes('preços de 2026-07-12'));
check('brief de 4d carrega o alerta ACIMA da tabela (não em rodapé)', (() => {
  const md = renderBriefMd(r, 4);
  return md.indexOf('MEDIDO HÁ 4d') < md.indexOf('| Métrica |');
})());
check('texto humano de 4d carrega o alerta ACIMA dos números', (() => {
  const t = renderHuman(r, 4);
  return t.indexOf('MEDIDO HÁ 4d') < t.indexOf('COBERTURA DE ALOCAÇÃO');
})());
check('bloco do PR também declara a idade', renderPrBlockMd(r, r.por_pr.find((p) => p.pr === 10), 4).includes('MEDIDO HÁ 4d'));

// snapshot se AUTO-DENUNCIA: a grade leu o JSON, não o markdown — a defesa mora no arquivo
check('aviso do snapshot é a PRIMEIRA chave do JSON (o leitor não passa reto)',
  Object.keys({ _LEIA_PRIMEIRO: avisoSnapshot('2026-07-13'), ...r })[0] === '_LEIA_PRIMEIRO');
check('aviso do snapshot carrega a data + o mando de rodar ao vivo',
  avisoSnapshot('2026-07-13').includes('2026-07-13') && avisoSnapshot('2026-07-13').includes('--json') && avisoSnapshot('2026-07-13').includes('NÃO cite'));

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
check('sem sessões: 100% sem match, total $0', r2.join.sem_match_pct === 100 && r2.custo.total_usd_atribuido === 0);

// ── prWindow corta a EXIBIÇÃO, não a atribuição (G7: universo ≠ tabela) ─────────
const r3 = buildReport({ prs: PRS, sessions: SESSIONS, prWindow: 1, generated: '2026-07-12' });
check('prWindow=1 exibe só o merge mais recente (#15)', r3.janela.prs_exibidos === 1 && r3.por_pr[0].pr === 15);
check('prWindow=1 NÃO encolhe o universo de atribuição (4 PRs, $18 atribuídos)',
  r3.janela.prs_no_universo === 4 && r3.custo.total_usd_atribuido === 18);

// ── agregadores diretos (redundância de defesa) ─────────────────────────────────
const agg = aggregatePorBranch([ent('b1', 'm', 1, 2), ent('b1', 'm', 10)]);
check('aggregatePorBranch soma por (branch, modelo)', agg.porBranch.get('b1').get('m').input === 11);
check('aggregatePorModelo ignora branch', aggregatePorModelo([ent('b1', 'm', 1), ent('b2', 'm', 2)]).get('m').input === 3);

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — join 2-sinais morde (branch com $ certo de cache; citação diluída) e libera (sem match declarado, sem dupla contagem, modelo sem preço não inventa USD).');
process.exit(fails ? 1 : 0);
