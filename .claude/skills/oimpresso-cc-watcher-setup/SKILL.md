---
name: oimpresso-cc-watcher-setup
description: Configura o watcher local do Claude Code que sincroniza ~/.claude/projects/*.jsonl com o MCP server do oimpresso (cc-search cross-dev). Ativa quando user pede "ativar watcher", "sincronizar minhas sessões", "compartilhar Claude Code com o time", "backfill MEM-CC-1", "setup ingestão Claude Code". Cria o script Node local (gitignored), instala deps, faz backfill 1×, configura como serviço em background. Cada dev roda 1× e fica ativo permanente.
allowed-tools: Read, Write, Edit, Bash, Glob, Grep, WebFetch, mcp__oimpresso__*
---

# Skill — Setup CC Watcher (MEM-CC-1)

> **Objetivo:** sincronizar `~/.claude/projects/*.jsonl` (sessões Claude Code locais do dev) → `https://mcp.oimpresso.com/api/cc/ingest` → DB → tool MCP `cc-search` (busca cross-dev).
>
> **Resultado:** Felipe pergunta *"como Wagner resolveu telescope crash 504?"* e o Claude Code dele acha a session original via `cc-search` (em vez de re-explorar).
>
> **Esforço pro dev:** ~5 minutos pilotado pela skill.

---

## Pré-requisitos

A skill checa antes de começar:

```bash
# 1. Token MCP configurado?
test -f .claude/settings.local.json && grep -c "Bearer mcp_" .claude/settings.local.json
# (esperado: 1 — se não, ative skill `oimpresso-team-onboarding` primeiro)

# 2. Node.js disponível?
node --version  # esperado: ≥18

# 3. Permission `copiloto.cc.ingest.self`?
# (default: vem com `copiloto.mcp.use` que todos com token têm)

# 4. Schema MCP do CT 100 está ativo?
curl -fsS https://mcp.oimpresso.com/api/cc/health 2>/dev/null
# (se não responder: avisa Wagner — Sprint B não foi deployado em prod ainda)
```

Se algum falhar, reportar + sair.

---

## 1. Determina paths e OS

```bash
# OS detection
case "$(uname -s 2>/dev/null || echo Windows)" in
  Linux*)  OS=linux ;;
  Darwin*) OS=mac ;;
  MINGW*|MSYS*|CYGWIN*|Windows*) OS=windows ;;
esac

# Path Claude projects (cross-OS)
if [ "$OS" = "windows" ]; then
  CC_DIR="$USERPROFILE/.claude/projects"
else
  CC_DIR="$HOME/.claude/projects"
fi

ls -la "$CC_DIR"  # confirma existe e tem JSONLs
```

---

## 2. Cria pasta do watcher (NUNCA commit)

```bash
mkdir -p .cc-watcher  # local gitignored
echo "/.cc-watcher/" >> .gitignore  # se ainda não tiver
```

---

## 3. Cria `package.json` mínimo

Use Write tool pra criar `.cc-watcher/package.json`:

```json
{
  "name": "oimpresso-cc-watcher",
  "version": "1.0.0",
  "description": "Local watcher: ~/.claude/projects/*.jsonl → mcp.oimpresso.com",
  "private": true,
  "type": "module",
  "scripts": {
    "start": "node index.js",
    "backfill": "node index.js --backfill-only",
    "once": "node index.js --once"
  },
  "dependencies": {
    "chokidar": "^3.6.0",
    "node-fetch": "^3.3.2"
  }
}
```

---

## 4. Cria `index.js` do watcher

Use Write tool pra criar `.cc-watcher/index.js` com este código:

```javascript
#!/usr/bin/env node
// MEM-CC-1 watcher local. Sincroniza ~/.claude/projects/*.jsonl com MCP server.
// Idempotente: msg_uuid UNIQUE no DB evita duplicação.
//
// Uso:
//   node index.js              → tail contínuo (daemon)
//   node index.js --backfill-only  → ingere TUDO 1× e termina
//   node index.js --once       → uma rodada e termina

import { readdir, readFile, stat, writeFile, mkdir } from 'fs/promises';
import { existsSync } from 'fs';
import { homedir } from 'os';
import { join, basename } from 'path';
import chokidar from 'chokidar';
import fetch from 'node-fetch';

const SETTINGS = process.env.CC_SETTINGS || '../.claude/settings.local.json';
const CHECKPOINT_FILE = '.cc-watcher-state.json';
const BATCH_SIZE = 50;
const FLUSH_DEBOUNCE_MS = 5000;

// ---- Config ----
const cwd = process.cwd();
const settingsPath = join(cwd, SETTINGS);
const settings = JSON.parse(await readFile(settingsPath, 'utf8'));
const auth = settings.mcpServers?.oimpresso?.headers?.Authorization || '';
const token = auth.replace(/^Bearer\s+/, '');
if (!token.startsWith('mcp_')) {
  console.error('❌ Token MCP não encontrado em', settingsPath);
  process.exit(1);
}

const MCP_INGEST_URL = (settings.mcpServers?.oimpresso?.url || 'https://mcp.oimpresso.com/api/mcp')
  .replace(/\/api\/mcp\/?$/, '/api/cc/ingest');

const CC_PROJECTS = join(homedir(), '.claude', 'projects');
if (!existsSync(CC_PROJECTS)) {
  console.error('❌ ~/.claude/projects não existe. Você usa Claude Code?');
  process.exit(1);
}

// ---- Checkpoint (offset por sessionId) ----
let checkpoint = {};
try {
  checkpoint = JSON.parse(await readFile(CHECKPOINT_FILE, 'utf8'));
} catch { /* não existe ainda — ok */ }

async function saveCheckpoint() {
  await writeFile(CHECKPOINT_FILE, JSON.stringify(checkpoint, null, 2));
}

// ---- POST batch pro MCP server ----
async function postBatch(session, messages) {
  if (!messages.length) return { inserted: 0, duplicated: 0 };

  const resp = await fetch(MCP_INGEST_URL, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Authorization': `Bearer ${token}`,
      'X-Requested-With': 'cc-watcher',
    },
    body: JSON.stringify({ session, messages }),
  });

  if (!resp.ok) {
    console.error(`⚠️ POST falhou ${resp.status}: ${await resp.text()}`);
    return null;
  }
  return resp.json();
}

// ---- Filtra ruído + transforma JSONL line → payload ingest ----
function lineParaMsg(raw) {
  let obj;
  try { obj = JSON.parse(raw); } catch { return null; }

  // Skip ruído (queue-operation, hooks vazios, sumário-meta)
  if (obj.type === 'queue-operation') return null;
  if (obj.type === 'summary' && !obj.message) return null;
  if (!obj.uuid && !obj.message?.id) return null;

  // Tenta extrair conteúdo plano
  let contentText = '';
  if (typeof obj.message?.content === 'string') {
    contentText = obj.message.content;
  } else if (Array.isArray(obj.message?.content)) {
    contentText = obj.message.content
      .map(c => c.text || c.content || JSON.stringify(c))
      .join('\n');
  } else if (obj.attachment?.stdout) {
    contentText = obj.attachment.stdout;
  }

  // Skip msgs muito pequenas (signal-to-noise)
  if (contentText.length < 30 && !obj.toolUseResult) return null;

  return {
    uuid: obj.uuid || obj.message?.id,
    parent_uuid: obj.parentUuid || null,
    type: obj.type === 'tool_result' || obj.toolUseResult ? 'tool_result'
        : obj.message?.role === 'assistant' ? 'assistant'
        : obj.message?.role === 'user' ? 'user'
        : obj.type === 'attachment' ? 'attachment'
        : obj.type || 'system',
    role: obj.message?.role || null,
    tool_name: obj.message?.content?.[0]?.name || obj.toolUseResult?.name || null,
    content_text: contentText.slice(0, 4000),
    content_json: obj,
    tokens_in: obj.message?.usage?.input_tokens || null,
    tokens_out: obj.message?.usage?.output_tokens || null,
    cache_read: obj.message?.usage?.cache_read_input_tokens || null,
    cache_write: obj.message?.usage?.cache_creation_input_tokens || null,
    ts: obj.timestamp || new Date().toISOString(),
  };
}

// ---- Processa 1 arquivo JSONL ----
async function processFile(filepath) {
  const fileName = basename(filepath);
  const sessionId = fileName.replace(/\.jsonl$/, '');
  const projectPath = filepath.split('/projects/')[1]?.split('/')[0] || '';

  const checkpointKey = filepath;
  const lastOffset = checkpoint[checkpointKey] || 0;

  const stats = await stat(filepath);
  if (stats.size <= lastOffset) return; // sem mudanças

  const data = await readFile(filepath, 'utf8');
  const newPart = data.slice(lastOffset);
  const lines = newPart.split('\n').filter(l => l.trim());

  if (!lines.length) return;

  // Extrai metadata da session do 1º line
  let firstLine;
  try { firstLine = JSON.parse(lines[0]); } catch {}
  const sessionMeta = {
    uuid: sessionId,
    project_path: projectPath.replace(/^-/, '').replace(/-/g, '/'),
    git_branch: firstLine?.gitBranch || null,
    cc_version: firstLine?.version || null,
    entrypoint: firstLine?.entrypoint || 'claude-code',
    started_at: firstLine?.timestamp || new Date().toISOString(),
  };

  // Batch as msgs
  const messages = lines.map(lineParaMsg).filter(Boolean);
  if (!messages.length) {
    checkpoint[checkpointKey] = stats.size;
    await saveCheckpoint();
    return;
  }

  // Quebra em batches de BATCH_SIZE
  let inserted = 0, duplicated = 0;
  for (let i = 0; i < messages.length; i += BATCH_SIZE) {
    const chunk = messages.slice(i, i + BATCH_SIZE);
    const result = await postBatch(sessionMeta, chunk);
    if (result) {
      inserted += result.messages_inserted || 0;
      duplicated += result.messages_duplicated || 0;
    }
  }

  checkpoint[checkpointKey] = stats.size;
  await saveCheckpoint();

  if (inserted > 0) {
    console.log(`✓ ${fileName.slice(0, 16)}... +${inserted} (dup ${duplicated}) total ${messages.length}`);
  }
}

// ---- Main ----
async function backfillAll() {
  console.log(`📥 Backfill: ${CC_PROJECTS}`);
  let total = 0;
  async function walk(dir) {
    const entries = await readdir(dir, { withFileTypes: true });
    for (const e of entries) {
      const path = join(dir, e.name);
      if (e.isDirectory()) await walk(path);
      else if (e.name.endsWith('.jsonl')) {
        await processFile(path);
        total++;
      }
    }
  }
  await walk(CC_PROJECTS);
  console.log(`✓ Backfill OK — ${total} arquivos processados`);
}

const args = process.argv.slice(2);
const backfillOnly = args.includes('--backfill-only');
const once = args.includes('--once');

if (backfillOnly) {
  await backfillAll();
  process.exit(0);
}

await backfillAll();

if (once) process.exit(0);

// Watch contínuo
console.log(`👀 Watching ${CC_PROJECTS}...`);
const watcher = chokidar.watch(`${CC_PROJECTS}/**/*.jsonl`, {
  persistent: true,
  awaitWriteFinish: { stabilityThreshold: 2000, pollInterval: 500 },
});
watcher.on('change', processFile);
watcher.on('add', processFile);
process.on('SIGINT', async () => {
  await saveCheckpoint();
  process.exit(0);
});
```

---

## 5. Instala e roda backfill

```bash
cd .cc-watcher
npm install --silent
npm run backfill 2>&1 | tail -20
```

Esperado: ~83 sessões processadas, 50k+ messages enviadas em ~3-5 min (depende da conexão).

---

## 6. Configura como serviço background (escolhe 1)

### Linux/Mac — systemd / launchd
```bash
# Linux:
cat > ~/.config/systemd/user/oimpresso-cc-watcher.service <<EOF
[Unit]
Description=Oimpresso CC Watcher
[Service]
WorkingDirectory=$HOME/oimpresso.com/.cc-watcher
ExecStart=/usr/bin/node index.js
Restart=on-failure
[Install]
WantedBy=default.target
EOF
systemctl --user enable --now oimpresso-cc-watcher
systemctl --user status oimpresso-cc-watcher
```

```bash
# Mac:
mkdir -p ~/Library/LaunchAgents
cat > ~/Library/LaunchAgents/com.oimpresso.cc-watcher.plist <<EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict>
  <key>Label</key><string>com.oimpresso.cc-watcher</string>
  <key>WorkingDirectory</key><string>$HOME/oimpresso.com/.cc-watcher</string>
  <key>ProgramArguments</key>
  <array>
    <string>/usr/local/bin/node</string>
    <string>index.js</string>
  </array>
  <key>RunAtLoad</key><true/>
  <key>KeepAlive</key><true/>
</dict></plist>
EOF
launchctl load ~/Library/LaunchAgents/com.oimpresso.cc-watcher.plist
```

### Windows — Task Scheduler (PowerShell admin)
```powershell
$Action = New-ScheduledTaskAction -Execute "node.exe" -Argument ".cc-watcher\index.js" -WorkingDirectory "$HOME\oimpresso.com"
$Trigger = New-ScheduledTaskTrigger -AtLogon
$Principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME
Register-ScheduledTask -TaskName "OimpressoCcWatcher" -Action $Action -Trigger $Trigger -Principal $Principal
Start-ScheduledTask -TaskName "OimpressoCcWatcher"
```

### Alternativa simples (qualquer OS) — npm run start em background
```bash
cd .cc-watcher
nohup npm start > watcher.log 2>&1 &
echo $! > watcher.pid
```

---

## 7. Verifica funcionamento

```bash
# 1. Audit log do MCP server tem ingest do user?
mcp call cc-search --query="*" --days_ago=1 --limit=5

# 2. Próprio status (claude-code-usage-self mostra cross-dev calls)
mcp call claude-code-usage-self
```

Se ver hits → **SUCESSO**. Mensagens já vão pro time inteiro.

---

## 8. Troubleshooting watcher

| Sintoma | Diagnose | Fix |
|---|---|---|
| `npm install` falha | Node muito antigo | `nvm install 20` |
| `chokidar` não detecta | Volume tipo network/Dropbox | use `usePolling: true` no chokidar.watch |
| `POST 401` repetido | token expirou | `oimpresso-team-onboarding` skill |
| `POST 429 quota_exceeded` | atingiu cap diário/mensal | espera reset ou Wagner aumenta |
| Backfill demorando >10min | conexão lenta | OK, deixa rodar — checkpoint preserva progresso |
| Watcher para de logar mas tá vivo | sem new sessions ou tudo cached | normal — só loga inserts novos |

---

## 9. Privacidade

⚠️ **Tudo que você fizer no Claude Code vai pro servidor.** Inclusive:
- Senhas que você cole acidentalmente em prompts
- Tool results com dados sensíveis (Bash output)
- Conteúdo de arquivos lidos (Read tool)

**Mitigação:**
- Watcher **filtra** queue-operations, hooks vazios, msgs <30 chars
- Wagner pode adicionar PII redactor no servidor (cycle 02)
- Você pode pausar via `systemctl --user stop oimpresso-cc-watcher` (Linux) ou `Stop-ScheduledTask` (Win)
- LGPD esquecer-me no roadmap (cycle 02)

---

## 10. Confirmação final

Antes de finalizar, valide:

- [ ] `.cc-watcher/index.js` existe e tem token configurado
- [ ] `.gitignore` cobre `.cc-watcher/`
- [ ] `npm run backfill` retornou OK
- [ ] Watcher rodando (systemctl/launchctl/Task Scheduler)
- [ ] `mcp call cc-search --query="*" --days_ago=1` retorna hits

Reporta:
```
✅ MEM-CC-1 watcher ativo. Suas sessões Claude Code agora vão automático
pro MCP server. Time pode buscar via cc-search.

Próximo: trabalhe normalmente. O watcher tail no fundo, batch a cada 5s.
```

---

## 11. Para Wagner — bulk setup do time

Se Wagner está pedindo "configure pra todo time", a skill pode:

1. Listar emails Felipe/Maíra/Luiz/Eliana
2. Pra cada um, gerar token via `php artisan copiloto:mcp:gerar-token --user-email=X`
3. Coletar tokens num arquivo seguro (Vaultwarden export)
4. Mandar email/Slack pra cada dev com link MEMORY_TEAM_ONBOARDING.md + token + instrução pra rodar essa skill local

**Quando Wagner pedir:** "configure todos os devs do time", oferece esse fluxo.
