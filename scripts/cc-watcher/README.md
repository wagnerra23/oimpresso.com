# oimpresso-cc-watcher

Watcher Node que ingere `~/.claude/projects/<projeto>/*.jsonl` pro MCP server (`mcp.oimpresso.com/api/cc/ingest`).

Implementa **MEM-CC-UI-1 US-COPI-CC-040/041** (Cycle 02 antecipado).

## Setup (1× por dev)

```bash
cd scripts/cc-watcher
npm install
```

Auto-detecta token do `.claude/settings.local.json` do projeto. Não precisa env var.

## Uso

### Backfill 1× (recomendado primeira vez)

```bash
npm run start
# ou
node index.js
```

Processa **todas** sessões de `~/.claude/projects/D--oimpresso-com*/`. Idempotente — re-rodar é seguro (skip por mtime, dedup por msg_uuid).

### Daemon (modo contínuo)

```bash
npm run watch
# ou
node index.js --watch
```

Monitora mudanças via `chokidar`. Ingere incremental conforme você usa o Claude Code. Ctrl+C pra sair.

### Cron diário (alternativa ao daemon)

Windows Task Scheduler / Linux cron pra rodar `node index.js` 1×/dia 23:00 BRT.

## Config (env opcional)

| Var | Default | Descrição |
|---|---|---|
| `MCP_URL` | `https://mcp.oimpresso.com/api/cc/ingest` | Endpoint backend |
| `MCP_TOKEN` | auto-detect de `.claude/settings.local.json` | Bearer token |
| `PROJECT_GLOB` | `D--oimpresso-com` | Filtra subfolders de `~/.claude/projects/` |
| `STATE_FILE` | `~/.claude/.cc-watcher-state.json` | Offset por arquivo (mtime+lineCount) |

## O que ingere

| Tipo JSONL | Backend `msg_type` | Notas |
|---|---|---|
| `user` | `user` | conteúdo string ou array |
| `assistant` (com text) | `assistant` | extrai texto do content array |
| `assistant` (com tool_use) | `tool_use` | extrai tool_name + input truncado 1000 chars |
| `tool_result` | `tool_result` | conteúdo concatenado |
| `hook` | `hook` | passa direto |
| `system` | `system` | passa direto |

## O que **NÃO** ingere

- `queue-operation` (ruído)
- `attachment` (deferred_tools_delta — só lista de tools)
- Mensagens vazias (<2 chars de conteúdo)

## Dedup + idempotência

- **`msg_uuid`** UNIQUE no servidor (`mcp_cc_messages.msg_uuid`) — 2ª execução não duplica
- **Conteúdo >4KB** vai pra `mcp_cc_blobs` SHA256-deduplicado
- **State local** (`~/.claude/.cc-watcher-state.json`) skipa arquivos sem mudança de mtime

Re-rodar é seguro a qualquer momento.

## Verificar dados

Após rodar, abre `https://oimpresso.com/copiloto/admin/cc-sessions` — deve listar suas sessões.

## Troubleshooting

### "MCP_TOKEN ausente"

Crie `.claude/settings.local.json` com:
```json
{
  "mcpServers": {
    "oimpresso": {
      "url": "https://mcp.oimpresso.com/api/mcp",
      "headers": { "Authorization": "Bearer mcp_..." }
    }
  }
}
```

Token gerado em `https://oimpresso.com/copiloto/admin/team`.

### "HTTP 401 Unauthorized"

Token revogado ou inválido. Gera novo em `/copiloto/admin/team`.

### "HTTP 403 Forbidden"

User não tem permission `copiloto.cc.ingest.self` ou `copiloto.mcp.use`. Wagner atribui via tinker.

### "HTTP 429 Quota Exceeded"

Bateu cota MCP. Espera reset (00:00 BRT) ou Wagner aumenta em `/admin/team`.

### Falha de rede / 5xx

Re-rode — idempotência cobre.

## Refs

- [SPEC MEM-CC-UI-1](../../memory/requisitos/Copiloto/SPEC-cc-sessions.md)
- [ADR 0053 — MCP server governança](../../memory/decisions/0053-mcp-server-governanca-como-produto.md)
- [ADR 0059 — Governança Anthropic Team](../../memory/decisions/0059-governanca-memoria-estilo-anthropic-team.md)
- Endpoint backend: `Modules/Copiloto/Http/Controllers/Mcp/CcIngestController.php`
