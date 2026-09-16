#!/usr/bin/env node
// redact.test.mjs — bite-test da redacao na fronteira de ingest do cc-watcher.
//
// Prova as DUAS pernas que o §5 exige (memory/proibicoes.md):
//   MORDE    — payload com segredo sai redigido
//   CONTROLE — payload benigno sai INTACTO (byte a byte)
//
// E exercita o CHOKEPOINT REAL (`postBatch`, o unico ponto que faz `fetch`) com
// `globalThis.fetch` stubado, inspecionando o body que IRIA pra rede. Assert
// sobre helper puro exportado nao prova contrato de pipeline (§5 2026-07-30).
//
// Rodar: node --test scripts/cc-watcher/redact.test.mjs
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { redactText, isPlaceholder } from './redact.mjs';
import { postBatch, parseMessage } from './index.js';

// Valor de teste com a FORMA exata do gerador (`mcp_` + 64 hex), nunca um token real.
const HEX64 = 'a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90';
const TOKEN_SHAPE = 'mcp_' + HEX64;

test('MORDE: a forma exata do incidente 2026-09-15 (Authorization JSON) e redigida', () => {
  const entrada = '"headers": { "Authorization": "Bearer ' + TOKEN_SHAPE + '" }';
  const { text, hits } = redactText(entrada);
  assert.equal(text.includes(HEX64), false, 'o valor do token NAO pode sobreviver');
  assert.equal(hits.mcp_token, 1);
  assert.match(text, /\[REDACTED:mcp_token\]/);
});

test('MORDE: outros shapes canonicos', () => {
  const casos = [
    ['sk-ant-api03-A1b2C3d4E5f6G7h8J9k0L1m2N3p4Q5r6S7t8U9v0', 'anthropic_key'],
    ['AKIA' + 'ABCDEFGHIJKLMNOP', 'aws_akia'],
    ['ghp_' + 'a'.repeat(36), 'github_pat'],
    ['-----BEGIN RSA PRIVATE KEY-----', 'private_key'],
    ['xoxb-123456789012-abcdefghijkl', 'slack_token'],
  ];
  for (const [valor, regra] of casos) {
    const { text, hits } = redactText('saida: ' + valor + ' fim');
    assert.equal(hits[regra], 1, 'regra ' + regra + ' deveria morder');
    assert.equal(text.includes(valor), false, valor.slice(0, 12) + ' sobreviveu');
  }
});

test('MORDE: senha de env dump (o caso mais frequente do corpus: 26 de 33 hits)', () => {
  const { text, hits } = redactText('DB_USERNAME=staging DB_PASSWORD=Zx9Kq2Lm7Pw4Rt8Nv3Bh6Yd1 APP_ENV=staging');
  assert.equal(hits.assign_generic, 1);
  assert.equal(text.includes('Zx9Kq2Lm7Pw4Rt8Nv3Bh6Yd1'), false);
  // o ROTULO sobrevive: o transcript ainda diz QUAL variavel era
  assert.match(text, /DB_PASSWORD=\[REDACTED:assign_generic\]/);
  assert.match(text, /DB_USERNAME=staging/, 'valor benigno vizinho intacto');
});

// ── CONTROLE NEGATIVO (obrigatorio) ─────────────────────────────────────────
test('CONTROLE: nome de tool MCP NAO e redigido (o FP que a medicao pegou)', () => {
  // A forma frouxa `mcp_[A-Za-z0-9_-]{16,}` dava 10.959 hits no corpus e casava
  // NOME DE TOOL. Se esta asserção cair, alguem afrouxou o padrao: RE-MEDIR.
  const entrada = 'chamei mcp__ccd_session_mgmt__list_sessions e mcp__oimpresso__brief-fetch';
  const { text, hits } = redactText(entrada);
  assert.equal(text, entrada, 'nome de tool tem que sair intacto');
  assert.deepEqual(hits, {});
});

test('CONTROLE: texto benigno sai byte a byte identico', () => {
  const entrada = [
    'const MCP_URL = process.env.MCP_URL || "https://oimpresso.com/api/cc/ingest";',
    'SELECT id, business_id FROM transactions WHERE final_total > 100;',
    'commit 59d54112e2d feat(governance): o gate distingue removido de MUDOU DE CASA',
    'cor de fundo #deadbeef e hash sha256 9c08945878571ecb76b70d25deb3852b',
  ].join('\n');
  const { text, hits } = redactText(entrada);
  assert.equal(text, entrada);
  assert.deepEqual(hits, {});
});

test('CONTROLE: placeholder/template NAO e redigido (vocabulario do .gitleaks.toml)', () => {
  for (const p of ['Bearer ${OIMPRESSO_MCP_TOKEN}', 'MCP_TOKEN=COLE_SEU_TOKEN_AQUI', 'API_KEY=your-token-example']) {
    const { text } = redactText(p);
    assert.equal(text, p, 'placeholder redigido a toa: ' + p);
  }
  assert.equal(isPlaceholder('${OIMPRESSO_MCP_TOKEN}'), true);
  assert.equal(isPlaceholder(HEX64), false);
});

// ── CHOKEPOINT REAL: o body que iria pra rede ───────────────────────────────
test('CHOKEPOINT: postBatch redige o payload ANTES do fetch', async () => {
  const original = globalThis.fetch;
  let corpoEnviado = null;
  globalThis.fetch = async (_url, opts) => {
    corpoEnviado = opts.body;
    return { ok: true, status: 200, json: async () => ({ messages_inserted: 1 }) };
  };
  try {
    await postBatch(
      { uuid: 's1', project_path: 'D:/oimpresso.com', git_branch: 'main' },
      [{ uuid: 'm1', type: 'tool_result', content_text: 'token: ' + TOKEN_SHAPE }]
    );
  } finally {
    globalThis.fetch = original;
  }
  assert.ok(corpoEnviado, 'o fetch precisa ter sido chamado');
  assert.equal(corpoEnviado.includes(HEX64), false, 'O TOKEN IRIA PRA REDE');
  assert.match(corpoEnviado, /\[REDACTED:mcp_token\]/);
  // o payload continua sendo JSON valido (redigimos o objeto, nunca o serializado)
  const parsed = JSON.parse(corpoEnviado);
  assert.equal(parsed.session.uuid, 's1');
});

test('CHOKEPOINT: campo NOVO no payload nasce coberto (redacao e recursiva)', async () => {
  const original = globalThis.fetch;
  let corpo = null;
  globalThis.fetch = async (_u, o) => { corpo = o.body; return { ok: true, status: 200, json: async () => ({}) }; };
  try {
    await postBatch(
      { uuid: 's2', campo_inventado_amanha: 'AKIAABCDEFGHIJKLMNOP' },
      [{ uuid: 'm2', aninhado: { fundo: ['ghp_' + 'b'.repeat(36)] } }]
    );
  } finally { globalThis.fetch = original; }
  assert.equal(corpo.includes('AKIAABCDEFGHIJKLMNOP'), false, 'campo novo vazou');
  assert.equal(corpo.includes('ghp_' + 'b'.repeat(36)), false, 'campo aninhado vazou');
});

// ── ANTI-STRADDLE: redacao antes do corte de 50k ────────────────────────────
test('parseMessage redige ANTES de truncar (segredo na fronteira dos 50k)', () => {
  // posiciona o token de modo que ele ATRAVESSE o corte de 50.000 chars
  const encheAte = 50000 - 20;
  const row = {
    uuid: 'm3',
    message: { role: 'user', content: [{ type: 'tool_result', content: 'x'.repeat(encheAte) + TOKEN_SHAPE }] },
  };
  const msg = parseMessage(row);
  assert.equal(msg.content_text.includes(HEX64), false, 'metade do token sobreviveu ao corte');
  assert.equal(msg.content_text.includes(HEX64.slice(0, 30)), false, 'prefixo do token sobreviveu');
});

test('CONTROLE: exemplo canonico de doc conta como placeholder (medido no corpus)', () => {
  // `AKIAIOSFODNN7EXAMPLE` e o valor de exemplo da documentacao da AWS; ele
  // aparece em fixture de teste do repo. O sufixo EXAMPLE cai no vocabulario de
  // placeholder herdado do .gitleaks.toml — por isso `aws_akia` fechou 0 hits na
  // medicao final, e nao porque a regra estivesse morta.
  const entrada = "['AKIAIOSFODNN7EXAMPLE',false]";
  assert.equal(redactText(entrada).text, entrada);
  // ja um AKIA sem marcador de exemplo MORDE:
  assert.equal(redactText('AKIAZ7Q2M4N8P1R5T3V6').hits.aws_akia, 1);
});

test('parseMessage redige input de tool_use ANTES do corte de 1000', () => {
  // um segredo no input de uma tool (ex.: curl com header) atravessando o corte
  const encheAte = 1000 - 20;
  const row = {
    uuid: 'm4',
    message: { role: 'assistant', content: [{ type: 'tool_use', name: 'Bash', input: { cmd: 'x'.repeat(encheAte) + TOKEN_SHAPE } }] },
  };
  const msg = parseMessage(row);
  assert.equal(msg.content_text.includes(HEX64), false, 'token sobreviveu no input da tool');
  assert.equal(msg.content_text.includes(HEX64.slice(0, 24)), false, 'prefixo sobreviveu ao corte');
});
