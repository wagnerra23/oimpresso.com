#!/usr/bin/env node
// Teste do PORTE loop-fechar-check.mjs (ex-.ps1). Deriva do CONTRATO (rotina idempotente:
// item feito por flag manual OU por arquivo existente), NÃO do .ps1. Advisory: SEMPRE exit 0.
// Rodar: node .claude/hooks/loop-fechar-check.test.mjs

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { itemDone, resolverItens, formatBanner } from './loop-fechar-check.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'loop-fechar-check.mjs');
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

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — porte .mjs resolve itens idempotente (manual/arquivo), aponta pendente, advisory exit 0.');
process.exit(fails ? 1 : 0);
