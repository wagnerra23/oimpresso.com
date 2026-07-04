---
page: /governance/audit
component: resources/js/Pages/governance/Audit.tsx
related_us: [US-GOV-003]
owner: wagner
status: live
last_validated: "2026-05-16"
parent_module: Governance
related_adrs: [79, 84, 86, 94, 147]
tier: A
charter_version: 1
---

# Page Charter — /governance/audit

> **Status:** live. Drill-down forense do `mcp_audit_log` (Constituição Art. 9). Append-only enforced via trigger MySQL ([ADR 0084](../../../../memory/decisions/0084-triggers-mysql-imutabilidade-mcp-audit-log.md)) — modificação de entry é incidente P0.

---

## Mission

Permitir a Wagner (operador sênior) investigar atividade do MCP server `mcp.oimpresso.com` em janela de tempo: quem chamou qual endpoint, com qual tool/resource, status (ok/error), duração. Visão forense pra debugging de incidentes + base pra futuro export LGPD Art. 18 por business_id.

---

## Goals — Features (faz)

- AppShellV2 + topnav + `<PageHeader>` shared
- KpiGrid cols=3 com `<KpiCard>` shared (total entries, errors, users distintos)
- 4 filtros combinados via `router.get` (period 1h/24h/7d/30d, actor via `mcp_actors.slug`, endpoint enum, status ok/error) com `preserveState` + `preserveScroll` + `replace`
- Tabela `mcp_audit_log` limit 200 entries por query (quando, user_id, endpoint, tool/resource, status badge semântico, duração ms)
- Badge status semântico (`bg-emerald-100` ok / `bg-red-100` error) consistente com Cockpit V2
- Empty state via `<EmptyState>` shared quando filtros não retornam nada
- Multi-tenant Tier 0: query scopada pelo `business_id` do usuário autenticado (default biz=1 — Wagner)

---

## Non-Goals — Features (NÃO faz)

- ❌ Edit/delete/UPDATE em audit entry (append-only IRREVOGÁVEL — trigger MySQL bloqueia, ADR 0084)
- ❌ Export CSV/PDF LGPD Art. 18 (fica pra próxima iteração)
- ❌ Real-time tail (sem WebSocket — refresh manual via filtros)
- ❌ Histórico além de 30d em filtros distinct (limit 50 endpoints — performance)

---

## UX Targets

- p95 first-paint < 800ms (KPIs + 200 entries + 2 distinct queries)
- 0 erros JS console
- Cores semânticas Cockpit V2: emerald=ok, rose/red=error, info=neutro
- Filtros aplicam via Inertia partial reload (sem full-page navigation)
- Limit hint visível no footer ("Limit 200 entries... refine filtros pra ver mais")

---

## UX Anti-patterns

- ❌ Edit inline em entry (audit é read-only — qualquer save aqui é violação Art. 9)
- ❌ Eager-load sem limit (200 hard cap obrigatório — `mcp_audit_log` cresce rápido)
- ❌ Filtro de período > 30d (custo MySQL alto sem índice composto)
- ❌ KpiCard inline (`<Card>` custom — canon = `<KpiCard>` shared)
- ❌ Cor crua `bg-red-100` sem semântica (canon = `statusColor()` helper)

---

## Tests anti-regressão

- [tests/Feature/Design/CockpitPatternConformanceTest.php](../../../../tests/Feature/Design/CockpitPatternConformanceTest.php) — sistêmico
- tests/Feature/Governance/AuditAppendOnlyTest.php — trigger MySQL bloqueia UPDATE/DELETE

---

## Refs

- [ADR 0079 Constituição Governança](../../../../memory/decisions/0079-constituicao-oimpresso-7-camadas-governanca.md) Art. 9 (Auditoria)
- [ADR 0084 mcp_audit_log append-only trigger](../../../../memory/decisions/0084-triggers-mysql-imutabilidade-mcp-audit-log.md)
- [ADR 0086 Governance Fase 5 MVP](../../../../memory/decisions/0086-fase-5-mvp-governance-actiongate-warn.md)
- [ADR 0094 Constituição V2](../../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md)
