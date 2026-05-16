---
page: /oficina-auto/service-orders/{id}
component: resources/js/Pages/OficinaAuto/ServiceOrders/Show.tsx
owner: wagner
status: live
last_validated: 2026-05-16
parent_module: OficinaAuto
related_adrs: [0137, 0143, 0110, 0093]
tier: A
charter_version: 1
---

# Page Charter — /oficina-auto/service-orders/{id}

> **Status:** live (V0). Detalhe completo de uma OS com FSM action panel + timeline auditável.

## Mission

Tela única-fonte-da-verdade sobre 1 OS — mecânico/atendente acompanha estado FSM, próximas ações disponíveis, histórico append-only de transições, valor acumulado (locação ativa).

## Goals — Features (faz)

- `<PageHeader>` com OS#{id} + status badge + ações contextuais (Imprimir, Voltar, Editar)
- Resumo: vehicle.plate, vehicle_type, contact, order_type, entered_at, expected_completion
- **ServiceOrderFsmActionPanel** — botões dinâmicos por stage atual (ADR 0143 pipeline canon) com RBAC role-aware
- **Timeline** append-only de `sale_stage_history` (transição, ator, timestamp, side-effects)
- Locação: card "Valor a receber" = daily_rate × dias_locacao (accessor `valor_receber`) + flag `is_overdue` vermelho se atrasada
- Manutenção complexa: lista itens (peças + serviços) com subtotal
- Multi-tenant Tier 0 — 404 se OS de outro business
- Inertia::defer em items aggregated + timeline > 20 entries

## Non-Goals — Features (NÃO faz)

- UPDATE direto em `current_stage_id` (PROIBIDO — usar `ExecuteStageActionService`)
- Comentários livres (vai pra `notes` field ou audit log via action)
- Edição de items inline (vai pra Edit page)
- Geração NFe inline (núcleo Modules/NfeBrasil via action canônica)

## UX Targets

- p95 first-paint < 600ms (1 OS + 20 history entries)
- FSM action button disabled state com tooltip explicativo ("Role X não autoriza")
- Timeline scroll virtual quando > 50 entries
- Cores semânticas Cockpit V2 consistentes com Index

## UX Anti-patterns

- Botão FSM sem checagem RBAC (canon = `sale_stage_action_roles`)
- Submit FSM sem confirm dialog em ações críticas (`is_critical=true`)
- Edição direta de status via select (PROIBIDO — usar action panel)
- Eager-load completo de transaction.lines sem defer

## Tests anti-regressão

- [Modules/OficinaAuto/Tests/Feature/FsmTransitionTest.php](../../../../../Modules/OficinaAuto/Tests/Feature/FsmTransitionTest.php)
- [Modules/OficinaAuto/Tests/Feature/ServiceOrderCrudTest.php](../../../../../Modules/OficinaAuto/Tests/Feature/ServiceOrderCrudTest.php)

## Refs

- [SPEC.md US-OFICINA-001 + US-OFICINA-003](../../../../../memory/requisitos/OficinaAuto/SPEC.md)
- [RUNBOOK-show.md](../../../../../memory/requisitos/OficinaAuto/RUNBOOK-show.md)
- [ADR 0143 FSM pipeline LIVE prod](../../../../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- [ADR 0129 FSM canônica tabular](../../../../../memory/decisions/0129-state-machine-canonica-fsm-rbac.md)
