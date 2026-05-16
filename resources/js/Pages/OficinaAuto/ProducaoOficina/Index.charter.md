---
page: /oficina-auto/producao
component: resources/js/Pages/OficinaAuto/ProducaoOficina/Index.tsx
owner: wagner
status: live
last_validated: 2026-05-16
parent_module: OficinaAuto
related_adrs: [0137, 0143, 0110, 0114]
tier: A
charter_version: 1
---

# Page Charter — /oficina-auto/producao

> **Status:** live (V0). Kanban operacional drag-drop de OS em produção — replica pré-arte Delphi WR_KANBAN.

## Mission

Visão Kanban tempo-real pro gerente de produção movimentar OS entre estágios (`aberta` → `em_servico` → `concluida` Simples; ou pipeline Complexa Vargas) via drag-drop, com confirmação em transições críticas e placa Mercosul visual.

## Goals — Features (faz)

- AppShellV2 + topnav
- `<PageHeader>` "Produção Oficina" + filtros (mecânico, order_type, prioridade)
- Colunas Kanban por stage FSM (CacambaKanbanColumn) — heading + count + total valor coluna
- Cards arrastáveis (CacambaCard) — placa Mercosul visual + vehicle + cliente + tempo em estágio + flag overdue (locação)
- Drag-drop @dnd-kit → ConfirmDialog em transições críticas (cancelar, voltar estágio) — ADR 0143 `is_critical`
- Drag-drop em transição padrão → ação imediata via `ExecuteStageActionService` (sem confirm)
- KanbanDndProvider isola contexto drag (não afeta outros componentes da página)
- Drawer detail (CacambaProducaoSheet) ao clicar card — FSM action panel completo
- Multi-tenant Tier 0 — colunas scopadas business_id
- Refresh manual (sem WebSocket no MVP V0) — Wagner pode chamar polling ou reload

## Non-Goals — Features (NÃO faz)

- Real-time WebSocket Centrifugo (futuro V1 — ADR 0058)
- Edição de campos via Kanban (drawer ou navegar pra Edit)
- Bulk drag-drop (1 card por vez — drag múltiplo confunde gerente)
- Histórico/audit log inline (vai pro drawer)

## UX Targets

- p95 first-paint < 1.2s (até 100 cards distribuídos em 5 colunas)
- Drag responsivo < 16ms por frame (60fps)
- Drop → status update < 300ms (otimista UI + reconcile)
- Placa Mercosul reconhecível < 50ms render

## UX Anti-patterns

- Drag sem feedback visual (canon = shadow + ghost card)
- Drop em coluna inválida sem mensagem (canon = toast explicativo + revert otimista)
- Polling agressivo < 5s (DDoS próprio backend)
- Card overflow text (canon = truncate + tooltip placa completa)

## Tests anti-regressão

- [Modules/OficinaAuto/Tests/Feature/FsmTransitionTest.php](../../../../../Modules/OficinaAuto/Tests/Feature/FsmTransitionTest.php)
- [Modules/OficinaAuto/Tests/Feature/ServiceOrderCrudTest.php](../../../../../Modules/OficinaAuto/Tests/Feature/ServiceOrderCrudTest.php)

## Refs

- [SPEC.md US-OFICINA-004 Kanban OS multi-item](../../../../../memory/requisitos/OficinaAuto/SPEC.md)
- [producao-oficina-cacamba-visual-comparison.md](../../../../../memory/requisitos/OficinaAuto/producao-oficina-cacamba-visual-comparison.md)
- [ADR 0143 FSM pipeline LIVE](../../../../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- Pré-arte Delphi: research/clientes-legacy-officeimpresso/_MAPPING/TELA-PRODUCAO-KANBAN.md
