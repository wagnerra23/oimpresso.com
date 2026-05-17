---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: Repair/ProducaoOficina/Index
file: resources/js/Pages/Repair/ProducaoOficina/Index.tsx
charter_present: true
charter_file: Index.charter.md
runbook_present: false
runbook_notes: sem RUNBOOK-producaooficina-index.md específico (RUNBOOK.md genérico apenas)
append_only: true
---

# Review estática — `Repair/ProducaoOficina/Index.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos

- AppShellV2 ✓
- Kanban 5 colunas Recepção→Diagnóstico→Aguardando→Execução→Pronto
- **Genericização vertical**: tipos `code/item/usage_meter/slot/area/executor` permitem OficinaAuto/ComunicacaoVisual/Vestuario reusarem — pattern arquitetural Tier 1 forte
- `LabelOverrides` + `SlotGroup` props vindo de `business.repair_settings` — multi-tenant aware ✓
- Drag-and-drop HTML5 nativo (US-REPAIR-PROD-4 2026-05-09)
- Default conservador B1..B4 + E1..E2 se biz não configurou — sane fallback ✓

## Riscos Tier 0

1. **MWART/M2 — SEM RUNBOOK específico**: hook deveria ter bloqueado. `/mwart-override` registrado? Verificar.
2. **DRAG-DROP/M2 — HTML5 nativo sem persistência confirmada**: se drag não bate no backend (`router.put`), card volta posição original? UX confusion. Validar feedback otimista + rollback.
3. **MULTI-TENANT/L3 — `repair_settings.labels` injetado**: confirmar controller usa `Auth::user()->business_id`.
4. **A11Y/L2 — Drag-and-drop HTML5 sem keyboard alternative**: inacessível teclado (WCAG 2.1.1). dnd-kit oferece keyboard mas HTML5 nativo NÃO.
5. **VERTICAL/L3 — Charter cobre genericização?**: validar charter Repair/ProducaoOficina não esquece labelOverrides.

## Top 5 recomendações

1. P0 — Criar `RUNBOOK-producaooficina-index.md` ou `/mwart-override`.
2. P0 — A11Y: substituir HTML5 drag-drop por @dnd-kit (suporta keyboard) — KanbanDndProvider pattern OficinaAuto já existe!
3. P1 — Validar UX rollback drag falho (toast erro + posição original).
4. P2 — Pest GUARD multi-tenant: biz=4 vê só labels biz=4.
5. P3 — Charter drift round 2 (vertical genericização docs).
