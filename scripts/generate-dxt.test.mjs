#!/usr/bin/env node
/**
 * Teste do bridge DXT gerado por scripts/generate-dxt.js.
 *
 * INCIDENTE 2026-08-12 — o Claude Desktop pede `tools/list` VÁRIAS vezes por
 * handshake. Medido no log real da extensão: 153 `tools/list` para 94
 * `initialize` (e 3× contra 1× de prompts/resources num handshake observado).
 * Cada ida paga o pipeline inteiro do servidor — auth + audit contra o MySQL do
 * Hostinger, ~2,2s — para devolver bytes idênticos.
 *
 * O que este teste trava:
 *   1. o cache serve as repetições sem ir à rede;
 *   2. cada resposta carrega o id da SUA request (devolver o id antigo faria o
 *      cliente descartar a resposta — é o modo de falha silencioso desta
 *      otimização, e o que a torna arriscada sem teste);
 *   3. `MCP_TOOLS_CACHE_MS=0` DESLIGA — controle negativo, sem o qual este
 *      teste poderia estar verde por não exercitar nada.
 *
 * Roda o bridge de verdade, como processo, contra um servidor que CONTA
 * requisições. Sem mock do fetch: mock do transporte é justamente o que não
 * provaria que a ida à rede foi evitada.
 */
import { readFileSync, writeFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawn } from 'node:child_process';
import http from 'node:http';

const AQUI = dirname(fileURLToPath(import.meta.url));
const RAIZ = join(AQUI, '..');

function extrairBridge() {
  const src = readFileSync(join(RAIZ, 'scripts/generate-dxt.js'), 'utf8');
  const m = src.match(/const BRIDGE_JS = (`[\s\S]*?\n`);/);
  if (!m) throw new Error('não achei o BRIDGE_JS em scripts/generate-dxt.js');
  return eval(m[1]); // template literal — resolve os \` e \${} escapados
}

async function cenario({ ttlMs }) {
  const dir = mkdtempSync(join(tmpdir(), 'dxt-bridge-'));
  const bridgePath = join(dir, 'index.js');
  writeFileSync(bridgePath, extrairBridge());

  const idas = [];
  const RESULT = { tools: [{ name: 'probe-tool', description: 'x' }] };

  const server = http.createServer((req, res) => {
    let body = '';
    req.on('data', (c) => (body += c));
    req.on('end', () => {
      const msg = JSON.parse(body);
      idas.push(msg.method);
      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ jsonrpc: '2.0', id: msg.id, result: RESULT }));
    });
  });
  await new Promise((r) => server.listen(0, '127.0.0.1', r));

  const env = {
    ...process.env,
    MCP_URL: `http://127.0.0.1:${server.address().port}/`,
    MCP_AUTHORIZATION: 'Bearer probe',
  };
  if (ttlMs !== undefined) env.MCP_TOOLS_CACHE_MS = String(ttlMs);

  const proc = spawn(process.execPath, [bridgePath], { env, stdio: ['pipe', 'pipe', 'pipe'] });
  const respostas = [];
  proc.stdout.on('data', (d) => {
    for (const l of d.toString().split('\n')) if (l.trim()) respostas.push(JSON.parse(l));
  });

  // ids DIFERENTES, como o Desktop faz de verdade
  for (const [i, id] of [1, 4, 5].entries()) {
    proc.stdin.write(JSON.stringify({ jsonrpc: '2.0', id, method: 'tools/list', params: {} }) + '\n');
    await new Promise((r) => setTimeout(r, i === 0 ? 500 : 250));
  }
  await new Promise((r) => setTimeout(r, 500));

  proc.kill();
  server.close();
  return { idas, respostas };
}

let falhas = 0;
function checar(ok, msg) {
  console.log(`${ok ? '✓' : '✗'} ${msg}`);
  if (!ok) falhas++;
}

// --- caso 1: cache ligado (default) ---
const ligado = await cenario({});
checar(ligado.idas.length === 1, `cache ligado: 3 pedidos -> ${ligado.idas.length} ida(s) à rede (esperado 1)`);
checar(
  JSON.stringify(ligado.respostas.map((r) => r.id)) === JSON.stringify([1, 4, 5]),
  `cada resposta traz o id da sua request (recebido ${JSON.stringify(ligado.respostas.map((r) => r.id))})`,
);
checar(ligado.respostas.length === 3 && ligado.respostas.every((r) => r.result?.tools), 'as 3 respostas trazem o result completo');

// --- caso 2: controle negativo — TTL 0 desliga ---
const desligado = await cenario({ ttlMs: 0 });
checar(desligado.idas.length === 3, `TTL=0 desliga: ${desligado.idas.length} ida(s) à rede (esperado 3)`);

console.log(falhas === 0 ? '\nOK — bridge DXT: cache de tools/list' : `\nFALHOU (${falhas})`);
process.exit(falhas === 0 ? 0 : 1);
