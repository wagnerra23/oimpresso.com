---
page: /governance
component: resources/js/Pages/governance/Dashboard.tsx
owner: wagner
status: live
last_validated: 2026-05-08
parent_module: Governance
related_adrs: [0110, 0079, 0094]
tier: A
charter_version: 1
---

# Page Charter — /governance

> **Status:** live. Página de referência viva do **Cockpit Pattern V2** com `<PageHeader>` + `<KpiCard>` shared (gold-standard de reuso de componentes).

---

## Mission

Dashboard executivo de governança: visão consolidada de saúde dos checks (multi-tenant isolation, brief uptime, custo brain B, PII leak, profile distiller drift) + audit highlights pra Wagner enquanto operador sênior decidir intervenções.

---

## Goals — Features (faz)

- AppShellV2 + topnav
- `<PageHeader>` shared canônico (h1 + subtitle + ações)
- `<KpiCard>` shared (NÃO inline custom) com tones semânticos (default/success/warning/danger/info)
- Audit highlights list com badges status semântico (rose pra erro, emerald pra ok)
- Multi-tenant Tier 0: dados scopados business_id (default biz=1 — Wagner)

---

## Non-Goals — Features (NÃO faz)

- ❌ Edição/configuração de checks (vão pra Pages governance/Policies, governance/Audit detalhada)
- ❌ Trigger manual de checks (canon = `php artisan jana:health-check` schedule daily 06:00 BRT)
- ❌ Histórico longo (mostra só audit_highlights.slice(0, 10) — visão de topo)
- ❌ Alerta push (notifica via storage/logs/laravel.log ALERT entries — canon ADR 0094)

---

## UX Targets

- p95 first-paint < 800ms (KPIs + 10 audit entries)
- 0 erros JS console
- Cores semânticas Cockpit V2: rose=erro, emerald=ok, amber=warning, blue=info
- Atualização: refresh manual via reload (não real-time WebSocket no MVP)

---

## UX Anti-patterns

- ❌ Cor crua `bg-red-100/bg-blue-100` em badges (corrigido 2026-05-08 — canon = rose/emerald semântico)
- ❌ KPIs inline com `<Card>` custom (canon = `<KpiCard>` shared)
- ❌ h1 inline (canon = `<PageHeader>` shared)
- ❌ `sessionStorage`

---

## Tests anti-regressão

- [tests/Feature/Design/CockpitPatternConformanceTest.php](../../tests/Feature/Design/CockpitPatternConformanceTest.php) — sistêmico (esta Page no canon target)
- [tests/Feature/Design/CockpitTypographyConformanceTest.php](../../tests/Feature/Design/CockpitTypographyConformanceTest.php) — tipografia

---

## Refs

- [Design.md §16 Cockpit V2](../../../../Design.md)
- [ADR 0110 Cockpit Pattern V2](../../../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [ADR 0094 Constituição V2](../../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) — checks de saúde
