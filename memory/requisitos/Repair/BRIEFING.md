# Repair — BRIEFING (estado consolidado)

> 1-pager canônico. Atualizado por PR (skill `brief-update` Tier B).
> Doc mãe: [SPEC.md](SPEC.md) · [ARCHITECTURE.md](ARCHITECTURE.md) · [README.md](README.md)
> Última atualização: 2026-05-25 (Wave Z-2 — Integração Vendas × Oficina mergeada em main)

## O que é

Kanban de Ordens de Serviço (OS) — infraestrutura **shared multi-vertical** consumida por `Modules/OficinaAuto`, `Modules/ComunicacaoVisual`, `Modules/Vestuario`. Vocabulário genérico (`code/item/usage_meter/slot/area/executor`) com `business.repair_settings` JSON pra overrides verticais.

## Capacidade canônica

- **JobSheet** entidade central (`repair_job_sheets`) — multi-tenant `business_id` global scope
- **RepairStatus** configurável por business com `is_completed_status` + `sort_order` heurística → 5 colunas fixas (Recepção / Diagnóstico / Aguardando peças / Em execução / Pronto)
- **ProducaoOficina** kanban drag-and-drop (US-REPAIR-PROD-2..4) — query real + fallback mock
- **MWART B6** ([Wave Massiva 2026-05-12](../../sessions/2026-05-12-wave-massiva-repair-mwart-b6.md)): Index/Show/Create/Edit/AddParts migrados Blade → Inertia/React (5 telas + 5 RUNBOOKs + 2 visual-comparisons)
- **FSM Pipeline canônico** ([ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)) — `RepairFsmActionController` orquestra 13 stages (`recebido_para_diagnostico` → ... → `entregue_completo`) × ~15 actions × 6 roles per-business via `ExecuteStageActionService`
- **Auto-faturar OS→Venda derivada** ([ADR 0192](../../decisions/0192-auto-faturar-os-venda-jobsheet-observer.md) · Wave Z-2 mergeada 2026-05-25) — `JobSheetObserver@updated` cria `Transaction(source='oficina', os_ref='OS-NNNN')` quando OS transiciona pra stage terminal `entregue_completo`; reverse hook (`cancelled_at`) cancela Transaction se OS reaberta; idempotente por `(business_id, os_ref)`; drawer ganha card "Esta OS gerou venda #V-NNNN" com breakdown peças/serviço/fiscal + atalhos Abrir/Imprimir/Compartilhar; cross-link bidirecional via evento `oimpresso:open-venda` (PRs #1500/#1502/#1505/#1508/#1509/#1510/#1511/#1513)

## Stack arquitetural

- 11 Controllers (Repair, JobSheet, ProducaoOficina, DeviceModel, RepairStatus, RepairSettings, RepairFsmAction, Customer, Dashboard, Data, Install)
- 3 Entities core (JobSheet, RepairStatus, DeviceModel) — todas com `business_id` indexado
- 9 charters Page Inertia (campeão do projeto)
- 10 Pest Feature tests cobrindo FSM, MWART B6 (5 telas), Produção Oficina, Consumer Arquivos
- Trait `GuardsFsmTransitions` em JobSheet bloqueia UPDATE direto em `current_stage_id`

## Clientes piloto

- **biz=1 (Wagner)** — sandbox dev + tests biz=1 obrigatório ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md))
- **CYCLE-06 Martinho Caçambas** (oficina caçambas) — candidato `Modules/OficinaAuto` via shared Repair, validação Kanban Produção 2026-05-13 ([sessão pré-Martinho 10h](../../sessions/2026-05-13-kanban-producao-oficina-cacambas.md))

## ROI / próximos passos

| Gap | Próximo PR | Owner |
|---|---|---|
| Smoke browser MCP salvo | Wave N | Claude |
| Service layer extraído (`KanbanProductionService` thin) | Wave M | Claude |
| Multi-tenant Pest dedicado (`MultiTenantRepairTest`) | Wave M | Claude |
| Migração biz=1 vendas legacy → FSM (`fsm:bulk-start-pipeline 1`) | Pós-canary | Wagner |

## ADRs canônicos

- [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) Multi-tenant Tier 0 IRREVOGÁVEL
- [0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) Processo MWART (Wave B6 seguiu)
- [0129](../../decisions/0129-state-machine-canonica-fsm-rbac.md) FSM tabular custom
- [0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) FSM Pipeline LIVE prod biz=1 (marco 2026-05-12)
- [0192](../../decisions/0192-auto-faturar-os-venda-jobsheet-observer.md) Auto-faturar OS→Venda via JobSheetObserver (Wave Z-2 marco 2026-05-25)
