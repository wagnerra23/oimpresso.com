#!/usr/bin/env node
// brief-fetch-curl.mjs — SessionStart (PORTE cross-plataforma do brief-fetch-curl.ps1).
// Chama a tool MCP brief-fetch via HTTP POST JSON-RPC AUTENTICADO e injeta o brief (~3k tokens)
// no stdout do SessionStart. Resolve o sinal-de-degradação #1 (Claude pula o brief).
//
// ── POR QUE .mjs (US-GOV-052) ─ o .ps1 chamava `powershell -File`; `powershell` só existe no
// Windows do [W]. No Mac/Linux (time MCP Felipe/Maiara/Luiz) o hook evapora em silêncio → time
// abre sessão SEM brief. Supersede brief-fetch-curl.ps1.
//
// ── PORQUE fetch nativo (não curl.exe) ─ o .ps1 usava curl.exe só pra fugir de um BUG do
// PowerShell 5.1 (Invoke-RestMethod decodifica UTF-8 como Windows-1252). É defeito SÓ do PS —
// o `fetch` do node decodifica UTF-8 nativamente. Some o curl + o arquivo temporário.
//
// ── REQUISITOS DUROS (adversário 2026-07-20) ─────────────────────────────────────────
//  1. TIMEOUT explícito (AbortSignal.timeout) — o fetch do node NÃO tem timeout default; sem
//     ele um servidor lento PENDURA o SessionStart até o TCP do OS estourar (minutos).
//  2. PATH cross-platform — resolve subindo a árvore até achar .claude/settings.local.json
//     (nada de `D:/oimpresso.com/...` hardcoded, que era fallback morto no Mac/Linux).
//  3. REDAÇÃO DE TOKEN POR CONSTRUÇÃO — as razões de fallback são categorias FIXAS; NUNCA
//     interpolam o Authorization, os headers, a resposta, nem o texto de erro do parse do
//     settings (o .ps1 vazava em `Write-Fallback "...: $curlOutput"` e `$_.Exception.Message`).
//     Só err.name (estruturalmente sem token) é anexado em erro de rede.
//  4. Fail-open TOTAL — todo caminho de falha cai no fallback (handoff index) + exit 0.
//
// Selftest: node .claude/hooks/brief-fetch-curl.mjs --selftest

import { spawnSync } from 'node:child_process';
import { pathToFileURL } from 'node:url';
import { existsSync, readFileSync } from 'node:fs';
import { join, dirname, resolve } from 'node:path';

const ENDPOINT = 'https://mcp.oimpresso.com/api/mcp';
const TIMEOUT_MS = 10000;
const HANDOFF = 'memory/08-handoff.md';

// ── resolução do settings.local.json (cwd primeiro, subindo a árvore) ──
export function resolveSettingsPath(startCwd = process.cwd()) {
  let dir = resolve(startCwd);
  for (;;) {
    const cand = join(dir, '.claude', 'settings.local.json');
    if (existsSync(cand)) return cand;
    const parent = dirname(dir);
    if (parent === dir) return null; // raiz do filesystem
    dir = parent;
  }
}

// ── token: lê Authorization e valida prefixo. NUNCA devolve o valor em mensagem. ──
export function readAuthHeader(settingsText) {
  try {
    const s = JSON.parse(settingsText);
    const h = s && s.mcpServers && s.mcpServers.oimpresso && s.mcpServers.oimpresso.headers
      && s.mcpServers.oimpresso.headers.Authorization;
    if (typeof h === 'string' && h.startsWith('Bearer mcp_')) return h;
    return null;
  } catch { return null; }
}

// ── payload JSON-RPC (tools/call brief-fetch, sem args — cache server-side vence) ──
export function buildBody() {
  return JSON.stringify({ jsonrpc: '2.0', id: 1, method: 'tools/call', params: { name: 'brief-fetch', arguments: {} } });
}

// ── extrai o markdown do result.content[].text (formato MCP Tool Result). Razões FIXAS. ──
export function extractBrief(json) {
  if (!json || typeof json !== 'object') return { ok: false, reason: 'resposta MCP inválida' };
  if (json.error) return { ok: false, reason: 'MCP retornou error JSON-RPC' };
  const content = json.result && json.result.content;
  if (!Array.isArray(content)) return { ok: false, reason: 'estrutura inesperada (sem result.content)' };
  let text = '';
  for (const b of content) {
    if (b && b.type === 'text' && typeof b.text === 'string') text += b.text + '\n';
  }
  if (!text.trim()) return { ok: false, reason: 'conteúdo vazio' };
  return { ok: true, text };
}

export function readHandoffTail(n = 30, cwd = process.cwd()) {
  try {
    const p = join(cwd, HANDOFF);
    if (!existsSync(p)) return null;
    const linhas = readFileSync(p, 'utf8').replace(/^﻿/, '').split(/\r?\n/);
    if (linhas.length && linhas[linhas.length - 1] === '') linhas.pop();
    return linhas.slice(-n).join('\n');
  } catch { return null; }
}

export function fallbackText(reason, handoffTail) {
  const out = [
    '',
    `=== [brief-fetch hook] FALLBACK ATIVADO — motivo: ${reason} ===`,
    'MCP brief-fetch indisponível. Use ÍNDICE de handoffs como contexto inicial:',
    '',
  ];
  out.push(handoffTail != null ? handoffTail : `(${HANDOFF} não encontrado neste worktree)`);
  out.push('');
  out.push('⚠ Claude — sem brief, você opera com dados parciais. Rode brief-fetch manual (tool MCP) se conectado.');
  out.push('');
  return out.join('\n');
}

export function successText(briefText) {
  return [
    '',
    '=== [brief-fetch] Daily Brief — estado consolidado MCP oimpresso ===',
    '',
    briefText.trimEnd(),
    '',
    '=== [brief-fetch] FIM brief — use como contexto base, NÃO refaça queries que já estão aqui ===',
    '',
  ].join('\n');
}

// ── POST autenticado (fetch injetável pro selftest). Erro → {ok:false, reason}. ──
export async function fetchBrief({ fetchImpl, endpoint = ENDPOINT, authHeader, timeoutMs = TIMEOUT_MS }) {
  const f = fetchImpl || (typeof fetch !== 'undefined' ? fetch : null);
  if (!f) return { ok: false, reason: 'fetch indisponível (Node <18)' };
  try {
    const res = await f(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json; charset=utf-8', Authorization: authHeader },
      body: buildBody(),
      signal: AbortSignal.timeout(timeoutMs),
    });
    const json = await res.json();
    return { ok: true, json };
  } catch (e) {
    // err.name é estrutural (AbortError/TimeoutError/TypeError) — NUNCA carrega token. err.message NÃO entra.
    const errName = e && e.name ? e.name : 'Error';
    const reason = errName === 'TimeoutError' || errName === 'AbortError'
      ? 'servidor MCP não respondeu no tempo (timeout)'
      : `servidor MCP inalcançável (${errName})`;
    return { ok: false, reason };
  }
}

// ── orquestrador (tudo injetável pro selftest hermético) ──
export async function runBrief(opts = {}) {
  const cwd = opts.cwd || process.cwd();
  const handoffTail = () => readHandoffTail(30, cwd);
  try {
    // 1. settings path
    let settingsText = opts.settingsTextOverride;
    if (settingsText == null) {
      const path = opts.settingsPath || resolveSettingsPath(cwd);
      if (!path) return fallbackText('settings.local.json não encontrado (token MCP indisponível)', handoffTail());
      try { settingsText = readFileSync(path, 'utf8'); }
      catch { return fallbackText('settings.local.json ilegível (token indisponível)', handoffTail()); }
    }
    // 2. token
    const authHeader = readAuthHeader(settingsText);
    if (!authHeader) return fallbackText('token Authorization ausente/inválido em settings.local.json', handoffTail());
    // 3. POST
    const r = await fetchBrief({ fetchImpl: opts.fetchImpl, endpoint: opts.endpoint, authHeader, timeoutMs: opts.timeoutMs });
    if (!r.ok) return fallbackText(r.reason, handoffTail());
    // 4. extrai
    const ex = extractBrief(r.json);
    if (!ex.ok) return fallbackText(ex.reason, handoffTail());
    // 5. sucesso
    return successText(ex.text);
  } catch {
    // fail-open blindado — qualquer coisa inesperada NÃO derruba o SessionStart
    return fallbackText('erro inesperado no hook (fail-open)', handoffTail());
  }
}

async function main() {
  let out = '';
  try { out = await runBrief({}); } catch { out = ''; }
  try { if (out) process.stdout.write(out + '\n'); } catch { /* ignore */ }
  process.exit(0);
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const test = new URL('./brief-fetch-curl.test.mjs', import.meta.url);
    const r = spawnSync(process.execPath, [test.pathname], { stdio: 'inherit' });
    process.exit(r.status ?? 1);
  }
  main();
}
