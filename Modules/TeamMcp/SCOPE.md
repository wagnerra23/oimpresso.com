---
module: TeamMcp
purpose: "Painel administrativo do MCP server canônico (mcp.oimpresso.com): tokens, scopes, audit, webhooks, ingest, tools registry, identity (mcp_actors), e tasks/cycles Jira-style do time."
contains:
  - "TeamController — gerencia time + tokens MCP"
  - "CcSessionsController — Claude Code sessions ingest"
  - "TasksAdminController — admin Jira-style (mcp_tasks, mcp_cycles, mcp_jira_projects)"
  - "DataController + InstallController (boilerplate)"
  # A migrar de Modules/Copiloto Fase 3.7:
  - "Mcp/CcIngestController — ingest Claude Code sessions"
  - "Mcp/HealthController — health check do MCP server"
  - "Mcp/SyncMemoryWebhookController — webhook git → mcp_memory_documents"
  # A migrar de Modules/ADS Fase 3.7:
  - "ToolsController — MCP tools registry UI"
  - "TeamScopesController — RBAC scopes per actor"
  # Fase 4 (NOVA, ADR 0081):
  - "ActorsController (NOVO) — Identity Mesh: CRUD de mcp_actors com manifest YAML"
not_contains:
  - "Chat IA conversacional → Modules/Copiloto"
  - "Knowledge browsing → Modules/KB"
  - "Skills governance → Modules/ADS"
  - "Decision flow → Modules/ADS"
  - "System Rules Spec → Modules/MemCofre (futuro SRS)"
  - "Policies executáveis runtime → Modules/Governance (Fase 5)"
trust_required: L1
owner: wagner
permission_prefix: teammcp.*
charter_adr: 0080
related_adrs:
  - 0053-mcp-server-governanca-como-produto
  - 0070-jira-style-task-management-current-md-removed
  - 0072-maturacao-memoria-team-mcp-openclaw-soa-2026
  - 0077-mcp-resolver-owner-via-mcp-handle (a absorver em 0081)
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
  - 0081-identity-mesh-mcp-actors (a criar — Fase 4)
url_prefixes:
  - /teammcp/*
db_tables_owned:
  - mcp_tokens
  - mcp_scopes
  - mcp_user_scopes
  - mcp_user_module_access
  - mcp_actors (NOVA — Fase 4 ADR 0081)
  - mcp_audit_log (mantém append-only com trigger; UI Fase 5 fica em Modules/Governance)
  - mcp_cc_sessions / mcp_cc_messages / mcp_cc_blobs
  - mcp_tasks / mcp_epics / mcp_cycles / mcp_jira_projects (Jira-style)
  - mcp_inbox_notifications
  - mcp_components / mcp_views
  - mcp_quotas
  - mcp_workflows
drift_alerts:
  - controller: "(esperando migração)"
    pertence_a: "Modules/Copiloto/Http/Controllers/Mcp/CcIngestController.php"
    motivo: "Ingest de Claude Code sessions é admin do MCP server"
    eta_migracao: "Fase 3.7"
  - controller: "(esperando migração)"
    pertence_a: "Modules/Copiloto/Http/Controllers/Mcp/HealthController.php"
    motivo: "Health check do MCP server pertence ao admin do MCP"
    eta_migracao: "Fase 3.7"
  - controller: "(esperando migração)"
    pertence_a: "Modules/Copiloto/Http/Controllers/Mcp/SyncMemoryWebhookController.php"
    motivo: "Webhook sync git→DB é função do MCP server admin"
    eta_migracao: "Fase 3.7"
  - controller: "(esperando migração)"
    pertence_a: "Modules/ADS/Http/Controllers/Admin/ToolsController.php"
    motivo: "MCP tools registry pertence ao TeamMcp"
    eta_migracao: "Fase 3.7"
  - controller: "(esperando migração)"
    pertence_a: "Modules/ADS/Http/Controllers/Admin/TeamScopesController.php"
    motivo: "RBAC scopes do MCP server pertence ao TeamMcp"
    eta_migracao: "Fase 3.7"
---

# Modules/TeamMcp — Admin do MCP server

## Missão

Painel administrativo do MCP server canônico (`mcp.oimpresso.com`). Self-host equivalente ao Anthropic Team plan adaptado pra LGPD + custo + custom (ADR 0059).

Inclui:
- **Tokens MCP** — gerar/revogar/listar (Wagner gerencia em `/copiloto/admin/team` — vai virar `/teammcp/`)
- **Scopes & permissões** — granularidade fina por tool/módulo (mcp_scopes, mcp_user_scopes)
- **Audit** — `mcp_audit_log` é tabela aqui (UI dashboard Fase 5 vai pra Governance)
- **Webhooks & ingest** — sync git→DB (memory, sessions, ADRs); ingest de Claude Code sessions
- **Tools registry** — MCP tools internos (cycles-active, my-work, etc.)
- **Identity Mesh** — `mcp_actors` table (Fase 4 ADR 0081)
- **Jira-style** — `mcp_tasks`, `mcp_cycles`, `mcp_jira_projects` admin UI

## Quando este módulo é tocado

| Trigger | Quem | Ação |
|---|---|---|
| Wagner gera token MCP | L1 | INSERT em `mcp_tokens` (com actor_slug Fase 4) |
| Wagner audita ações da IA | L1 | filtra `mcp_audit_log` |
| Maíra/Felipe/Eliana onboarding | dev | gera token bind a actor |
| Push em `memory/*` | webhook | sync `mcp_memory_documents` |
| IA externa pede conexão | L1 (Wagner aprova) | cria actor + token bind |

## Quando NÃO é tocado

- ❌ Chat IA → Modules/Copiloto
- ❌ Skills → Modules/ADS
- ❌ Browse de ADRs/sessions → Modules/KB
- ❌ Policies runtime → Modules/Governance (Fase 5)

## Drift atual

5 controllers a migrar (3 de Copiloto, 2 de ADS). Ver `drift_alerts[]`.

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. 5 controllers pendentes de migração + ActorsController planejado pra Fase 4.
