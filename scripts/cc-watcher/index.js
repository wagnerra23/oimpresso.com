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
import { pathToFileURL } from 'node:url';
import { redactText, redactPayload, totalHits } from './redact.mjs';

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
      if (typeof auth !== 'string') continue;
      // O arquivo pode REFERENCIAR o ambiente em vez de guardar o segredo
      // (`Bearer ${OIMPRESSO_MCP_TOKEN}`) — mesma forma que o `readAuthHeader` do
      // `.claude/hooks/brief-fetch-curl.mjs` aceita desde 2026-09-15. Sem esta
      // expansão, o `slice(7)` devolveria o TEXTO `${OIMPRESSO_MCP_TOKEN}` como se
      // fosse o token e a chamada viraria um 401 opaco.
      const ref = /^Bearer \$\{([A-Za-z_][A-Za-z0-9_]*)\}$/.exec(auth);
      if (ref) {
        const v = process.env[ref[1]];
        // fail-closed: env ausente/vazia/forma errada → segue procurando, nunca
        // devolve a referência crua.
        if (typeof v === 'string' && v.startsWith('mcp_') && !v.includes('COLE_SEU')) return v;
        continue;
      }
      if (auth.startsWith('Bearer ')) {
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
export function parseMessage(row) {
  const msg = {
    uuid: row.uuid,
    parent_uuid: row.parentUuid || null,
    type: row.type,
    role: row.message?.role || null,
    model: row.message?.model || null,
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
    msg.content_text = redactText(row.message.content).text;
  } else if (Array.isArray(row.message?.content)) {
    const parts = [];
    for (const c of row.message.content) {
      if (c.type === 'text') parts.push(c.text);
      if (c.type === 'tool_use') {
        msg.type = 'tool_use';
        msg.tool_name = c.name;
        // redige ANTES do corte de 1000: mesma razao anti-straddle do corte de 50k
        parts.push(`[tool: ${c.name}] ${redactText(JSON.stringify(c.input)).text.slice(0, 1000)}`);

        // `content_json` com o path do arquivo tocado. Ficava SEMPRE null (era
        // inicializado assim e nunca atribuido), e o consumidor — o agregado
        // "paths tocados" do `whats-active` — filtra `whereNotNull('content_json')`
        // e le `input.file_path`. Resultado medido em 2026-09-18: a deteccao de
        // sobreposicao de path NUNCA funcionou, pra sessao nenhuma; era justo a
        // metade que serve pra duas sessoes nao se atropelarem no mesmo arquivo.
        //
        // O shape NAO e invencao minha: e o que o teste do proprio consumidor
        // semeia (`tests/Feature/Modules/Copiloto/Mcp/WhatsActiveToolTest.php`,
        // `json_encode(['input' => ['file_path' => $filePath]])`) — o contrato
        // existia, so o produtor nao o emitia. Era por isso que o teste ficava
        // verde com a producao quebrada: ele semeia a coluna direto no banco e
        // nunca atravessa este watcher.
        //
        // Guarda só o path (não o `input` inteiro) de propósito: input de Write
        // carrega o arquivo todo, e replicar isso em toda linha de tool_use
        // incharia a coluna sem consumidor que peça. `notebook_path` entra porque
        // o consumidor inclui NotebookEdit no `whereIn` de tool_name.
        const tocado = c.input?.file_path ?? c.input?.notebook_path ?? null;
        if (typeof tocado === 'string' && tocado !== '') {
          msg.content_json = { input: { file_path: tocado } };
        }
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
    // Redige ANTES de truncar: um segredo que atravessasse o corte de 50k
    // sobreviveria partido ao meio, sem casar padrao nenhum dos dois lados.
    msg.content_text = redactText(parts.join('\n')).text.slice(0, 50000);
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
export async function postBatch(session, messages) {
  // -- FRONTEIRA DE INGEST (ADR 0057 §10 . LC-35) --------------------
  // Daqui pra frente o conteudo sai da maquina e vira `mcp_cc_messages`, legivel
  // pelo time via `cc-search`. Este e o UNICO ponto do watcher que faz `fetch` --
  // redigir aqui cobre tambem `session` (project_path, git_branch) e qualquer
  // campo que venha a ser adicionado ao payload depois, sem ninguem lembrar.
  // Redige o objeto ja desserializado, nunca o JSON serializado: nao ha como
  // corromper a sintaxe.
  const redHits = {};
  const payload = redactPayload({ session, messages }, redHits);
  const redN = totalHits(redHits);
  if (redN > 0) {
    // loga SO a contagem por tipo -- jamais o valor (seria reintroduzir o vetor)
    const resumo = Object.entries(redHits).map(([k, v]) => k + '=' + v).join(' ');
    console.log('\n  [redacao] ' + redN + ' segredo(s) redigido(s): ' + resumo);
  }
  const corpo = JSON.stringify(payload);

  // Retry com espera pra 429/5xx. Antes era one-shot: throttle ⇒ o arquivo era
  // DESCARTADO com erro no log e o offset não avançava, então o dado só voltava
  // no próximo backfill manual. Medido em 2026-09-18, quando o `--watch` passou
  // a funcionar pela 1ª vez: 13 sessões escrevendo ao mesmo tempo estouram o
  // throttle da rota e a maioria dos POSTs virava 429 perdido.
  // `Retry-After` do Laravel manda; sem ele, backoff exponencial.
  const TENTATIVAS = 4;
  let ultimoErro = null;

  for (let tentativa = 1; tentativa <= TENTATIVAS; tentativa++) {
    const res = await fetch(MCP_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${MCP_TOKEN}`,
      },
      body: corpo,
    });

    const data = await res.json().catch(() => ({}));
    if (res.ok) return data;

    ultimoErro = `HTTP ${res.status}: ${data?.message || data?.error || res.statusText}`;

    const vaiRetentar = (res.status === 429 || res.status >= 500) && tentativa < TENTATIVAS;
    if (!vaiRetentar) break;

    const retryAfter = Number.parseInt(res.headers.get('retry-after') ?? '', 10);
    const esperaMs = Number.isFinite(retryAfter) && retryAfter > 0
      ? Math.min(retryAfter, 60) * 1000
      : Math.min(2 ** tentativa, 30) * 1000;

    console.log(`\n  [retry ${tentativa}/${TENTATIVAS - 1}] ${ultimoErro} — aguardando ${esperaMs / 1000}s`);
    await new Promise((r) => setTimeout(r, esperaMs));
  }

  throw new Error(ultimoErro ?? 'falha desconhecida no POST');
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

// ──────────────────────────────────────────────────────────────────────
// Lock de instância única (só no modo watch)
// ──────────────────────────────────────────────────────────────────────
const LOCK_FILE = path.join(os.homedir(), '.claude', '.cc-watcher.lock');

/**
 * Impede N daemons simultâneos. Medido em 2026-09-18: 3 rodando ao mesmo tempo,
 * cada um POSTando os MESMOS arquivos — o dedup por `msg_uuid` protege o dado,
 * mas o tráfego triplica e estoura o throttle da rota (HTTP 429). Aqui há ~89
 * sessões Claude simultâneas e qualquer uma pode subir um watcher, então a
 * proteção precisa ser do próprio daemon, não da disciplina de quem inicia.
 *
 * `process.kill(pid, 0)` não mata: só testa se o PID existe (ESRCH = morto).
 * Lock órfão (processo caiu sem liberar) é assumido, nunca respeitado — senão
 * um crash deixaria o pipe cego pra sempre, que é o modo de falha que esta
 * sessão foi consertar.
 */
export function adquirirLock(lockFile = LOCK_FILE) {
  try {
    const dono = JSON.parse(fs.readFileSync(lockFile, 'utf-8'));
    if (typeof dono?.pid === 'number' && dono.pid !== process.pid) {
      let vivo = true;
      try {
        process.kill(dono.pid, 0);
      } catch {
        vivo = false;
      }
      if (vivo) {
        console.error(`[erro] já há um cc-watcher em modo watch (PID ${dono.pid}, desde ${dono.desde}).`);
        console.error('       N daemons = N× POST do mesmo arquivo = HTTP 429. Encerre o outro antes,');
        console.error(`       ou remova ${lockFile} se tiver certeza de que ele morreu.`);
        return false;
      }
      console.log(`   [lock] assumindo lock órfão do PID ${dono.pid} (processo não existe mais)`);
    }
  } catch {
    // sem lock, ilegível ou corrompido → segue e reescreve
  }

  fs.mkdirSync(path.dirname(lockFile), { recursive: true });
  fs.writeFileSync(lockFile, JSON.stringify({ pid: process.pid, desde: new Date().toISOString() }, null, 2));

  const liberar = () => {
    try {
      const atual = JSON.parse(fs.readFileSync(lockFile, 'utf-8'));
      if (atual?.pid === process.pid) fs.unlinkSync(lockFile);
    } catch {}
  };
  process.on('exit', liberar);

  return true;
}

// Relevante = `.jsonl` cuja pasta-pai casa PROJECT_GLOB. O recorte do projeto é
// FILTRO DE HANDLER, não padrão de path — ver watch() pro motivo. Exportado pro
// bite-test poder asseverar o predicado isolado.
export function isRelevantJsonl(filePath) {
  if (!filePath.endsWith('.jsonl')) return false;

  return path.basename(path.dirname(filePath)).startsWith(PROJECT_GLOB);
}

// Monta o watcher REAL. Extraído (e exportado) de propósito: o bite-test precisa
// exercitar ESTA fiação — objeto de opções + filtro de handler —, não uma cópia
// dela. Assert sobre cópia paralela é o que deixou o defeito (A) verde por 141
// dias (`memory/proibicoes.md` §5 2026-07-30).
export function createJsonlWatcher(chokidar, projectsDir, onFile) {
  const watcher = chokidar.watch(projectsDir, {
    persistent: true,
    ignoreInitial: true,
    depth: 2,
    awaitWriteFinish: { stabilityThreshold: 2000, pollInterval: 500 },
  });

  // Fila SERIAL com coalescing por path. Sem ela, 13 sessões escrevendo ao mesmo
  // tempo disparam N ingests concorrentes que estouram o throttle da rota (429
  // medido em 2026-09-18 — o defeito só apareceu quando o watch passou a
  // funcionar, porque antes nenhum evento chegava aqui). O Map colapsa rajada de
  // mudanças do MESMO arquivo num trabalho só: o ingest é incremental por offset,
  // então processar 1× depois de 5 escritas pega as 5.
  const pendentes = new Map();
  let drenando = false;

  const drenar = async () => {
    if (drenando) return;
    drenando = true;
    try {
      while (pendentes.size > 0) {
        const [filePath, label] = pendentes.entries().next().value;
        pendentes.delete(filePath);
        try {
          await onFile(filePath, label);
        } catch (e) {
          // erro de 1 arquivo não pode matar a fila nem o daemon
          console.error(` ✗ ${path.basename(filePath)}: ${e?.message ?? e}`);
        }
      }
    } finally {
      drenando = false;
    }
  };

  const onHit = (filePath, label) => {
    if (!isRelevantJsonl(filePath)) return;
    pendentes.set(filePath, label);
    void drenar();
  };

  watcher.on('change', (filePath) => onHit(filePath, '🔄'));
  watcher.on('add', (filePath) => onHit(filePath, '➕'));
  watcher.on('error', (e) => console.error(`\n❌ watcher: ${e?.message ?? e}`));

  return watcher;
}

async function watch() {
  // ANTES do backfill: um 2º daemon não deve nem gastar o backfill inteiro
  // (412 pastas) pra descobrir no fim que era duplicado.
  if (!adquirirLock()) {
    process.exit(2);
  }

  await ingestOnce();
  console.log('\n👀 Modo watch — monitorando mudanças (Ctrl+C pra sair)...');

  const { default: chokidar } = await import('chokidar');

  // Observa o DIRETÓRIO-PAI, nunca um glob por pasta. Dois defeitos INDEPENDENTES
  // medidos em 2026-09-18 (watcher vivo desde 17/09 19:46, heartbeat dead=399):
  //
  //   (A) chokidar 4 REMOVEU suporte a glob. `watch('<pasta>/*.jsonl')` observa
  //       0 paths e nunca emite evento — medido com controle positivo (mesmo
  //       harness observando o diretório emite `change`). O `^4.0.3` está no
  //       package.json desde o NASCIMENTO do watcher (f20982bb0, 2026-04-30),
  //       logo `--watch` nunca funcionou: só o `ingestOnce()` do boot ingeria.
  //   (B) `listProjectFolders()` era lido 1× no boot, então pasta de projeto
  //       criada DEPOIS (worktree nova) nunca entrava na lista. Em 18/09 as 10
  //       pastas com atividade do dia eram TODAS pós-boot — o caso dominante,
  //       porque worktree nova é a rotina aqui.
  //
  // Observar PROJECTS_DIR cobre `<projects>/<pasta>/<arquivo>.jsonl` e torna a
  // descoberta de pasta nova automática (chokidar emite `add` pro arquivo dentro
  // do subdir novo, sem restart). `depth` é o nº de níveis ABAIXO do observado:
  // pasta = 1, arquivo dentro dela = 2 — valor fixado por bite-test, não por
  // leitura da doc.
  const watcher = createJsonlWatcher(chokidar, PROJECTS_DIR, async (filePath, label) => {
    console.log(`\n${label} ${path.basename(filePath)} — ingerindo`);
    try {
      const r = await processFile(filePath);
      if (r.skipped) return;
      console.log(` ✓ ${r.messages} msgs (ins=${r.inserted} dup=${r.dup})`);
    } catch (e) {
      console.error(` ✗ ${e.message}`);
    }
  });

  // Instrumento que não consegue observar DIZ isso, em vez de ficar inerte calado
  // (foi assim que o defeito (A) sobreviveu 141 dias: processo vivo, 0 evento).
  await new Promise((resolve) => watcher.on('ready', resolve));
  const observados = Object.values(watcher.getWatched()).flat().length;
  console.log(`   observando ${PROJECTS_DIR} (depth 2) — ${observados} path(s), filtro '${PROJECT_GLOB}*/**.jsonl'`);
  if (observados === 0) {
    console.error('   ⚠️ 0 paths observados — watch INERTE. Não confie no pipe até investigar.');
  }
}

// ──────────────────────────────────────────────────────────────────────
// Run -- guardado por entry-point (pathToFileURL: cross-plataforma, backslash
// do Windows nao quebra). Sem esta guarda, `import` deste arquivo pelo teste
// dispararia o daemon; e o bite-test PRECISA importar pra exercitar o
// `postBatch` REAL -- assert sobre helper puro exportado nao prova o contrato
// do pipeline (memory/proibicoes.md §5 2026-07-30).
// ──────────────────────────────────────────────────────────────────────
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (!MCP_TOKEN) {
    console.error('[erro] MCP_TOKEN ausente. Defina via env ou em .claude/settings.local.json');
    process.exit(1);
  }
  console.log('oimpresso-cc-watcher v0.2 (com redacao de segredo no ingest)');
  console.log('   MCP_URL: ' + MCP_URL);
  console.log('   PROJECT_GLOB: ' + PROJECT_GLOB);
  console.log('   MODE: ' + MODE);
  console.log('   STATE: ' + STATE_FILE);
  console.log('   PID: ' + process.pid);
  console.log('');

  // Daemon de vigia não pode morrer calado: em `watch` ele é a ÚNICA fonte do
  // heartbeat, e sumir sem dizer nada é o modo de falha que deixou o pipe cego
  // (processo vivo mas inerte é primo disto — morto e silencioso é pior).
  // Loga e SEGUE; quem decide derrubar é o operador, não um erro de 1 arquivo.
  process.on('unhandledRejection', (e) => {
    console.error(`\n[unhandledRejection] ${e?.message ?? e}`);
    if (e?.stack) console.error(e.stack);
  });
  process.on('uncaughtException', (e) => {
    console.error(`\n[uncaughtException] ${e?.message ?? e}`);
    if (e?.stack) console.error(e.stack);
  });
  for (const sinal of ['SIGINT', 'SIGTERM']) {
    process.on(sinal, () => {
      console.log(`\n[${sinal}] encerrando por pedido do operador (PID ${process.pid})`);
      process.exit(0);
    });
  }

  try {
    if (MODE === 'watch') await watch();
    else await ingestOnce();
  } catch (e) {
    console.error('\n[fatal] ' + e.message);
    console.error(e.stack);
    process.exit(1);
  }
}
