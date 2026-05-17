---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: OficinaAuto/ProducaoOficina/Index
file: resources/js/Pages/OficinaAuto/ProducaoOficina/Index.tsx
charter_present: true
charter_file: Index.charter.md
runbook_present: false
runbook_notes: sem RUNBOOK-producaooficina-index.md em memory/requisitos/OficinaAuto/
append_only: true
---

# Review estática — `OficinaAuto/ProducaoOficina/Index.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos

- AppShellV2 ✓
- Kanban 5 colunas caçambas (Disponível/Locada/Aguardando/Manutenção/Pronta)
- Refs ricos: ADR 0137, 0143, 0110 + protótipo `prototipo-ui/.../visual-source.html` (1213 linhas — fonte canônica)
- `useMemo`/`useCallback` lições PR #717 aplicadas (anti re-render loop) ✓
- `KanbanDndProvider` (dnd-kit canon) + `DragConfirmDialog` confirmation
- `CacambaProducaoSheet` drawer próprio (NÃO ServiceOrderSheet)

## Riscos Tier 0

1. **MWART/M2 — SEM RUNBOOK**: hook bloquear ou `/mwart-override`.
2. **DRAG/M2 — Confirmation dialog OK, mas `PendingTransition` precisa Pest GUARD**: usuário arrasta entre colunas, dialog confirma, request bate FSM Service (`ExecuteStageActionService`?). Validar fluxo end-to-end.
3. **CACHE/L3 — `KanbanGroups` props EAGER**: 5 listas chegam síncronas — candidato defer.
4. **STORAGE/L4 — Sem `localStorage` pra ordem de colunas?**: se Wagner customiza order, perde refresh.
5. **A11Y/L3 — dnd-kit OK pra keyboard mas focus-order entre cards precisa teste**.

## Top 5 recomendações

1. P0 — Criar `RUNBOOK-producaooficina-index.md` (espelhar `Vehicles/Index` RUNBOOK).
2. P0 — Pest GUARD: drag entre colunas dispara FSM action via `ExecuteStageActionService` (NUNCA UPDATE direto em `current_stage_id`).
3. P1 — Deferir `groups` no controller.
4. P2 — `localStorage` `oimpresso.oficina.producao.col_order` (opcional Wagner-pref).
5. P3 — A11Y: teste keyboard tab-order kanban.
