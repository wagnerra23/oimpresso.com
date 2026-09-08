#!/usr/bin/env node
// Teste do PORTE loop-fechar-check.mjs (ex-.ps1). Deriva do CONTRATO (rotina idempotente:
// item feito por flag manual OU por arquivo existente), NÃO do .ps1. Advisory: SEMPRE exit 0.
// Rodar: node .claude/hooks/loop-fechar-check.test.mjs

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { readFileSync, existsSync } from 'node:fs';
import { itemDone, resolverItens, formatBanner, medirComando, formatBannerPrograma } from './loop-fechar-check.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'loop-fechar-check.mjs');
const REPO = join(dirname(HOOK), '..', '..');
let fails = 0;
const check = (n, c) => { console.log((c ? '[OK]   ' : '[FAIL] ') + n); if (!c) fails++; };

// exists fake: só 'app/pronto.php' existe
const fakeExists = (p) => String(p).replace(/\\/g, '/').endsWith('app/pronto.php');

// ── itemDone (puro, exists injetado) ─────────────────────────────────────────────
check('itemDone: manual done=true', itemDone({ detect: { tipo: 'manual' }, done: true }, '/r', fakeExists) === true);
check('itemDone: manual done=false', itemDone({ detect: { tipo: 'manual' }, done: false }, '/r', fakeExists) === false);
check('itemDone: file_any com arquivo existente → true', itemDone({ detect: { tipo: 'file_any', paths: ['app/pronto.php'] } }, '/r', fakeExists) === true);
check('itemDone: file_any sem arquivo → false', itemDone({ detect: { tipo: 'file_any', paths: ['app/falta.php'] } }, '/r', fakeExists) === false);
check('itemDone: sem detect → false', itemDone({}, '/r', fakeExists) === false);

// ── VETO MANUAL: `done:false` explícito reabre item file_any (bite-test) ─────────
// MORDE: o caso real do #6 — arquivo do detect EXISTE mas o DoD não fechou (flag de
// prod + sign-off [W]). Antes do fix isto dava `true` e o banner mentia "LOOP FECHADO".
check('MORDE: file_any com arquivo existente MAS done:false → false (veto manual)',
  itemDone({ detect: { tipo: 'file_any', paths: ['app/pronto.php'] }, done: false }, '/r', fakeExists) === false);
// Controles negativos: o veto NÃO pode engolir o caminho normal.
check('controle: file_any + done ausente → detect decide (true)',
  itemDone({ detect: { tipo: 'file_any', paths: ['app/pronto.php'] } }, '/r', fakeExists) === true);
check('controle: file_any + done:true → detect decide (true)',
  itemDone({ detect: { tipo: 'file_any', paths: ['app/pronto.php'] }, done: true }, '/r', fakeExists) === true);
check('controle: file_any + done:true mas arquivo sumiu → false (detect manda, item reabre)',
  itemDone({ detect: { tipo: 'file_any', paths: ['app/falta.php'] }, done: true }, '/r', fakeExists) === false);

// ── TERCEIRO ESTADO: descartado ≠ feito ≠ pendente ──────────────────────────────
const manifestDesc = { itens: [
  { ordem: 1, gap: '2', titulo: 'Entregue', prioridade: 'P0', detect: { tipo: 'file_any', paths: ['app/pronto.php'] } },
  { ordem: 2, gap: '6', titulo: 'LGPD purge', prioridade: 'P0', detect: { tipo: 'file_any', paths: ['app/pronto.php'] },
    done: false, descartado: true, razao_descarte: 'num ERP nao se apaga PII' },
] };
const bDesc = formatBanner(resolverItens(manifestDesc, '/r', fakeExists));
check('MORDE: item descartado sai da fila (nao vira PROXIMO PENDENTE)', !/PROXIMO PENDENTE/.test(bDesc));
check('MORDE: descartado NAO imprime [OK] (nao finge entrega)', !/\[OK\] #6/.test(bDesc) && /\[XX\] #6/.test(bDesc));
check('descartado mostra a razao ao lado', /DESCARTADO por decisao \[W\].*num ERP nao se apaga PII/.test(bDesc));
check('resumo separa entregue de descartado', /1 entregue\(s\), 1 descartado\(s\)/.test(bDesc));
check('descartado NAO reabre por causa do veto done:false',
  itemDone({ detect: { tipo: 'file_any', paths: ['app/pronto.php'] }, done: false, descartado: true }, '/r', fakeExists) === false);
// controle: sem a flag, o item volta a ser pendente normal
const bSemDesc = formatBanner(resolverItens(
  { itens: [{ ordem: 1, gap: '6', titulo: 'X', prioridade: 'P0', detect: { tipo: 'file_any', paths: ['app/pronto.php'] }, done: false }] },
  '/r', fakeExists));
check('controle: sem descartado, done:false segue PENDENTE', /PROXIMO PENDENTE: #6/.test(bSemDesc));

// ── E2E de contrato: o escape valve que o banner ANUNCIA precisa funcionar ───────
// O texto "(Para reabrir um item, mude 'done' no manifesto.)" era falso pros file_any.
const manifestVeto = { itens: [
  { ordem: 1, gap: '6', titulo: 'LGPD purge (codigo pronto, flag prod off)', prioridade: 'P0',
    detect: { tipo: 'file_any', paths: ['app/pronto.php'] }, done: false,
    precisa_aprovacao_wagner: true, nota_aprovacao: 'exige canary 7d + sign-off' },
] };
const bannerVeto = formatBanner(resolverItens(manifestVeto, '/r', fakeExists));
check('E2E: item file_any vetado aparece como PROXIMO PENDENTE (nao LOOP FECHADO)',
  /PROXIMO PENDENTE: #6/.test(bannerVeto) && !/LOOP FECHADO/.test(bannerVeto));
check('E2E: item vetado que exige [W] mostra o aviso de aprovacao',
  /EXIGE APROVACAO DO WAGNER/.test(bannerVeto));

// ── resolverItens: ordena + resolve done ─────────────────────────────────────────
const manifest = { itens: [
  { ordem: 2, gap: 'G2', titulo: 'Segundo', prioridade: 'P1', detect: { tipo: 'manual' }, done: false },
  { ordem: 1, gap: 'G1', titulo: 'Primeiro', prioridade: 'P0', detect: { tipo: 'file_any', paths: ['app/pronto.php'] } },
] };
const itens = resolverItens(manifest, '/r', fakeExists);
check('resolverItens ordena por ordem', itens[0].gap === 'G1' && itens[1].gap === 'G2');
check('resolverItens resolve done (G1 feito, G2 pendente)', itens[0].done === true && itens[1].done === false);

// ── formatBanner ─────────────────────────────────────────────────────────────────
check('formatBanner mostra proximo pendente', /PROXIMO PENDENTE: #G2/.test(formatBanner(itens)));
check('formatBanner: tudo feito → LOOP FECHADO', /LOOP FECHADO/.test(formatBanner([{ ordem: 1, gap: 'X', titulo: 't', done: true }])));
check('formatBanner vazio quando sem itens', formatBanner([]) === '');

// ── E2E: sem manifesto no repo → exit 0 silencioso (fail-open) ───────────────────
const r = spawnSync(process.execPath, [HOOK], { encoding: 'utf8', cwd: dirname(fileURLToPath(import.meta.url)) });
check('E2E: roda sem crash → exit 0', r.status === 0);

// ═══════════════════════════════════════════════════════════════════════════════
// ADR 0391 — detect `comando`: MORDE por COMPORTAMENTO (roda a porta viva e compara
// o número), nunca por presença. Tri-estado: feito | pendente | nao_medido.
// ═══════════════════════════════════════════════════════════════════════════════
const runnerDe = (status, stdout, stderr = '') => () => ({ status, stdout, stderr });
const detCasos = { tipo: 'comando', cmd: 'x', regex: '"missing_casos":\\s*(\\d+)', op: '<=', alvo: 0 };
check('comando: numero no alvo → feito', medirComando(detCasos, { runner: runnerDe(0, '{"missing_casos": 0}') }).estado === 'feito');
check('MORDE: numero fora do alvo → pendente (valor 67)', (() => { const r = medirComando(detCasos, { runner: runnerDe(0, '{"missing_casos": 67}') }); return r.estado === 'pendente' && r.valor === 67; })());
check('MORDE: exit≠0 → nao_medido (nao vira pendente nem feito — §5 2026-07-29)', medirComando(detCasos, { runner: runnerDe(2, '{"missing_casos": 0}') }).estado === 'nao_medido');
check('MORDE: regex nao casou → nao_medido', medirComando(detCasos, { runner: runnerDe(0, 'nada') }).estado === 'nao_medido');
check('comando: regex_ausente_vale 0 → feito quando o banner some (0 classes)', medirComando({ ...detCasos, regex_ausente_vale: 0 }, { runner: runnerDe(0, 'nada') }).estado === 'feito');
const detFrac = { tipo: 'comando', cmd: 'x', regex: 'E2E[^:]*:\\s*(\\d+)/(\\d+)', op: '>=', alvo_grupo: 2 };
check('comando: alvo_grupo deriva o alvo da propria saida (54/218 → pendente, alvo 218)', (() => { const r = medirComando(detFrac, { runner: runnerDe(0, 'E2E (Browser ∪ VRT)  : 54/218') }); return r.estado === 'pendente' && r.alvo === 218 && r.valor === 54; })());
check('comando: alvo_grupo 218/218 → feito', medirComando(detFrac, { runner: runnerDe(0, 'E2E : 218/218') }).estado === 'feito');
const detExit = { tipo: 'comando', cmd: 'x', op: 'exit0' };
check('comando exit0: rc 0 → feito', medirComando(detExit, { runner: runnerDe(0, '') }).estado === 'feito');
check('comando exit0: rc 1 → pendente (SLA violado e MEDIDO)', medirComando(detExit, { runner: runnerDe(1, '') }).estado === 'pendente');
check('MORDE: exit0 com rc 127 (comando ausente) → nao_medido, nao pendente', medirComando(detExit, { runner: runnerDe(127, '') }).estado === 'nao_medido');
check('comando: runner com error (nao spawnou) → nao_medido', medirComando(detExit, { runner: () => ({ status: null, stdout: '', stderr: '', error: new Error('ENOENT') }) }).estado === 'nao_medido');
check('comando: contar_linhas conta linhas nao-vazias do stdout', medirComando({ tipo: 'comando', cmd: 'x', contar_linhas: true, op: '<=', alvo: 0 }, { runner: runnerDe(0, 'a.blade.php\nb.blade.php\n') }).valor === 2);
check('comando: sem cmd → nao_medido', medirComando({ tipo: 'comando' }, { runner: runnerDe(0, '') }).estado === 'nao_medido');

// resolverItens com comando: veto manual, sob_demanda/cache, tri-estado no banner
const manComando = { slug: 'prog-teste', tipo: 'programa', _titulo: 'Programa de teste', itens: [
  { id: 'a', ordem: 1, gap: 'E1', titulo: 'feita', prioridade: 'P0', detect: { ...detCasos } },
  { id: 'b', ordem: 2, gap: 'E2', titulo: 'vetada', prioridade: 'P0', done: false, detect: { ...detCasos } },
  { id: 'c', ordem: 3, gap: 'E3', titulo: 'sob demanda', prioridade: 'P1', detect: { ...detCasos, medir: 'sob_demanda' } },
] };
const okRunner = runnerDe(0, '{"missing_casos": 0}');
const itC = resolverItens(manComando, '/r', fakeExists, { runner: okRunner, cache: null });
check('resolverItens comando: medido feito', itC[0].estado === 'feito' && itC[0].done === true);
check('MORDE: veto done:false derruba um feito MEDIDO', itC[1].estado === 'pendente' && itC[1].done === false);
check('resolverItens comando: sob_demanda sem --medir e sem cache → nao_medido', itC[2].estado === 'nao_medido');
const itCache = resolverItens(manComando, '/r', fakeExists, { runner: okRunner, cache: { medido_em: '2026-09-01T00:00:00Z', itens: { c: { estado: 'pendente', valor: 3, alvo: 0, medido_em: '2026-09-01T00:00:00Z' } } } });
check('resolverItens comando: sob_demanda com cache → usa o retrato DATADO', itCache[2].estado === 'pendente' && itCache[2].deCache === '2026-09-01T00:00:00Z');
const itMedir = resolverItens(manComando, '/r', fakeExists, { runner: okRunner, cache: null, medir: true });
check('resolverItens comando: --medir roda o sob_demanda', itMedir[2].estado === 'feito');
// cache com validade (TTL 24h) + memo por comando — custo de SessionStart sob controle
const agora = Date.parse('2026-09-07T12:00:00Z');
const cacheFresco = { itens: { a: { estado: 'pendente', valor: 5, alvo: 0, medido_em: '2026-09-07T11:00:00Z' } } };
const cacheVencido = { itens: { a: { estado: 'pendente', valor: 5, alvo: 0, medido_em: '2026-09-06T06:00:00Z' } } };
let chamadas = 0; const runnerConta = (...a) => { chamadas++; return okRunner(...a); };
const itFresco = resolverItens({ itens: [manComando.itens[0]] }, '/r', fakeExists, { runner: runnerConta, cache: cacheFresco, agoraMs: agora });
check('cache dentro do TTL: usa o retrato datado e NAO roda o comando', itFresco[0].estado === 'pendente' && itFresco[0].deCache === '2026-09-07T11:00:00Z' && chamadas === 0);
const itVencido = resolverItens({ itens: [manComando.itens[0]] }, '/r', fakeExists, { runner: runnerConta, cache: cacheVencido, agoraMs: agora });
check('MORDE: cache vencido (30h) → mede de novo (runner chamado, estado atual)', itVencido[0].estado === 'feito' && itVencido[0].deCache === null && chamadas === 1);
chamadas = 0;
resolverItens({ itens: [manComando.itens[0], { ...manComando.itens[0], id: 'a2', gap: 'E1b' }] }, '/r', fakeExists, { runner: runnerConta, cache: null });
check('memo: duas etapas com o MESMO cmd rodam o comando 1 vez', chamadas === 1);
const bp = formatBannerPrograma(itC, manComando, { cmdMedir: 'X' });
check('banner programa: [OK]/[--]/[??] distintos + resumo conta os tres', /\[OK\] E1/.test(bp) && /\[--\] E2/.test(bp) && /\[\?\?\] E3/.test(bp) && /1 feita\(s\) · 1 pendente\(s\) · 1 nao medida\(s\)/.test(bp));
check('banner programa: NAO MEDIDO nunca vira "cumprido"', !/PROGRAMA CUMPRIDO/.test(bp));
check('banner programa: nao carrega a linha Brain B do IA-OS', !/Brain B/.test(bp));
check('banner IA-OS (default) segue byte-compativel: item comando nao muda o formato antigo', /PROXIMO PENDENTE: #G2/.test(formatBanner(itens)));

// ── O MANIFESTO REAL (.claude/regime-evolucao.json): cada etapa É VÁLIDA POR TESTE ──
// Não basta o JSON estar bem formado: o selftest RODA o detect de cada etapa e falha se
// alguma não mediu. Etapa sob demanda só escapa declarando a dependência externa.
const REG = join(REPO, '.claude', 'regime-evolucao.json');
let reg = null; try { reg = JSON.parse(readFileSync(REG, 'utf8')); } catch { /* falha abaixo */ }
check('regime-evolucao.json existe, e programa e tem etapas', !!reg && reg.tipo === 'programa' && Array.isArray(reg.itens) && reg.itens.length > 0);
if (reg) {
  const ids = new Set();
  for (const it of reg.itens) {
    const d = it.detect || {};
    const completo = d.tipo === 'comando' && typeof d.cmd === 'string' && d.cmd.length > 0
      && (d.op === 'exit0' || ((d.regex || d.contar_linhas) && (Number.isFinite(d.alvo) || d.alvo_grupo)))
      && typeof it.ancora === 'string' && typeof it.executor === 'string' && typeof it.id === 'string' && !ids.has(it.id);
    ids.add(it.id);
    check(`regime ${it.gap}: detect por COMPORTAMENTO completo (cmd+op+alvo) + ancora + executor + id unico`, completo);
    check(`regime ${it.gap}: sob_demanda so com dependencia_externa declarada`, d.medir !== 'sob_demanda' || typeof d.dependencia_externa === 'string');
  }
  const t0 = Date.now();
  const medidos = resolverItens(reg, REPO, existsSync, { medir: true, cache: null });
  for (const r of medidos) {
    const det = (reg.itens.find((i) => i.id === r.id) || {}).detect || {};
    const ok = r.estado !== 'nao_medido' || (det.medir === 'sob_demanda' && /exit|nao rodou/.test(r.motivo || ''));
    check(`regime ${r.gap}: MEDIU → ${r.estado}${r.valor != null ? ` (${r.valor}/${r.alvo})` : ''}${r.motivo ? ` [${r.motivo}]` : ''}`, ok);
  }
  console.log(`  (medicao real do regime em ${((Date.now() - t0) / 1000).toFixed(1)}s)`);
  // E2E: o hook de verdade, com --manifest, imprime o banner de PROGRAMA e sai 0
  const rp = spawnSync(process.execPath, [HOOK, '--manifest', '.claude/regime-evolucao.json'], { encoding: 'utf8', cwd: REPO });
  check('E2E: hook --manifest regime → exit 0 e banner PROGRAMA', rp.status === 0 && /PROGRAMA: /.test(rp.stdout) && /RESUMO:/.test(rp.stdout));
}

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — porte .mjs resolve itens idempotente (manual/arquivo/comando), aponta pendente, advisory exit 0.');
process.exit(fails ? 1 : 0);
