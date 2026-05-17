---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: Repair/Dashboard/Index
file: resources/js/Pages/Repair/Dashboard/Index.tsx
charter_present: true
charter_file: Index.charter.md
runbook_present: false
runbook_notes: módulo Repair tem RUNBOOK.md genérico + RUNBOOK-repair-index/show/jobsheet-*; falta RUNBOOK-dashboard.md específico
append_only: true
---

# Review estática — `Repair/Dashboard/Index.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos

- AppShellV2 ✓ · PageHeader+KpiCard shared ✓
- Sem `useForm` (read-only) — adequado pra dashboard
- 5 props pesadas (kpis + 4 charts) chegam EAGER — risco performance
- `_trending_devices_chart` ignorado com `void` + FIXME (US-REPAIR-DASH-1) — débito declarado
- Sem `<Deferred>` wrapper nas charts (ADR 0143 Inertia::defer pattern)
- Sem `localStorage` — não persiste filtros (mas dashboard não tem filtros)

## Riscos Tier 0

1. **PERF/M2 — Charts EAGER**: 4 charts (`job_sheets_by_status`, `job_sheets_by_service_staff`, `trending_brand_chart`, `trending_dm_chart`) chegam todas no first-paint sem `Inertia::defer` no controller — viola [RUNBOOK-inertia-defer-pattern.md](../../../../memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md). Wagner regra 2026-05-15 (Tier 0).
2. **DEBT/M3 — FIXME ativo não rastreado**: `US-REPAIR-DASH-1` no comentário sem MCP task vinculada (verificar via `tasks-list module:Repair`).
3. **CHARTER/L4 — Charter desatualizado?**: review.md round 1 não consultou charter pra validar drift; F1 round 2.
4. **RUNBOOK/L4 — Falta RUNBOOK-dashboard.md**: hook `block-mwart-violation.ps1` deveria ter bloqueado edit. Verificar se foi override.
5. **A11Y/L3 — Charts sem fallback texto**: KPIs e charts sem `aria-label`/`description` pra screen readers.

## Top 5 recomendações

1. P1 — Mover `trending_*_chart` props pra `Inertia::defer(...)` no controller; wrap em `<Deferred>` com skeleton.
2. P1 — Criar `memory/requisitos/Repair/RUNBOOK-dashboard.md` (ou justificar via `/mwart-override`).
3. P2 — Resolver FIXME `_trending_devices_chart` (painel próprio ou remover prop).
4. P2 — Validar charter vs implementação atual (drift check) no round 2.
5. P3 — A11Y: `aria-label` + `<figcaption>` nas charts.
