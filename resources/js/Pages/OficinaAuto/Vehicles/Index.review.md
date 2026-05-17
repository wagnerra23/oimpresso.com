---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: OficinaAuto/Vehicles/Index
file: resources/js/Pages/OficinaAuto/Vehicles/Index.tsx
charter_present: true
charter_file: Index.charter.md
runbook_present: true
runbook_file: memory/requisitos/OficinaAuto/RUNBOOK-index.md
append_only: true
---

# Review estática — `OficinaAuto/Vehicles/Index.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos

- AppShellV2 ✓ · charter+RUNBOOK ✓
- "Dashboard de Caçambas" pattern Sells/Index (KPI cards + filter pills + toggle Lista/Grade)
- Imports: VehicleStatusBadge + **reusa ServiceOrderSheet** de `../ServiceOrders/_components/`
- Refs: ADR 0137, 0110 + Mockup `demo-martinho-2026-05-13/mockup.html`
- `CurrentRental` payload rico (dias_locacao, valor_receber, is_overdue, contact)
- `useCallback`/`useEffect`/`useMemo` ✓

## Riscos Tier 0

1. **PERF/M2 — `vehicles: VehicleRow[]` provavelmente EAGER**: validar paginação + defer.
2. **CROSS-MODULE/L3 — Reusa ServiceOrderSheet `../ServiceOrders/_components/`**: dependência cross-tela frágil; se ServiceOrderSheet quebrar, Vehicles/Index quebra. Move pra `_components/shared/`?
3. **STATUS/L3 — `VehicleStatus` enum local** vs backend? Validar sync.
4. **PII/L3 — Contact name + delivery_address expostos na lista**: permission gate?
5. **A11Y/L4 — Toggle Lista/Grade `Table2`/`LayoutList` icons sem aria-label**.

## Top 5 recomendações

1. P0 — Deferir `vehicles` paginator no controller.
2. P1 — Mover `ServiceOrderSheet` pra `_components/shared/` se reuso já em 2+ telas.
3. P1 — `VehicleStatus` enum centralizado (em `types.ts` compartilhado).
4. P2 — `@can` gate em contact_name visibility.
5. P3 — A11Y aria-label nos toggles.
