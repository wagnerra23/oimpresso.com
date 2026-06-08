#!/usr/bin/env node
// MEM-CC-UI-1 US-COPI-CC-040/041 — Watcher Node ingere ~/.claude/projects/*/*.jsonl
// pro MCP server (mcp.oimpresso.com/api/cc/ingest).
//
// Modos:
//   node index.js --once    — ingere todas as sessões 1× (good pra backfill)
//   node index.js --watch   — daemon (chokidar) monitora mudanças
//   node index.js (default) — once
//
// Config via env ou .env:
//   MCP_URL=https://mcp.oimpresso.com/api/cc/ingest
//   MCP_TOKEN=mcp_xxxxx  (Bearer)
//   PROJECT_GLOB=D--oimpresso-com*  (filtra subfolders)
//   STATE_FILE=~/.claude/.cc-watcher-state.json
//
// Idempotente: msg_uuid UNIQUE no servidor; re-rodar é seguro.

import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import readline from 'node:readline';

// ──────────────────────────────────────────────────────────────────────
// Config
// ──────────────────────────────────────────────────────────────────────
// Default = Hostinger (oimpresso.com) — onde a rota /api/cc/ingest está deployada.
// CT 100 (mcp.oimpresso.com) tem versão própria do código que pode não estar atualizada.
// Pra usar CT 100, defina MCP_URL=https://mcp.oimpresso.com/api/cc/ingest no env.
const MCP_URL = process.env.MCP_URL || 'https://oimpresso.com/api/cc/ingest';
const MCP_TOKEN = process.env.MCP_TOKEN || readTokenFromSettings();
const PROJECT_GLOB = process.env.PROJECT_GLOB || 'D--oimpresso-com';
const PROJECTS_DIR = path.join(os.homedir(), '.claude', 'projects');
const STATE_FILE = process.env.STATE_FILE || path.join(os.homedir(), '.claude', '.cc-watcher-state.json');
const BATCH_SIZE = 200;
const SKIP_TYPES = new Set(['queue-operation', 'attachment']); // ignoradas
const MIN_CONTENT_LEN = 2; // ignora msgs vazias

if (!MCP_TOKEN) {
  console.error('❌ MCP_TOKEN ausente. Defina via env ou em .claude/settings.local.json');
  process.exit(1);
}

const args = process.argv.slice(2);
const MODE = args.includes('--watch') ? 'watch' : 'once';

// ──────────────────────────────────────────────────────────────────────
// State (offset.json) — última linha ingerida por arquivo
// ──────────────────────────────────────────────────────────────────────
function loadState() {
  try { return JSON.parse(fs.readFileSync(STATE_FILE, 'utf-8')); } catch { return {}; }
}
function saveState(state) {
  fs.mkdirSync(path.dirname(STATE_FILE), { recursive: true });
  fs.writeFileSync(STATE_FILE, JSON.stringify(state, null, 2));
}

let state = loadState();

// ──────────────────────────────────────────────────────────────────────
// Lê token do .claude/settings.local.json se MCP_TOKEN não set
// ──────────────────────────────────────────────────────────────────────
function readTokenFromSettings() {
  // Tenta ler do .claude/settings.local.json no cwd, .., ../..
  const candidates = [
    path.join(process.cwd(), '.claude', 'settings.local.json'),
    path.join(process.cwd(), '..', '.claude', 'settings.local.json'),
    path.join(process.cwd(), '..', '..', '.claude', 'settings.local.json'),
    path.join(os.homedir(), '.claude', 'settings.local.json'),
  ];
  for (const p of candidates) {
    try {
      const raw = JSON.parse(fs.readFileSync(p, 'utf-8'));
      const auth = raw?.mcpServers?.oimpresso?.headers?.Authorization;
      if (auth?.startsWith('Bearer ')) {
        return auth.slice(7);
      }
    } catch {}
  }
  return null;
}

// ──────────────────────────────────────────────────────────────────────
// Lista projetos que casam com PROJECT_GLOB
// ──────────────────────────────────────────────────────────────────────
function listProjectFolders() {
  if (!fs.existsSync(PROJECTS_DIR)) return [];
  const all = fs.readdirSync(PROJECTS_DIR);
  return all
    .filter(name => name.startsWith(PROJECT_GLOB))
    .map(name => path.join(PROJECTS_DIR, name))
    .filter(p => fs.statSync(p).isDirectory());
}

// ──────────────────────────────────────────────────────────────────────
// Lê 1 jsonl, agrega session metadata + messages
// ──────────────────────────────────────────────────────────────────────
async function readJsonl(filePath) {
  const session = { messages: [] };
  let lineNum = 0;
  let firstTs = null;
  let lastTs = null;

  const stream = fs.createReadStream(filePath, { encoding: 'utf-8' });
  const rl = readline.createInterface({ input: stream, crlfDelay: Infinity });

  for await (const line of rl) {
    lineNum++;
    if (!line.trim()) continue;
    let row;
    try { row = JSON.parse(line); } catch { continue; }

    if (SKIP_TYPES.has(row.type)) continue;

    const ts = row.timestamp;
    if (ts) {
      if (!firstTs) firstTs = ts;
      lastTs = ts;
    }

    // Session metadata vem da 1ª mensagem que tem
    if (!session.uuid && row.sessionId) {
      session.uuid = row.sessionId;
      session.cc_version = row.version || null;
      session.entrypoint = row.entrypoint || null;
      session.project_path = row.cwd || null;
      session.git_branch = row.gitBranch || null;
    }

    // Mensagem real
    if (!row.uuid) continue;
    const msg = parseMessage(row);
    if (msg) session.messages.push(msg);
  }

  if (session.uuid) {
    session.started_at = firstTs;
    session.ended_at = lastTs;
  }
  return { session, lineCount: lineNum };
}

// ──────────────────────────────────────────────────────────────────────
// Parse 1 row JSONL → message shape esperado pelo backend
// ──────────────────────────────────────────────────────────────────────
function parseMessage(row) {
  const msg = {
    uuid: row.uuid,
    parent_uuid: row.parentUuid || null,
    type: row.type,
    role: row.message?.role || null,
    tool_name: null,
    content_text: null,
    content_json: null,
    tokens_in: row.message?.usage?.input_tokens || null,
    tokens_out: row.message?.usage?.output_tokens || null,
    cache_read: row.message?.usage?.cache_read_input_tokens || null,
    cache_write: row.message?.usage?.cache_creation_input_tokens || null,
    cost_usd: null,
    ts: row.timestamp,
  };

  // Extrai texto do content (varia muito)
  if (typeof row.message?.content === 'string') {
    msg.content_text = row.message.content;
  } else if (Array.isArray(row.message?.content)) {
    const parts = [];
    for (const c of row.message.content) {
      if (c.type === 'text') parts.push(c.text);
      if (c.type === 'tool_use') {
        msg.type = 'tool_use';
        msg.tool_name = c.name;
        parts.push(`[tool: ${c.name}] ${JSON.stringify(c.input).slice(0, 1000)}`);
      }
      if (c.type === 'tool_result') {
        msg.type = 'tool_result';
        if (typeof c.content === 'string') parts.push(c.content);
        else if (Array.isArray(c.content)) {
          for (const cc of c.content) {
            if (cc.type === 'text') parts.push(cc.text);
          }
        }
      }
    }
    msg.content_text = parts.join('\n').slice(0, 50000);
  }

  // Skipa se sem conteúdo útil
  if (!msg.content_text || msg.content_text.length < MIN_CONTENT_LEN) {
    if (msg.type !== 'tool_use' && msg.type !== 'tool_result') return null;
  }

  // Trunca se gigante
  if (msg.content_text && msg.content_text.length > 50000) {
    msg.content_text = msg.content_text.slice(0, 50000) + '...[truncated]';
  }

  return msg;
}

// ──────────────────────────────────────────────────────────────────────
// POST batch pra /api/cc/ingest
// ──────────────────────────────────────────────────────────────────────
async function postBatch(session, messages) {
  const payload = { session, messages };
  const res = await fetch(MCP_URL, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Authorization': `Bearer ${MCP_TOKEN}`,
    },
    body: JSON.stringify(payload),
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    throw new Error(`HTTP ${res.status}: ${data?.message || data?.error || res.statusText}`);
  }
  return data;
}

// ──────────────────────────────────────────────────────────────────────
// Processa 1 arquivo (incremental usando state)
// ──────────────────────────────────────────────────────────────────────
async function processFile(filePath) {
  const stat = fs.statSync(filePath);
  const fileKey = filePath;
  const lastIngested = state[fileKey] || { mtime: 0, lineCount: 0 };

  // Pula se mtime não mudou
  if (stat.mtimeMs <= lastIngested.mtime && lastIngested.lineCount > 0) {
    return { skipped: true, file: path.basename(filePath) };
  }

  const { session, lineCount } = await readJsonl(filePath);
  if (!session.uuid || session.messages.length === 0) {
    return { empty: true, file: path.basename(filePath) };
  }

  const sessionMeta = {
    uuid: session.uuid,
    project_path: session.project_path,
    git_branch: session.git_branch,
    cc_version: session.cc_version,
    entrypoint: session.entrypoint,
    started_at: session.started_at,
    ended_at: session.ended_at,
  };

  // Envia em batches
  let totalInserted = 0, totalDup = 0;
  for (let i = 0; i < session.messages.length; i += BATCH_SIZE) {
    const batch = session.messages.slice(i, i + BATCH_SIZE);
    try {
      const res = await postBatch(sessionMeta, batch);
      totalInserted += res.messages_inserted || 0;
      totalDup += res.messages_duplicated || 0;
      process.stdout.write('.');
    } catch (e) {
      console.error(`\n❌ ${path.basename(filePath)} batch ${i}: ${e.message}`);
      throw e;
    }
  }

  state[fileKey] = { mtime: stat.mtimeMs, lineCount };
  saveState(state);

  return {
    file: path.basename(filePath),
    session_uuid: session.uuid,
    messages: session.messages.length,
    inserted: totalInserted,
    dup: totalDup,
  };
}

// ──────────────────────────────────────────────────────────────────────
// Main
// ──────────────────────────────────────────────────────────────────────
async function ingestOnce() {
  const folders = listProjectFolders();
  console.log(`📂 ${folders.length} projeto(s) casando com '${PROJECT_GLOB}'`);

  let totalSessions = 0, totalMsgs = 0, totalIns = 0, totalDup = 0, totalSkip = 0;
  for (const folder of folders) {
    const jsonls = fs.readdirSync(folder).filter(f => f.endsWith('.jsonl'));
    console.log(`\n📁 ${path.basename(folder)} → ${jsonls.length} sessões`);
    for (const f of jsonls) {
      const filePath = path.join(folder, f);
      try {
        const r = await processFile(filePath);
        if (r.skipped) { totalSkip++; continue; }
        if (r.empty) continue;
        totalSessions++;
        totalMsgs += r.messages;
        totalIns += r.inserted;
        totalDup += r.dup;
        console.log(` ✓ ${r.file.slice(0, 8)}: ${r.messages} msgs (ins=${r.inserted} dup=${r.dup})`);
      } catch (e) {
        console.error(` ✗ ${f.slice(0, 8)}: ${e.message}`);
      }
    }
  }

  console.log(`\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━`);
  console.log(`📊 Resumo: ${totalSessions} sessões processadas, ${totalSkip} skipped (sem mudança)`);
  console.log(`           ${totalIns} mensagens novas, ${totalDup} já existiam`);
  console.log(`           total processado: ${totalMsgs} msgs`);
  console.log(`━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━`);
}

async function watch() {
  await ingestOnce();
  console.log('\n👀 Modo watch — monitorando mudanças (Ctrl+C pra sair)...');

  const { default: chokidar } = await import('chokidar');
  const folders = listProjectFolders();
  const watcher = chokidar.watch(folders.map(f => path.join(f, '*.jsonl')), {
    persistent: true,
    ignoreInitial: true,
    awaitWriteFinish: { stabilityThreshold: 2000, pollInterval: 500 },
  });

  watcher.on('change', async (filePath) => {
    console.log(`\n🔄 ${path.basename(filePath)} mudou — re-ingerindo`);
    try {
      const r = await processFile(filePath);
      if (r.skipped) return;
      console.log(` ✓ ${r.messages} msgs (ins=${r.inserted} dup=${r.dup})`);
    } catch (e) {
      console.error(` ✗ ${e.message}`);
    }
  });
  watcher.on('add', async (filePath) => {
    console.log(`\n➕ Nova sessão: ${path.basename(filePath)}`);
    try {
      const r = await processFile(filePath);
      console.log(` ✓ ${r.messages} msgs (ins=${r.inserted})`);
    } catch (e) {
      console.error(` ✗ ${e.message}`);
    }
  });
}

// ──────────────────────────────────────────────────────────────────────
// Run
// ──────────────────────────────────────────────────────────────────────
console.log(`🚀 oimpresso-cc-watcher v0.1`);
console.log(`   MCP_URL: ${MCP_URL}`);
console.log(`   PROJECT_GLOB: ${PROJECT_GLOB}`);
console.log(`   MODE: ${MODE}`);
console.log(`   STATE: ${STATE_FILE}`);
console.log('');

try {
  if (MODE === 'watch') await watch();
  else await ingestOnce();
} catch (e) {
  console.error('\n💥 Erro fatal:', e.message);
  console.error(e.stack);
  process.exit(1);
}
