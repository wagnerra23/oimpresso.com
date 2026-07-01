---
name: MCP endpoints oimpresso — canônico vs fallback
description: Diferencia mcp.oimpresso.com (CT 100/FrankenPHP, canônico produção) de oimpresso.com/api/mcp (Hostinger, dev/CRUD apenas — não suporta fluxo HTTP/SSE persistente). ADR 0053
type: reference
---
## Os dois endpoints

| Endpoint | Host | Server | Papel | Status 2026-04-30 |
|---|---|---|---|---|
| **`mcp.oimpresso.com/api/mcp`** | CT 100 docker-host | FrankenPHP Caddy | **Canônico** — fluxo MCP em produção | vivo, `/health` 200 |
| `oimpresso.com/api/mcp` | Hostinger shared | nginx + PHP-FPM | Fallback dev / CRUD admin | vivo pós-pull `4feed564` |

**Por que dois?** Hostinger não roda daemons nem suporta SSE persistente / streaming MCP. CT 100 com FrankenPHP aguenta o fluxo. Decisão em ADR 0053.

**Mesmo código + mesmo MySQL.** CT 100 puxa via `git clone` + tunnel SSH ao Hostinger MySQL (`DB_HOST=tunnel:3306`). Sync via webhook `/api/mcp/sync-memory`.

## Como conectar Claude Code

1. **Gerar token** em `https://oimpresso.com/copiloto/admin/team` (login Wagner/superadmin → botão "gerar token" no usuário)
2. Token formato: `mcp_<base64>`
3. Configurar em `.mcp.json` (HTTP MCP):
   ```json
   {
     "mcpServers": {
       "oimpresso-mcp": {
         "type": "http",
         "url": "https://mcp.oimpresso.com/api/mcp",
         "headers": { "Authorization": "Bearer mcp_..." }
       }
     }
   }
   ```
4. Reiniciar Claude Code

## Endpoints públicos (sem token)

- `GET /api/mcp/health` → `{"status":"ok","service":"oimpresso-mcp","version":"0.1","spec_mcp":"2025-06-18"}`
- `POST /api/mcp/sync-memory` → webhook GitHub (auth via `X-MCP-Sync-Token`, não Bearer)

## Endpoints autenticados (Bearer mcp_*)

- `GET /api/mcp/health/auth` → echo do user/quota/scopes do token
- `POST /api/mcp` → JSON-RPC 2.0 (initialize / tools/list / tools/call)

Sem token: 401 + `WWW-Authenticate: Bearer realm="mcp", error="invalid_token"`

## Tools disponíveis no server (`Modules/Jana/Mcp/Tools/`)

- `CcSearchTool`
- `MemoriaSearchTool`
- `DecisionsSearchTool` / `DecisionsFetchTool`
- `SessionsRecentTool`
- `TasksCurrentTool`
- `ClaudeCodeUsageSelfTool`

## Receita re-deploy CT 100

Acesso só via console Proxmox web (porta 22 não exposta) — ver infra-proxmox-ct100.md.

```bash
cd /opt/oimpresso-mcp/code && git pull && cd ..
cd code/docker/oimpresso-mcp && docker compose build && docker compose up -d
docker compose logs -f tunnel  # confirmar "Local forwarding listening"
curl https://mcp.oimpresso.com/api/mcp/health
```

## Pegadinhas

- **Hostinger não substitui CT 100** mesmo com `/api/mcp/health` 200 — só CRUD admin funciona; conexão MCP persistente cai.
- **DNS Hostinger API** (não painel hPanel) — receita em hostinger.md.
- **Token MCP nunca em git/commit/PR.** Gestão via `/copiloto/admin/team` UI ou CLI `php artisan copiloto:mcp:system-token`.
- **Drift Hostinger:** se `git pull` abortar com "untracked files would be overwritten", `git stash --include-untracked -m 'pre-pull-DATA'` antes (usado em 2026-04-30).
