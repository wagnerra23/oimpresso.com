#!/usr/bin/env node
/**
 * Bite-test do WATCH do cc-watcher — os 2 defeitos medidos em 2026-09-18.
 *
 * Por que existe: o `--watch` ficou INERTE por 141 dias (nascimento do watcher,
 * f20982bb0 · 2026-04-30 → 2026-09-18) com o processo VIVO. `dead=399` no
 * heartbeat, `whats-active` cego, sessões paralelas sobrescrevendo arquivo uma
 * da outra sem aviso. Duas causas independentes:
 *
 *   (A) chokidar 4 REMOVEU suporte a glob → `watch('<pasta>/*.jsonl')` observa
 *       0 paths e nunca emite evento.
 *   (B) a lista de pastas era lida 1× no boot → worktree criada depois ficava
 *       invisível. Era o caso DOMINANTE: em 18/09 as 10 pastas com atividade
 *       do dia eram todas pós-boot.
 *
 * Exercita `createJsonlWatcher` — a fiação REAL que produção usa (chokidar real,
 * objeto de opções real, filtro real), não uma cópia. Assert sobre cópia paralela
 * é o que deixou (A) verde todo esse tempo (`memory/proibicoes.md` §5 2026-07-30).
 *
 *   node --test scripts/cc-watcher/watch.test.mjs
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

import chokidar from 'chokidar';

import { spawn } from 'node:child_process';

import { createJsonlWatcher, isRelevantJsonl, parseMessage, adquirirLock } from './index.js';

const GLOB = 'D--oimpresso-com';

/** Espera até `pred()` virar verdade (ou estourar). awaitWriteFinish = 2s em prod. */
async function esperar(pred, timeoutMs = 8000) {
  const ate = Date.now() + timeoutMs;
  while (Date.now() < ate) {
    if (pred()) return true;
    await new Promise((r) => setTimeout(r, 120));
  }
  return false;
}

/**
 * Sobe o watcher real sobre um PROJECTS_DIR temporário.
 *
 * O `ready` tem TETO de propósito: medido em 2026-09-18, com o glob de volta o
 * chokidar 4 **nunca emite `ready`** — sem teto a suíte PENDURA em vez de falhar,
 * e hang em CI é indistinguível de flake de infra. Teto ⇒ falha com a causa escrita.
 */
async function subir(t) {
  const raiz = fs.mkdtempSync(path.join(os.tmpdir(), 'cc-watch-test-'));
  const hits = [];
  const watcher = createJsonlWatcher(chokidar, raiz, (p) => hits.push(path.basename(p)));
  t.after(async () => {
    await watcher.close();
    fs.rmSync(raiz, { recursive: true, force: true });
  });

  const pronto = await new Promise((resolve) => {
    const id = setTimeout(() => resolve(false), 5000);
    watcher.on('ready', () => { clearTimeout(id); resolve(true); });
  });
  assert.ok(pronto,
    'watcher não emitiu `ready` em 5s — é a assinatura do glob em chokidar 4 (path irresolvível)');

  return { raiz, hits, watcher };
}

test('MORDE (A): mudança em .jsonl de pasta do projeto emite evento', async (t) => {
  const { raiz, hits } = await subir(t);
  const pasta = path.join(raiz, `${GLOB}--claude-worktrees-fixture-aaa111`);
  fs.mkdirSync(pasta, { recursive: true });
  const arq = path.join(pasta, 'sessao.jsonl');
  fs.writeFileSync(arq, '{"type":"user"}\n');

  assert.ok(await esperar(() => hits.includes('sessao.jsonl')),
    'o watcher não viu o .jsonl — watch inerte (é o defeito A de volta)');
});

test('MORDE (B): pasta de projeto criada DEPOIS do ready é descoberta sem restart', async (t) => {
  const { raiz, hits } = await subir(t);

  // Nasce só agora — exatamente o caso das 10 worktrees de 18/09.
  const nova = path.join(raiz, `${GLOB}--claude-worktrees-pos-boot-bbb222`);
  fs.mkdirSync(nova, { recursive: true });
  fs.writeFileSync(path.join(nova, 'nova-sessao.jsonl'), '{"type":"user"}\n');

  assert.ok(await esperar(() => hits.includes('nova-sessao.jsonl')),
    'pasta pós-boot invisível — é o defeito B de volta (lista lida 1× no boot)');
});

test('CONTROLE NEGATIVO: .jsonl fora do PROJECT_GLOB não é ingerido', async (t) => {
  const { raiz, hits } = await subir(t);
  const outro = path.join(raiz, 'Z--outro-projeto-qualquer');
  fs.mkdirSync(outro, { recursive: true });
  fs.writeFileSync(path.join(outro, 'alheia.jsonl'), '{"type":"user"}\n');

  // Âncora: espera um hit LEGÍTIMO pra provar que o watcher está vivo — sem isso,
  // "0 eventos" seria indistinguível de watcher morto (ausência de medição).
  const pasta = path.join(raiz, `${GLOB}--ancora-ccc333`);
  fs.mkdirSync(pasta, { recursive: true });
  fs.writeFileSync(path.join(pasta, 'ancora.jsonl'), '{"type":"user"}\n');

  assert.ok(await esperar(() => hits.includes('ancora.jsonl')), 'âncora não chegou — teste cego');
  assert.ok(!hits.includes('alheia.jsonl'), 'ingeriu projeto de fora do glob');
});

test('CONTROLE NEGATIVO: arquivo não-.jsonl na pasta do projeto é ignorado', async (t) => {
  const { raiz, hits } = await subir(t);
  const pasta = path.join(raiz, `${GLOB}--ddd444`);
  fs.mkdirSync(pasta, { recursive: true });
  fs.writeFileSync(path.join(pasta, 'notas.md'), '# nada\n');
  fs.writeFileSync(path.join(pasta, 'ok.jsonl'), '{"type":"user"}\n');

  assert.ok(await esperar(() => hits.includes('ok.jsonl')), 'âncora não chegou — teste cego');
  assert.ok(!hits.includes('notas.md'), 'ingeriu arquivo que não é .jsonl');
});

test('ANTI-REGRESSÃO: o watcher observa >0 paths (glob de volta ⇒ 0 e este teste cai)', async (t) => {
  const { raiz, watcher } = await subir(t);
  fs.mkdirSync(path.join(raiz, `${GLOB}--eee555`), { recursive: true });

  await esperar(() => Object.values(watcher.getWatched()).flat().length > 0);
  const observados = Object.values(watcher.getWatched()).flat().length;

  assert.ok(observados > 0,
    `0 paths observados — chokidar 4 não tem glob; reintroduzir '<pasta>/*.jsonl' zera isto`);
});

test('predicado isRelevantJsonl: pasta-pai decide, extensão decide', () => {
  const ok = path.join('x', `${GLOB}--w-fff666`, 's.jsonl');
  assert.equal(isRelevantJsonl(ok), true);
  assert.equal(isRelevantJsonl(path.join('x', 'Z--outro', 's.jsonl')), false);
  assert.equal(isRelevantJsonl(path.join('x', `${GLOB}--w`, 's.md')), false);
});

// ──────────────────────────────────────────────────────────────────────
// content_json — a metade "paths tocados" do whats-active
// ──────────────────────────────────────────────────────────────────────

/** Monta um row JSONL de tool_use como o Claude Code grava. */
function rowToolUse(name, input) {
  return {
    uuid: 'u-' + name,
    timestamp: '2026-09-18T12:00:00Z',
    type: 'assistant',
    message: { role: 'assistant', content: [{ type: 'tool_use', name, input }] },
  };
}

test('MORDE: Edit emite content_json no shape que o whats-active consome', () => {
  // Path do caso REAL de 18/09 (4 sessões no mesmo arquivo). Montado por join pra
  // não depender de literal com barra invertida: escrito à mão, o par colapsa no
  // transporte e o teste segue verde testando outra string (LC-26 · §5 2026-09-09).
  const alvo = ['D:', 'oimpresso.com', 'app', 'Http', 'Controllers', 'ProductController.php'].join(path.sep);
  const m = parseMessage(rowToolUse('Edit', { file_path: alvo, old_string: 'a', new_string: 'b' }));

  assert.equal(m.tool_name, 'Edit');
  // Shape exato semeado por tests/.../WhatsActiveToolTest.php:136
  assert.deepEqual(m.content_json, { input: { file_path: alvo } },
    'content_json fora do contrato — o agregado de paths volta a ficar vazio');
});

test('MORDE: Write e NotebookEdit também preenchem (NotebookEdit via notebook_path)', () => {
  const w = parseMessage(rowToolUse('Write', { file_path: '/x/a.ts', content: 'z' }));
  assert.equal(w.content_json?.input?.file_path, '/x/a.ts');

  const n = parseMessage(rowToolUse('NotebookEdit', { notebook_path: '/x/n.ipynb', new_source: 'z' }));
  assert.equal(n.content_json?.input?.file_path, '/x/n.ipynb',
    'NotebookEdit está no whereIn do consumidor, então precisa de path também');
});

test('CONTROLE NEGATIVO: tool sem path não inventa content_json', () => {
  const b = parseMessage(rowToolUse('Bash', { command: 'ls -la' }));
  assert.equal(b.tool_name, 'Bash');
  assert.equal(b.content_json, null, 'não deve fabricar file_path pra tool que não tem');

  const vazio = parseMessage(rowToolUse('Edit', { file_path: '' }));
  assert.equal(vazio.content_json, null, 'string vazia não é path');
});

test('CONTROLE NEGATIVO: o input INTEIRO não vai pro content_json (anti-inchaço)', () => {
  const gordo = 'x'.repeat(50_000);
  const m = parseMessage(rowToolUse('Write', { file_path: '/x/a.ts', content: gordo }));

  assert.equal(m.content_json.input.content, undefined, 'conteúdo do arquivo não deve ser replicado');
  assert.ok(JSON.stringify(m.content_json).length < 200,
    `content_json inchou (${JSON.stringify(m.content_json).length} bytes) — guarda só o path`);
});

// ──────────────────────────────────────────────────────────────────────
// Lock de instância única — 3 daemons simultâneos medidos em 18/09
// ──────────────────────────────────────────────────────────────────────

test('MORDE: 2º daemon é recusado enquanto o 1º está VIVO', async (t) => {
  const lock = path.join(fs.mkdtempSync(path.join(os.tmpdir(), 'cc-lock-')), 'l.json');

  // processo REAL vivo pra fazer o papel do outro daemon (nada de PID inventado)
  const outro = spawn(process.execPath, ['-e', 'setTimeout(()=>{}, 60000)'], { stdio: 'ignore' });
  t.after(() => { try { outro.kill(); } catch {} });
  await esperar(() => typeof outro.pid === 'number', 3000);
  fs.writeFileSync(lock, JSON.stringify({ pid: outro.pid, desde: new Date().toISOString() }));

  assert.equal(adquirirLock(lock), false,
    'aceitou 2º daemon com o 1º vivo — é o stampede de 429 de volta');
});

test('MORDE: lock ÓRFÃO (processo morto) é assumido, não respeitado pra sempre', async (t) => {
  const lock = path.join(fs.mkdtempSync(path.join(os.tmpdir(), 'cc-lock-')), 'l.json');

  const morto = spawn(process.execPath, ['-e', ''], { stdio: 'ignore' });
  const pidMorto = morto.pid;
  await new Promise((r) => morto.on('exit', r));
  fs.writeFileSync(lock, JSON.stringify({ pid: pidMorto, desde: new Date().toISOString() }));

  assert.equal(adquirirLock(lock), true,
    'lock órfão bloqueou — um crash deixaria o pipe cego pra sempre');
  assert.equal(JSON.parse(fs.readFileSync(lock, 'utf-8')).pid, process.pid, 'não reescreveu o dono');
});

test('CONTROLE NEGATIVO: sem lock, e lock corrompido, não impedem a subida', () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'cc-lock-'));

  assert.equal(adquirirLock(path.join(dir, 'ausente.json')), true, 'ausência de lock não pode bloquear');

  const ruim = path.join(dir, 'ruim.json');
  fs.writeFileSync(ruim, 'isto nao e json {{{');
  assert.equal(adquirirLock(ruim), true, 'lock corrompido não pode bloquear');
});
