---
title: "RUNBOOK MWART — Repair/Dashboard/Index"
module: Repair
tela: Repair/Dashboard/Index
owner: W
status: ativo
last_validated: "2026-09-09"
related_adrs: [0104-processo-mwart-canonico-unico-caminho, 0093-multi-tenant-isolation-tier-0, 0358-doutrina-de-teste-tenant-98-supersede-0101]
---

# RUNBOOK MWART — Repair/Dashboard/Index

> **Tela:** `/repair/dashboard` · **Componente:** `resources/js/Pages/Repair/Dashboard/Index.tsx`
> **Fonte de design:** `prototipo-ui/cowork/repair-page.jsx` região `Painel` (L45-103)
> **Diff medido:** [6telas-index-visual-comparison.md §3.2](6telas-index-visual-comparison.md)
> **Refs:** [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR UI-0029](../_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)

## Status

| Item | Estado |
|---|---|
| Blade legacy | `Modules/Repair/Resources/views/dashboard/index.blade.php` (preservado) |
| Inertia branch | `DashboardController::index()` L44-90 — já existia sob flag MWART |
| Flag | `mwart.repair_dashboard_index` |
| Charter | `resources/js/Pages/Repair/Dashboard/Index.charter.md` (`status: live`) |
| Casos | `resources/js/Pages/Repair/Dashboard/Index.casos.md` |

## Os dois defeitos que esta onda conserta (medidos, não supostos)

### 1. O KPI conta a coisa errada

`DashboardController.php:80-81`:

```php
'total_repairs'       => is_countable($job_sheets_by_status) ? count($job_sheets_by_status) : 0,
'service_staff_count' => is_countable($job_sheets_by_service_staff) ? count($job_sheets_by_service_staff) : 0,
```

`count()` de um **agrupamento** conta as LINHAS do grupo — quantos status distintos aparecem,
não quantas OS existem. Num negócio com 6 status configurados, `total_repairs` é ~6 para
sempre, com 3 ou 3.000 OS. A tela rotula honestamente ("Status únicos"), mas o nome da prop
promete outra coisa, e nenhum dos dois números responde a pergunta que quem abre um dashboard
de oficina tem: *quantas folhas estão abertas e quantas estão atrasadas?*

O protótipo responde exatamente isso: **Folhas pendentes** (hero, com "N sem técnico
atribuído") · **Concluídas** · **Entrega vencida**.

### 2. "Top aparelhos" nasce vazio

`DashboardController.php:86` manda `'trending_devices_chart' => []` — literal. O
`RepairUtil::getTrendingDevices()` **é chamado** na linha 42 e o resultado é descartado. A tela
tem o card, o `Deferred`, o skeleton e o `emptyMsg`: tudo funciona, e nunca mostra nada.

## F2 BACKEND

| KPI | Como sai | Coluna |
|---|---|---|
| `pending` | folhas cujo status **não** é de conclusão | join `repair_statuses.is_completed_status` |
| `pending_unassigned` | dessas, as sem técnico | `service_staff` null/0 |
| `completed` | folhas em status de conclusão | idem |
| `overdue` | pendentes com `delivery_date` no passado | `delivery_date` |

Uma agregada só, com join no catálogo de status — não 4 queries. `business_id` explícito nas
duas pontas (`JobSheet` não tem global scope; é o idioma do módulo).

`trending_devices_chart` passa a devolver o que o `getTrendingDevices()` já calcula, no mesmo
shape `{device, count}` dos irmãos.

**Fora desta onda:** o **Ticket médio** do protótipo. É valor monetário → Regra Mestre de VALOR
(dupla prova + antes→depois) e decisão [W]. O alerta de atrasadas com ação "Ver as atrasadas"
depende de a listagem aceitar o filtro por querystring — outra onda.

## F3 FRONTEND

`KpiGrid cols={2}` → 4 KPIs. "Entrega vencida" muda de tom quando há atraso (o protótipo usa
`tone={atrasadas.length ? "danger" : "default"}`) — número neutro quando é zero não vira alarme
falso. O card "Top aparelhos" não muda: ele já estava certo, só recebia lista vazia.

## F4 QA

- `Modules/Repair/Tests/Feature/RepairDashboardContratoTest.php` (já existe — **estender**, não
  duplicar).
- **CT 100**, nunca local:
  `tailscale ssh root@ct100-mcp "docker exec oimpresso-staging php artisan test --filter=RepairDashboardContrato"`
- Tenant fictício **98** ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)).

## F5 CUTOVER

Sem cutover: a flag `mwart.repair_dashboard_index` já governa quem vê a tela React.

## Riscos

| Risco | Mitigação |
|---|---|
| Folha com `status_id` órfão (status apagado no legado) some da contagem | o join é `leftJoin`; sem status a folha conta como **pendente**, que é o lado seguro |
| `delivery_date` nulo | `whereNotNull` antes da comparação — sem prazo não é atraso |
| FSM | esta tela é **read-only**; não toca `current_stage_id` nem transição. [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) intacta |
