---
id: requisitos-teammcp-superficie
---

# SUPERFÍCIE — Modules/TeamMcp ⚰️ REMOVIDO

> ⚠️ **Este documento é uma lápide.** O `Modules/TeamMcp` foi **apagado em 2026-07-31**
> (decisão [W]: *"MCP vai para forja"* + *"pode apagar"*). Não há mais superfície a derivar —
> o gerador `module-surface.mjs` não produz este arquivo, porque o módulo não existe.
>
> Mantido no lugar **de propósito**: 3 docs vivos linkam pra cá, e apagar criaria link morto
> em vez de registro. Quem chegou aqui procurando a superfície, ela está em
> [`memory/requisitos/Forja/SUPERFICIE.md`](../Forja/SUPERFICIE.md).

## Para onde foi cada coisa

O módulo saiu em **7 etapas**, todas com PR e CI verde. Nada foi perdido — a regra do [W] era
*"nada pode ser perdido"*, e o destino de cada peça está no
[DEPRECATION-PLAN](DEPRECATION-PLAN.md).

| Cluster | Foi para |
|---|---|
| `/api/mcp` (SyncMemoryWebhook · Health) | `Modules/Forja/Http/Controllers/Mcp/` |
| Identidade (McpActor · ActorResolver · McpActorRepository · McpTokenIssuer) | `Modules/Forja/{Entities,Services}/` |
| Loop de handoff (4 tools MCP · CoworkHandoff · Ingest/LeverService) | `Modules/Forja/{Mcp/Tools,Entities,Services}/` |
| Ingest CC (CcIngest* · McpIngestHeartbeat · IngestLivenessService) | `Modules/Forja/` |
| Admin do MCP (ToolsController · TeamScopesController) | `Modules/Forja/Http/Controllers/Admin/` |
| Hub Equipe (Team · TasksAdmin · CcSessions · Scorecard) | `Modules/Forja/Http/Controllers/` |
| Cockpit `/forja` (ForjaController · 4 services · PrChecksResolver) | `Modules/Forja/` |

**URLs preservadas:** `/api/mcp/*` · `/api/cc/ingest` · `/team-mcp/*` · `/forja/*` · `/ads/admin/*`
seguem idênticas — nenhum watcher, bookmark ou webhook precisou ser reconfigurado
([ADR 0087](../../decisions/0087-drift-resolution-sem-mover-url.md), *drift resolution sem mover URL*).

**Tabelas:** `mcp_tokens` · `mcp_actors` · `mcp_ingest_heartbeat` · `cowork_handoffs` **não foram
tocadas** — o CT 100 e o Hostinger leem o mesmo banco, então a migração foi troca de dono **no
código**, nunca DDL.
