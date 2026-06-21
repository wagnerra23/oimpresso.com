---
module: TeamMcp
version: "1.0"
last_updated: "2026-06-13"
owner: wagner
na_justified:
  D1.a: "Tabela `mcp_actors` é cross-tenant POR DESIGN (sem `business_id`) — Identity Mesh governa time INTERNO oimpresso (Wagner/Felipe/Maiara/Eliana/Luiz), não clientes externos. Demais tabelas TeamMcp (`mcp_tokens`, `mcp_scopes`, `mcp_audit_log`) herdam `business_id` via `user_id` FK ou são repo-wide governance ([ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md) MCP server canon + [ADR 0081](../../decisions/0081-identity-mesh-mcp-actors.md) Identity Mesh + Constituição v2 Art. 6)."
  D5: "TeamMcp é módulo INTERNO de gestão de tokens MCP do time (Wagner/Felipe/Maiara/Luiz/Eliana). Cliente biz=4 ROTA LIVRE não usa por design — é ferramenta da equipe, não produto pra cliente final. ADR 0081 documenta como módulo team-internal."
  D8.b: "TeamMcp já nasceu Inertia/React (sem Blade legacy). CSRF é aplicado via middleware web padrão Laravel — não há views Blade pra auditar paridade."
related_adrs: [0053-mcp-server-governanca-como-produto, 0081-identity-mesh-mcp-actors, 0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0105-cliente-como-sinal-guiar-sem-mandar, 0153-module-grade-rubrica-v1, 0154-module-grade-v2-na-justificado, 0155-module-grade-v3-sub-dimensoes-gate-ci]
---

# SPEC — Modules/TeamMcp

> Módulo: **TeamMcp** — painel administrativo do MCP server canônico (`mcp.oimpresso.com`)
> Owner: Wagner [W] · Trust required: L1 (Wagner) · Permission prefix: `teammcp.*`
> Charter ADR: [0080](../../decisions/0080-trust-tiers-operacional-audit-findings.md) · Identity Mesh: [0081](../../decisions/0081-identity-mesh-mcp-actors.md)
> Status (2026-05-16): em produção — 5 actors humanos seedados, ~352 docs sync git→DB, Kanban Jira-style live

## Missão

Self-host equivalente ao Anthropic Team plan adaptado pra LGPD + custo + custom ([ADR 0059](../../decisions/0059-governanca-memoria-estilo-anthropic-team.md)). Concentra governança: tokens MCP do time, Identity Mesh (mcp_actors), audit log append-only, webhooks git→DB, ingest Claude Code sessions, tools registry, Kanban tasks/cycles.

## Tabelas owned (DB)

- `mcp_actors` — Identity Mesh ([ADR 0081](../../decisions/0081-identity-mesh-mcp-actors.md)) — cross-tenant por design (sem `business_id`)
- `mcp_tokens` — bind a `actor_id` + `user_id` (herda `business_id` do user)
- `mcp_scopes`, `mcp_user_scopes`, `mcp_user_module_access` — RBAC granular per tool/módulo
- `mcp_audit_log` — append-only com trigger MySQL imutabilidade
- `mcp_cc_sessions`, `mcp_cc_messages`, `mcp_cc_blobs` — Claude Code sessions ingest (MEM-CC)
- `mcp_tasks`, `mcp_epics`, `mcp_cycles`, `mcp_jira_projects` — Jira-style ([ADR 0070](../../decisions/0070-jira-style-task-management-current-md-removed.md))
- `mcp_inbox_notifications`, `mcp_components`, `mcp_views`, `mcp_quotas`, `mcp_workflows`

## User Stories (US-TEAM-NNN)

### US-TEAM-001 — Actor onboarding (cadastro humano novo no time)
**Como** Wagner (L0/superadmin)
**Quero** cadastrar Felipe/Maiara/Luiz/Eliana em `mcp_actors` com manifest declarado (trust_level + modules_write/read/blocked + skills_required + actions_blocked + audit_required + parent_actor=wagner)
**Pra** rastrear cada ação no MCP server à pessoa física certa, com cadeia de delegação clara (Constituição Art. 6 Identity Mesh).
**Aceite:**
- Seeder `McpActorsSeeder` idempotente (2x = 5 rows)
- Tier hierarchy: Wagner=L0, Felipe/Maira=L2, Luiz/Eliana=L3
- Felipe write em `legacy-delphi/*`, `Officeimpresso`, `OficinaAuto`, `ComunicacaoVisual`
- Eliana (advogada+financeiro) write em `NfeBrasil`, `Financeiro`, `RecurringBilling`
- Maiara blocked em `NfeBrasil` + `RecurringBilling` (fiscal só L3 Eliana ou Wagner)
- `parent_actor_id` de não-Wagner aponta pra `wagner.id`
- Cobertura: `McpActorsSeederTest.php` (7 invariantes), `ActorPermissionMatrixTest.php`

### US-TEAM-002 — Token MCP issue (gerar token pra dev/IA)
**Como** Wagner
**Quero** gerar token MCP via `POST /team-mcp/team/{user}/token` que bind a um `user_id` + `actor_id`
**Pra** dev/IA consumir tools MCP (cycles-active, my-work etc) com identidade rastreável.
**Aceite:**
- Token único cryptographically random (≥32 bytes)
- Persistido em `mcp_tokens` com `actor_id` linkado
- UI Wagner em `/team-mcp/team` (route `team-mcp.team.token.gerar`)
- Auditoria: INSERT em `mcp_audit_log` registra issue
- Cobertura: `SmokeRoutesTest.php` (smoke auth gate)

### US-TEAM-003 — Token MCP revoke (revogar token comprometido)
**Como** Wagner
**Quero** revogar token via `DELETE /team-mcp/team/token/{token}`
**Pra** invalidar imediatamente acesso (dev desligado, IA descontinuada, credencial vazada).
**Aceite:**
- `mcp_tokens.revoked_at` set imediato
- Próxima call MCP com esse token → 401 (`ActorResolver::byId` retorna null pra revoked)
- Auditoria: INSERT em `mcp_audit_log` registra revoke + razão
- Cobertura: `MultiTenantTokenIsolationTest.php` (testa revoked_at NÃO resolve)

### US-TEAM-004 — Isolamento token A vs token B
**Como** sistema MCP
**Quero** garantir que token gerado pra dev A nunca resolve actor B
**Pra** vazamento de token NÃO virar elevação de privilégio (Felipe token NÃO pode ler dados Eliana NfeBrasil).
**Aceite:**
- `ActorResolver::byId(A)` retorna actor A; `byId(B)` retorna actor B; nunca cruza
- Capabilities (`modules_write`, `modules_blocked`) NÃO vazam entre actors
- `slug` unique constraint impede duplicação
- Cobertura: `MultiTenantTokenIsolationTest.php` (8 cenários)

### US-TEAM-005 — Permission per tool/módulo (gates execução)
**Como** Wagner
**Quero** que cada tool MCP cheque `canWriteModule()` + `isActionBlocked()` antes de executar
**Pra** Felipe NÃO conseguir invocar tool que escreve em NfeBrasil mesmo com token válido.
**Aceite:**
- `McpActor::canWriteModule($module)` respeita `modules_write` + `modules_blocked` + wildcard `*`
- `McpActor::isActionBlocked($action)` consulta `actions_blocked`
- ActionGate middleware (Fase 5 — [ADR 0086](../../decisions/0086-fase-5-mvp-governance-actiongate-warn.md)) enforce em runtime
- Cobertura: `ActorPermissionMatrixTest.php` (matriz 5 humanos × módulos críticos)

### US-TEAM-006 — IA pareada → audit trail no humano parent
**Como** Wagner
**Quero** que IA actor (ex: `claude-code-wagner-laptop`) tenha `parent_actor_id` = humano L0/L1
**Pra** auditoria atribuir ações IA ao humano responsável (Felipe pareou Claude → Felipe responde).
**Aceite:**
- `McpActor::effectiveHumanSlug()` resolve IA→parent.slug (se parent não revogado)
- `effectiveHumanUserId()` retorna parent.user_id
- `effectiveDisplayName()` mostra nome do humano em logs
- Parent revogado → fallback pro próprio slug IA (graceful degradation)
- Cobertura: `ActorPermissionMatrixTest.php` (cenários 7-8)

### US-TEAM-007 — Audit log MCP append-only (Tier 0)
**Como** sistema MCP
**Quero** que toda ação em tool MCP registre em `mcp_audit_log` (actor_id, tool_name, payload_hash, timestamp, business_id_efetivo)
**Pra** rastreabilidade total LGPD + forensics em caso de incidente.
**Aceite:**
- Trigger MySQL bloqueia UPDATE/DELETE em `mcp_audit_log` (imutabilidade by-design)
- INSERT é o único DML permitido
- `audit_required=true` em actors L3 → audit_log entry per call
- UI dashboard Fase 5 vai pra `Modules/Governance` (NÃO neste módulo per SCOPE.md)
- Cobertura: SPEC-only por ora (US-TEAM-007 vira teste futuro com Pest factory)

## Não-escopo (NÃO neste módulo)

- ❌ Chat IA conversacional → `Modules/Jana`
- ❌ Knowledge browsing (ADRs/sessions UI) → `Modules/KB`
- ❌ Skills governance + Brain A/B → `Modules/ADS`
- ❌ Policies executáveis runtime → `Modules/Governance` (Fase 5)
- ❌ System Rules Spec → `Modules/SRS` (futuro SRS)

## Métricas de saúde

- Token issue/revoke trackeado em `mcp_audit_log`
- `php artisan jana:health-check` → check `pii_leak_in_assistant_responses` (cross-module)
- Drift detector: PR sem ADR mãe que toca `mcp_actors` falha CI

## Referências canônicas

- [ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md) — MCP server como produto
- [ADR 0070](../../decisions/0070-jira-style-task-management-current-md-removed.md) — Jira-style tasks
- [ADR 0079](../../decisions/0079-constituicao-oimpresso-7-camadas-governanca.md) — Constituição 7 camadas
- [ADR 0080](../../decisions/0080-trust-tiers-operacional-audit-findings.md) — Trust tiers operacional
- [ADR 0081](../../decisions/0081-identity-mesh-mcp-actors.md) — Identity Mesh (mãe)
- [ADR 0086](../../decisions/0086-fase-5-mvp-governance-actiongate-warn.md) — ActionGate Fase 5
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2

---
**v1.0.0** (2026-05-16) — SPEC inicial 7 US (TEAM-001..007) + cobertura testes Pest (5 feature tests).
