---
page: /copiloto/admin/governanca
component: resources/js/Pages/Jana/Admin/Governanca/Index.tsx
owner: wagner
status: live
last_validated: "2026-05-16"
parent_module: Jana
parent_adr: memory/decisions/0053-mcp-server-governanca-como-produto.md
related_adrs: [39, 53, 61, 70, 93, 94, 95, 131]
related_charters:
  - resources/js/Pages/Jana/Admin/Roadmap.charter.md
related_specs:
  - memory/requisitos/Jana/SPEC.md (MEM-MCP-1.e)
tier: A
charter_version: 1
permissao: copiloto.mcp.usage.all
superadmin_only: true
---

# Page Charter — `/copiloto/admin/governanca`

> **Status:** `live` — implementada e em uso prod biz=1 (Wagner superadmin) desde 2026-04. Charter retroativo Wave M 2026-05-16.
>
> **Superadmin-only** — gestão do MCP server `mcp.oimpresso.com` (CT 100). Tokens, audit log, quotas, sync `memory/git → mcp_memory_documents`.

---

## Mission

Cockpit de **governança do MCP server** ([ADR 0053](../../../../../../memory/decisions/0053-mcp-server-governanca-como-produto.md)) — único ERP BR com MCP exposto como produto. Wagner monitora: quem usa, quanto custa, quais docs sincronizam, quais tokens estão ativos, quais skills foram publicadas.

Audiência primária: **Wagner (superadmin único)**. Eventualmente Felipe quando promovido. Larissa NÃO acessa (sem permissão `copiloto.mcp.usage.all`).

---

## Goals

- KPIs MCP: total docs sincronizados (352+), última sync git→DB, audit calls 24h, custo Brain B 24h, tokens ativos
- Tabela tokens `/copiloto/admin/team` integrada (link cross-tela)
- Sync manual `IndexarMemoryGitParaDb` via botão (Wagner triggera após push git canônico)
- Audit log `mcp_audit_log` paginated com filtro por tool name + user (Felipe/Maiara/Eliana)
- Quota enforcer status (`QuotaEnforcer`) — quem está rate-limited
- Sub-nav: Visão Geral / Tokens / Sync / Audit / Quotas

## Non-Goals

- ⛔ Edição direta de `mcp_memory_documents` (canon é git — esta tela só LÊ)
- ⛔ Geração de token sem expiração obrigatória (Tier 0 segurança)
- ⛔ Acesso cross-business em audit log sem flag `?escopo=plataforma` (LGPD)
- ⛔ Bypass `QuotaEnforcer` — superadmin marca quota override mas registra audit

## UX targets

- Padrão Chat Cockpit V2 ([ADR 0039](../../../../../../memory/decisions/0039-ui-chat-cockpit-padrao.md)) — shared components (`KpiGrid`, `KpiCard`, `StatusBadge`, `EmptyState`, `SubNav`, `PageHeader`)
- LocalStorage persiste preset filtros + seção ativa (`LS_PRESET_KEY`, `LS_SECAO_KEY`)
- Render < 300ms p95 com `Inertia::defer()` em audit log paginated
- Dark mode obrigatório
- Mobile-friendly mas otimizado desktop (uso real Wagner monitor)

## Anti-hooks

- ⛔ Exposição de token raw no UI (só prefix `mcp_xxx...` + last 4)
- ⛔ Sync manual sem `tasks-update` log — audit-trail obrigatório
- ⛔ Renderizar audit log com PII raw — `PiiRedactor` enforce
- ⛔ Permitir acesso a user sem `superadmin` flag — RBAC Spatie strict

## Skills relevantes

`brief-first` (Tier A) · `multi-tenant-patterns` (Tier A) · `jana-arch` (Tier B) · `mcp-first` (Tier A) · `publication-policy` (Tier A — Wagner aprova mudança Tier 0)

## Charter version log

- v1 (2026-05-16) — Charter retroativo Wave M boost Modules/Jana 64→78
