---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: OficinaAuto/ServiceOrders/Index
file: resources/js/Pages/OficinaAuto/ServiceOrders/Index.tsx
charter_present: true
charter_file: Index.charter.md
runbook_present: true
runbook_file: memory/requisitos/OficinaAuto/RUNBOOK-index.md
append_only: true
---

# Review estática — `OficinaAuto/ServiceOrders/Index.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos

- AppShellV2 ✓ · PageHeader+KpiCard+KpiGrid+EmptyState ✓ · charter+RUNBOOK ✓
- `ServiceOrderSheet` drawer canônico ✓ + `ServiceOrderStatusBadge` componente isolado
- `useCallback`/`useEffect` ✓
- Refs: V0.5 pré-reunião Martinho 13/maio + Mockup `demo-martinho-2026-05-13/mockup.html`
- `is_overdue`, `dias_locacao`, `valor_receber` accessors — backend ricos
- KPIs: `locacoes_ativas`, `manutencao_ativas`, `concluidas_mes`, `atrasadas`

## Riscos Tier 0

1. **PERF/M2 — `orders: PaginatedOrders` EAGER**: paginate() = candidato OBRIGATÓRIO `Inertia::defer` (Tier 0 2026-05-15).
2. **FILTER/L3 — `Filters {status, type, q}` URL state**: localStorage não usado (URL OK).
3. **MULTI-TENANT/L3 — Validar accessor `valor_receber` respeita business_id**.
4. **CHARTER/L4 — Drift charter round 2** (V0.5 demo evoluiu rápido).
5. **A11Y/L4 — KPI cards sem `role="status"`/`aria-label` rico**.

## Top 5 recomendações

1. P0 — Deferir `orders` no controller (`Inertia::defer(fn () => $this->buildOrdersPayload())`).
2. P1 — Pest GUARD `accessor valor_receber` cross-tenant biz=1 vs biz=99 (ADR 0101).
3. P2 — Charter drift round 2.
4. P2 — A11Y KPI rich label.
5. P3 — `localStorage` `oimpresso.oficina.os.last_filter` (Wagner-pref opt-in).
