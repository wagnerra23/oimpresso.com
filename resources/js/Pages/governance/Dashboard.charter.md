---
page: /governance
component: resources/js/Pages/governance/Dashboard.tsx
owner: wagner
status: live
last_validated: "2026-05-09"
parent_module: Governance
related_adrs: ["0110-cockpit-pattern-v2-canon-list-detail", "0079-constituicao-oimpresso-7-camadas-governanca", "0094-constituicao-v2-7-camadas-8-principios", "0114-prototipo-ui-cowork-loop-formalizado"]
tier: A
charter_version: 2
---

# Page Charter — /governance

> **Status:** live. Página de referência viva do **Cockpit Pattern V2** com `<PageHeader>` + `<KpiCard>` shared (gold-standard de reuso de componentes).
> **v2 (2026-05-09):** estendida com seção "Saúde do ecossistema" — fechou a maior parte do escopo da US-COPI-098 do epic Cockpit Saúde sem precisar de tela nova.

---

## Mission

Dashboard executivo de governança E saúde do ecossistema: visão consolidada de checks Constituição (multi-tenant isolation, brief uptime, custo brain B, PII leak, profile distiller drift) + audit highlights + saúde do ecossistema (failed jobs Horizon, custo IA 24h, narrativas hourly Brain A) pra Wagner enquanto operador sênior decidir intervenções.

---

## Goals — Features (faz)

- AppShellV2 + topnav
- `<PageHeader>` shared canônico (h1 + subtitle + ações)
- 2 fileiras de KpiGrid separadas por h2 de seção (`Constituição` cols=6 + `Saúde do ecossistema` cols=3)
- `<KpiCard>` shared (NÃO inline custom) com tones semânticos (default/success/warning/danger/info)
- Audit highlights list com badges status semântico (rose pra erro, emerald pra ok)
- Narrativas Brain A 24h (top 5) com badges severity (info=blue, warning=amber, critical=rose) — top 5 últimas 24h
- Multi-tenant Tier 0: dados scopados business_id (default biz=1 — Wagner)
- Degradação graciosa: failed_jobs / jana_mensagens / jana_health_narratives ausentes → KPI mostra "—" + description explicativa, ao invés de erro

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
