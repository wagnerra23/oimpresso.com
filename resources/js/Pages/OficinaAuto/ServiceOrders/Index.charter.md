---
page: /oficina-auto/service-orders
component: resources/js/Pages/OficinaAuto/ServiceOrders/Index.tsx
owner: wagner
status: live
last_validated: "2026-05-26"
parent_module: OficinaAuto
related_adrs:
  - 0137-modules-oficinaauto-qualificada
  - 0110-tipografia-canon-h1-subtitle
  - 0093-multi-tenant-isolation-tier-0
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0171-oficinaauto-ativacao-piloto-martinho-faseada
  - 0192-auto-faturar-os-venda-jobsheet-observer
  - 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada
tier: A
charter_version: 2
---

# Page Charter — /oficina-auto/service-orders

> **Status:** live (V0). Listagem-detalhe canon Cockpit Pattern V2 das Ordens de Serviço.
>
> **Sub-vertical 4 ([ADR 0194](../../../../../memory/decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) — 2026-05-26):** OS em prod biz=164 Martinho são sub-vertical 4 mecânica pesada (`order_type='manutencao'` predominante). KPI "locações ativas / atrasadas (overdue)" aplica primariamente pra schema sub-vertical 3 hipotético preservado nullable — quando sem dado real, exibe 0 sem erro. Auto-faturar OS→Venda extensão ADR 0192 LIVE 2026-05-25 (Card "Esta OS gerou venda #V-NNNN" no drawer).

## Mission

Dashboard operacional pra atendente/gerente da oficina decidir próxima ação em cada OS — visão consolidada de OS abertas, em serviço, aguardando aprovação, atrasadas (locação overdue) e concluídas pendente entrega.

## Goals — Features (faz)

- AppShellV2 + topnav padrão Cockpit V2 (ADR 0110)
- `<PageHeader>` shared (h1 "Ordens de Serviço" + subtitle + ações Criar OS / Importer Firebird)
- KpiGrid topo: OS abertas, em serviço, locações ativas, atrasadas (overdue), valor a receber consolidado
- Listagem com filtros: status, order_type (manutencao/locacao), vehicle.plate, contact, intervalo de datas
- Badge status semântico (rose=atrasada, amber=orcamento aguardando, blue=em_servico, emerald=concluida)
- Drawer detail (ServiceOrderSheet) ao clicar linha — FSM action panel + timeline append-only
- Multi-tenant Tier 0 (ADR 0093) — dados scopados business_id
- Inertia::defer obrigatório em KPIs agregados + paginação pesada (RUNBOOK-inertia-defer-pattern)

## Non-Goals — Features (NÃO faz)

- Edição inline na listagem (vai pra drawer ou Edit page)
- Trigger manual de WhatsApp (futuro US-OFICINA-006)
- Histórico full > 90 dias (paginar; arquivo via export)
- Importer Firebird inline (artisan command US-OFICINA-002)

## UX Targets

- p95 first-paint < 800ms (KpiGrid + 50 OS)
- 0 erros JS console
- Drawer abre < 200ms
- Cores semânticas Cockpit V2 (rose/amber/emerald/blue/info)

## UX Anti-patterns

- Cor crua `bg-red-100/bg-blue-100` (canon = rose/emerald semântico)
- KPI inline com `<Card>` custom (canon = `<KpiCard>` shared)
- `sessionStorage` (canon = querystring + Inertia)
- Eager-load de transactions paginate sem `Inertia::defer`

## Tests anti-regressão

- [Modules/OficinaAuto/Tests/Feature/ServiceOrderCrudTest.php](../../../../../Modules/OficinaAuto/Tests/Feature/ServiceOrderCrudTest.php)
- [Modules/OficinaAuto/Tests/Feature/FsmTransitionTest.php](../../../../../Modules/OficinaAuto/Tests/Feature/FsmTransitionTest.php)
- tests/Feature/Design/CockpitPatternConformanceTest.php (sistêmico)

## Refs

- [SPEC.md US-OFICINA-001](../../../../../memory/requisitos/OficinaAuto/SPEC.md)
- [RUNBOOK-index.md](../../../../../memory/requisitos/OficinaAuto/RUNBOOK-index.md)
- [ADR 0137](../../../../../memory/decisions/0137-modules-oficinaauto-qualificada.md)
- [ADR 0143 FSM canon](../../../../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
