#!/usr/bin/env node
/**
 * Bite-test do INCREMENTO do cc-watcher — o defeito medido em 2026-09-21.
 *
 * Por que existe: `readJsonl(filePath)` lia o arquivo INTEIRO e `processFile`
 * postava TODAS as mensagens. O `lineCount` era gravado no state (:342) e lido
 * só como `> 0` (:308) — WRITE-ONLY como offset. Qualquer arquivo cujo mtime
 * mudasse re-postava o conteúdo inteiro.
 *
 * Medido antes do conserto, no corpus real (882 arquivos, 66 processados):
 *   65.322 linhas RE-ENVIADAS para 7.258 de fato novas — 88,9% de desperdício.
 *   Um arquivo com 43 linhas novas de 2.627 reenviava as 2.627.
 *
 * O dano não era latência: era o balde de rate-limit de `throttle:60/min` do
 * grupo `api`, cuja chave é o IP (o grupo roda ANTES do `mcp.auth`, então não
 * há user no momento do throttle) — logo COMPARTILHADO por todas as sessões
 * Claude da máquina. Cada run queimava 16× esse balde contra as vizinhas.
 *
 * Exercita o CLI DE FORA (`node index.js --once`) contra um servidor HTTP real
 * e uma sandbox de projetos — o pipeline inteiro (ingestOnce → processFile →
 * readJsonl → postBatch), não um helper exportado. Assert sobre helper é o que
 * deixou o `--watch` verde e inerte por 141 dias (`memory/proibicoes.md`
 * §5 2026-07-30): mexer só no chamador deixaria um teste de helper passando.
 *
 *   node --test scripts/cc-watcher/incremental.test.mjs
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import http from 'node:http';
import { spawn } from 'node:child_process';

const AQUI = path.dirname(new URL(import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1'));
const CLI = path.join(AQUI, 'index.js');
const GLOB = 'D--oimpresso-com';

/** Servidor que conta o que REALMENTE chegou pela rede. */
async function servidorFake() {
  const recebidas = [];
  const sessoes = [];
  const srv = http.createServer((req, res) => {
    let body = '';
    req.on('data', (c) => { body += c; });
    req.on('end', () => {
      let payload = {};
      try { payload = JSON.parse(body); } catch { /* corpo inválido conta como 0 */ }
      const msgs = payload.messages || [];
      recebidas.push(...msgs);
      if (payload.session) sessoes.push(payload.session);
      res.writeHead(200, { 'content-type': 'application/json' });
      res.end(JSON.stringify({ messages_inserted: msgs.length, messages_duplicated: 0 }));
    });
  });
  await new Promise((r) => srv.listen(0, '127.0.0.1', r));
  return { srv, recebidas, sessoes, url: `http://127.0.0.1:${srv.address().port}/api/cc/ingest` };
}

/** Uma linha de jsonl no shape que o watcher espera. */
function linha(n, sessionId, ts) {
  return JSON.stringify({
    uuid: `${sessionId}-msg-${n}`,
    sessionId,
    timestamp: ts,
    type: 'user',
    cwd: 'D:/oimpresso.com',
    gitBranch: 'main',
    version: '1.0.0',
    message: { role: 'user', content: `mensagem numero ${n} com corpo suficiente` },
  }) + '\n';
}

/** Monta sandbox: <root>/projects/<GLOB>-x/<uuid>.jsonl com N linhas. */
function sandbox(nLinhas, sessionId) {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'cc-inc-'));
  const projects = path.join(root, 'projects', `${GLOB}-sandbox`);
  fs.mkdirSync(projects, { recursive: true });
  const jsonl = path.join(projects, `${sessionId}.jsonl`);
  let txt = '';
  for (let i = 1; i <= nLinhas; i++) {
    txt += linha(i, sessionId, `2026-09-21T10:00:${String(i % 60).padStart(2, '0')}.000Z`);
  }
  fs.writeFileSync(jsonl, txt);
  return { root, projects: path.join(root, 'projects'), jsonl, state: path.join(root, 'state.json') };
}

/** Roda o CLI de fora e devolve o que o servidor viu. */
function rodar(sb, url) {
  return new Promise((resolve) => {
    const p = spawn(process.execPath, [CLI, '--once'], {
      cwd: AQUI,
      env: {
        ...process.env,
        CC_PROJECTS_DIR: sb.projects,
        STATE_FILE: sb.state,
        PROJECT_GLOB: GLOB,
        MCP_URL: url,
        MCP_TOKEN: 'mcp_fake_para_teste',
      },
      stdio: ['ignore', 'pipe', 'pipe'],
    });
    let out = '';
    p.stdout.on('data', (d) => { out += d; });
    p.stderr.on('data', (d) => { out += d; });
    p.on('exit', (code) => resolve({ code, out }));
  });
}

test('MORDE: 2º run só posta o INCREMENTO, não o arquivo inteiro', async (t) => {
  const { srv, recebidas, url } = await servidorFake();
  t.after(() => srv.close());

  const sid = 'aaaaaaaa-1111-2222-3333-444444444444';
  const sb = sandbox(100, sid);

  const r1 = await rodar(sb, url);
  assert.equal(r1.code, 0, `1º run falhou: ${r1.out}`);
  const noPrimeiro = recebidas.length;
  assert.equal(noPrimeiro, 100, `1º run devia postar as 100 (postou ${noPrimeiro})`);

  // +5 linhas, e o mtime avança — exatamente o gatilho do re-envio total
  let extra = '';
  for (let i = 101; i <= 105; i++) extra += linha(i, sid, '2026-09-21T11:00:00.000Z');
  fs.appendFileSync(sb.jsonl, extra);
  fs.utimesSync(sb.jsonl, new Date(), new Date(Date.now() + 5000));

  recebidas.length = 0;
  const r2 = await rodar(sb, url);
  assert.equal(r2.code, 0, `2º run falhou: ${r2.out}`);

  assert.equal(recebidas.length, 5,
    `2º run postou ${recebidas.length} mensagens para 5 novas — é o desperdício de 88,9% de volta`);

  const ids = recebidas.map((m) => m.uuid);
  assert.ok(ids.includes(`${sid}-msg-105`), 'a última mensagem nova não foi postada');
  assert.ok(!ids.includes(`${sid}-msg-1`), 'reenviou a 1ª linha — o offset não cortou nada');
});

test('MORDE: o metadata da sessão sobrevive ao corte (started_at não anda pra frente)', async (t) => {
  const { srv, sessoes, url } = await servidorFake();
  t.after(() => srv.close());

  const sid = 'bbbbbbbb-1111-2222-3333-444444444444';
  const sb = sandbox(50, sid);

  await rodar(sb, url);
  const inicioPrimeiro = sessoes.at(-1)?.started_at;
  assert.ok(inicioPrimeiro, 'sessão não chegou no 1º run');

  fs.appendFileSync(sb.jsonl, linha(51, sid, '2026-09-21T23:59:00.000Z'));
  fs.utimesSync(sb.jsonl, new Date(), new Date(Date.now() + 5000));

  sessoes.length = 0;
  await rodar(sb, url);

  assert.equal(sessoes.at(-1)?.started_at, inicioPrimeiro,
    'started_at mudou no incremento — `upsertSession` faz updateOrCreate, então ' +
    'isso EMPURRA a data de início da sessão a cada run');
});

test('MORDE: arquivo TRUNCADO relê do zero em vez de perder mensagem', async (t) => {
  const { srv, recebidas, url } = await servidorFake();
  t.after(() => srv.close());

  const sid = 'cccccccc-1111-2222-3333-444444444444';
  const sb = sandbox(80, sid);

  await rodar(sb, url);

  // arquivo REESCRITO menor: o offset guardado (80) passa do fim (10)
  let novo = '';
  for (let i = 1; i <= 10; i++) novo += linha(i, sid, '2026-09-21T12:00:00.000Z');
  fs.writeFileSync(sb.jsonl, novo);
  fs.utimesSync(sb.jsonl, new Date(), new Date(Date.now() + 5000));

  recebidas.length = 0;
  await rodar(sb, url);

  assert.equal(recebidas.length, 10,
    `pós-truncamento postou ${recebidas.length} de 10 — pular por offset stale PERDE mensagem`);
});

test('CONTROLE NEGATIVO: sem linha nova, o run não posta nada (e não repete o trabalho)', async (t) => {
  const { srv, recebidas, url } = await servidorFake();
  t.after(() => srv.close());

  const sid = 'dddddddd-1111-2222-3333-444444444444';
  const sb = sandbox(40, sid);

  await rodar(sb, url);
  assert.equal(recebidas.length, 40, 'o 1º run tem que postar — senão este controle não vale nada');

  const entradaDe = () => {
    const st = JSON.parse(fs.readFileSync(sb.state, 'utf-8'));
    return st[Object.keys(st).find((k) => k.includes(sid))];
  };
  const mtimePrimeiro = entradaDe()?.mtime;
  assert.ok(mtimePrimeiro, 'o arquivo sumiu do state depois do 1º run');

  // mtime avança SEM linha nova. É o caso `real=3214 guardado=3214` medido em 21/09.
  fs.utimesSync(sb.jsonl, new Date(), new Date(Date.now() + 5000));

  recebidas.length = 0;
  await rodar(sb, url);

  assert.equal(recebidas.length, 0,
    `postou ${recebidas.length} mensagens sem nenhuma linha nova`);

  // O mtime do state tem que ACOMPANHAR, senão o arquivo é RELIDO do disco a cada
  // run pra sempre (o caminho `empty` não gravava state).
  //
  // Asserir `lineCount === 40` aqui NÃO serviria: o 1º run já o deixou em 40, então
  // o assert passa com ou sem o conserto — valor esperado que coincide com o que o
  // bug produz (`memory/proibicoes.md` §5 2026-09-05). Medido: com `lineCount` o
  // mutante que remove o `saveState` sobrevivia 4/4; com o mtime, ele cai.
  assert.ok(entradaDe().mtime > mtimePrimeiro,
    `mtime do state não avançou (${entradaDe().mtime} <= ${mtimePrimeiro}) — ` +
    'o arquivo será relido do disco em todo run daqui pra frente');
});

test('MORDE: 429 no meio NAO joga fora o progresso ja confirmado', async (t) => {
  // A perna que explica o indicio da sessao irma em 21/09: 455 sessoes POSTadas
  // num momento em que so 19 arquivos .jsonl tinham sido modificados em 24h.
  // `processFile` gravava o state DEPOIS do loop de batches; um `throw` de 429
  // pulava a gravacao inteira, entao o arquivo reenviava TUDO no run seguinte —
  // e reenviar tudo gera mais 429. Circulo que se alimenta.
  const recebidas = [];
  let falharAPartirDe = 3; // deixa passar 2 batches (400 msgs), depois 429 sempre
  let postsVistos = 0;
  const srv = http.createServer((req, res) => {
    let b = ''; req.on('data', (c) => { b += c; });
    req.on('end', () => {
      postsVistos++;
      let p = {}; try { p = JSON.parse(b); } catch {}
      if (postsVistos >= falharAPartirDe) {
        res.writeHead(429, { 'content-type': 'application/json', 'retry-after': '0' });
        res.end(JSON.stringify({ message: 'Too Many Attempts.' }));
        return;
      }
      recebidas.push(...(p.messages || []));
      res.writeHead(200, { 'content-type': 'application/json' });
      res.end(JSON.stringify({ messages_inserted: (p.messages || []).length, messages_duplicated: 0 }));
    });
  });
  await new Promise((r) => srv.listen(0, '127.0.0.1', r));
  t.after(() => srv.close());
  const url = `http://127.0.0.1:${srv.address().port}/api/cc/ingest`;

  const sid = 'eeeeeeee-1111-2222-3333-444444444444';
  const sb = sandbox(700, sid); // 4 batches de 200 (BATCH_SIZE=200)

  await rodar(sb, url);
  const confirmadasNoPrimeiro = recebidas.length;
  assert.ok(confirmadasNoPrimeiro >= 200,
    `o servidor devia ter confirmado >=200 antes do 429 (confirmou ${confirmadasNoPrimeiro})`);

  // Agora tudo passa. O mtime NAO e tocado de proposito: e o caso real de uma
  // sessao que ja encerrou e ficou entregue pela metade. Sem mexer no mtime, o
  // teste discrimina as duas formas de errar de uma vez —
  //   grava demais (mtime real no parcial) => o arquivo e PULADO e o resto se perde;
  //   grava de menos (nada no parcial)      => reenvia as 700 e alimenta o 429.
  // Com `utimesSync` aqui, a 1a forma passava despercebida: medido, o mutante que
  // troca `mtime: 0` por `mtime: stat.mtimeMs` sobrevivia 5/5.
  falharAPartirDe = Number.MAX_SAFE_INTEGER;
  postsVistos = 0;
  recebidas.length = 0;
  await rodar(sb, url);

  const esperado = 700 - confirmadasNoPrimeiro;
  assert.equal(recebidas.length, esperado,
    `2o run entregou ${recebidas.length}; ${confirmadasNoPrimeiro} ja estavam confirmadas, ` +
    `entao devia mandar ${esperado}. Zero = arquivo pulado com metade entregue (PERDA); ` +
    `700 = progresso jogado fora, que e o re-POST eterno sob 429.`);
});
