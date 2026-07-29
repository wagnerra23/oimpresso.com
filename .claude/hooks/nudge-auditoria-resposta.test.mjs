#!/usr/bin/env node
// Teste do nudge-auditoria-resposta.mjs. Deriva do CONTRATO (checklist pedido por [M]
// 2026-07-29), NAO do output do hook.
//
// FIXTURES SAO FRASES REAIS da sessao 2026-07-29 — cada positivo abaixo foi escrito
// de verdade pelo agente e passou batido. Teste com exemplo inventado prova que o
// regex casa com o exemplo; teste com a frase que ESCAPOU prova que o buraco fechou.
//
// Rodar: node .claude/hooks/nudge-auditoria-resposta.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { auditar, montarAviso, lastAssistantText, REGRAS, CABECALHO } from './nudge-auditoria-resposta.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'nudge-auditoria-resposta.mjs');
let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };

// ── POSITIVOS: frases REAIS que escaparam na sessao 2026-07-29 ──────────────────

check('ESTADO: "a tela carrega" sem ter aberto (frase real — a rota dava 500)',
  auditar('A rota existe no main e nao tem portao. Voce abre /products/unificado agora e a tela carrega, com as 5 sub-telas navegando.').includes('ESTADO'));

check('ESTADO: "ja esta ligada, e so abrir a URL" (frase real)',
  auditar('Para voce trabalhar, a tela ja esta ligada. E so abrir a URL.').includes('ESTADO'));

check('ESTADO: com acento — "a tela está pronta"',
  auditar('Conferi o controller e a tela está pronta pra uso.').includes('ESTADO'));

check('TENANT: "smoke biz=1" apontado como alvo (canon vivo e 98)',
  auditar('Espera aprovacao [W] + smoke biz=1 antes de mergear.').includes('TENANT'));

check('TENANT: com acento — "validou em biz=4"',
  auditar('Ele validou em biz=4 e passou.').includes('TENANT'));

check('BLAST: "não é tocada" sem diff (frase real, com acento)',
  auditar('A tela da Larissa não é tocada em nada disso. Rota propria, controller proprio.').includes('BLAST'));

check('CI: "0 failed" sem lane nem assertions (LC-13)',
  auditar('Rodei a suite e deu 0 failed, pode mergear.').includes('CI'));

check('PONTEIRO: cita .php sem arquivo:linha',
  auditar('O erro esta no ProdutoUnificadoController.php, no metodo que monta as categorias.').includes('PONTEIRO'));

// ── NEGATIVOS: a versao CORRIGIDA das mesmas falas nao pode acusar ──────────────

check('SILENCIO: ponteiro presente (routes/web.php:447)',
  auditar('O TODO do can:product.view esta em routes/web.php:447 e a permission existe.').includes('PONTEIRO') === false);

check('SILENCIO: ESTADO com prova de status HTTP literal',
  auditar('A tela carrega — confirmei: < HTTP/1.1 200 OK na rota autenticada.').includes('ESTADO') === false);

check('SILENCIO: ESTADO com screenshot',
  auditar('Esta pronto: segue o screenshot da tela depois do deploy.').includes('ESTADO') === false);

check('SILENCIO: TENANT reconhecendo o canon 98',
  auditar('O smoke roda em biz=98 (SEEDED_TENANT_ID), nunca em biz=1 nem biz=4.').includes('TENANT') === false);

check('SILENCIO: BLAST com git diff --stat',
  auditar('Nao afeta nada da Larissa — o git diff --stat mostra 1 arquivo tocado.').includes('BLAST') === false);

check('SILENCIO: BACKLOG citando US existente',
  auditar('Posso fazer o conserto agora. US-PROD-023 ja cobre a promocao das telas.').includes('BACKLOG') === false);

check('SILENCIO: BACKLOG dizendo explicitamente que NAO existe US',
  auditar('Vou implementar. Conferi o tasks-list: nenhuma task cobre esse bug.').includes('BACKLOG') === false);

check('SILENCIO: CI nomeando lane e assertions',
  auditar('A lane Estoque MySQL ficou verde: 4 passed (30 assertions).').includes('CI') === false);

check('SILENCIO: texto vazio', auditar('').length === 0);
check('SILENCIO: texto neutro', auditar('Bom dia, segue o resumo da reuniao.').length === 0);
check('SILENCIO: entrada nao-string', auditar(null).length === 0);

// ── montarAviso ────────────────────────────────────────────────────────────────

check('montarAviso: vazio quando nao ha violacao', montarAviso([]) === '');
check('montarAviso: inclui cabecalho e a regra', montarAviso(['ESTADO']).startsWith(CABECALHO) && montarAviso(['ESTADO']).includes('ESTADO:'));
check('montarAviso: entrada invalida → ""', montarAviso(null) === '');
check('REGRAS: todo id e unico', new Set(REGRAS.map((r) => r.id)).size === REGRAS.length);

// ── lastAssistantText (transcript JSONL) ───────────────────────────────────────

const tmp = mkdtempSync(join(tmpdir(), 'audresp-'));
const tp = join(tmp, 'transcript.jsonl');
writeFileSync(tp, [
  JSON.stringify({ type: 'user', message: { content: [{ type: 'text', text: 'oi' }] } }),
  JSON.stringify({ type: 'assistant', message: { content: [{ type: 'text', text: 'PRIMEIRA' }] } }),
  JSON.stringify({ type: 'assistant', message: { content: [{ type: 'text', text: 'a tela ja esta ligada' }] } }),
].join('\n'));
check('lastAssistantText: pega a ultima msg assistant', lastAssistantText(tp) === 'a tela ja esta ligada');
check('lastAssistantText: arquivo inexistente → ""', lastAssistantText(join(tmp, 'nope.jsonl')) === '');
check('lastAssistantText: path vazio → ""', lastAssistantText('') === '');

// ── E2E: advisory SEMPRE exit 0 ────────────────────────────────────────────────

function runHook(input) {
  return spawnSync(process.execPath, [HOOK], { input, encoding: 'utf8' });
}
const r1 = runHook(JSON.stringify({ transcript_path: tp }));
check('E2E: exit 0 mesmo acusando', r1.status === 0);
check('E2E: imprime o aviso quando classifica', r1.stdout.includes('AUDITORIA-RESPOSTA'));

const r2 = runHook(JSON.stringify({ transcript_path: join(tmp, 'nope.jsonl') }));
check('E2E: transcript ausente → exit 0 e silencio', r2.status === 0 && r2.stdout.trim() === '');

const r3 = runHook('nao-e-json');
check('E2E: stdin invalido → fail-open exit 0', r3.status === 0);

const r4 = runHook('');
check('E2E: stdin vazio → exit 0', r4.status === 0);

console.log(fails === 0 ? '\nTODOS OS TESTES PASSARAM' : `\n${fails} TESTE(S) FALHARAM`);
process.exit(fails === 0 ? 0 : 1);
