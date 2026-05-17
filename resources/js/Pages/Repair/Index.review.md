---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: Repair/Index
file: resources/js/Pages/Repair/Index.tsx
charter_present: false
charter_file: null
runbook_present: true
runbook_file: memory/requisitos/Repair/RUNBOOK-repair-index.md
append_only: true
---

# Review estática — `Repair/Index.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos

- AppShellV2 ✓ · PageHeader+KpiCard+EmptyState shared ✓
- 14 colunas filtros (q, statuses[], contact, location, staff, datas, sort, dir, per_page, is_completed) — robusto
- `repairs: Paginator` EAGER + `meta.totals.em_andamento/completas` EAGER — Tier 0 PERF risk
- Sem `Deferred` aparente — controller deveria deferir `repairs`
- `useState`+`useMemo` ✓ · sem `useForm` (router.get pra filtros)
- Sem `localStorage` (filtros persistem só na URL — aceitável)

## Riscos Tier 0

1. **PERF/M1 — `repairs` Paginator EAGER**: viola RUNBOOK-inertia-defer-pattern (Tier 0 2026-05-15). Listagens com paginate() = candidato OBRIGATÓRIO a `Inertia::defer`.
2. **CHARTER/M2 — SEM charter** apesar de tela P0 do módulo Repair. Pattern `Repair/Show.charter.md` existe — replicar.
3. **MULTI-TENANT/L3 — Sem indicação visível de `business_id`**: confiar no controller. Validar Pest cross-tenant `biz=1 vs biz=99`.
4. **ICONS/L4 — Mix lucide-react + sem `<Icon>` helper**: outras telas Repair usam `<Icon name=...>` (canonical). Inconsistência.
5. **DEBT/M3 — Comment "port 1:1 Blade legacy DataTables"**: indica débito não fechado MWART F3.

## Top 5 recomendações

1. P0 — Criar `Repair/Index.charter.md` (espelhar `Repair/Show.charter.md`).
2. P0 — Deferir `repairs` no controller + `<Deferred data="repairs" fallback={<TableSkeleton/>}>`.
3. P1 — Pest GUARD cross-tenant + smoke biz=1 (ADR 0101).
4. P2 — Padronizar ícones: lucide-react direto OR `<Icon>` (escolher um e migrar).
5. P3 — Documentar débito DataTables→TanStack em US (`tasks-create`).
