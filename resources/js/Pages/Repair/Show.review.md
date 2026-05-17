---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: Repair/Show
file: resources/js/Pages/Repair/Show.tsx
charter_present: true
charter_file: Show.charter.md
runbook_present: true
runbook_file: memory/requisitos/Repair/RUNBOOK-repair-show.md
append_only: true
---

# Review estática — `Repair/Show.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos

- AppShellV2 ✓ · PageHeader+EmptyState ✓ · charter+RUNBOOK ✓
- `Deferred` importado ✓
- `SellPayload` com `sell_lines[]`+`payments[]`+`activities[]` — payload pesado
- `fsm.enabled` flag opt-in via `mwart.repair_show_fsm_panel.enabled` — coerente
- `STAGE_COLOR_MAP` hardcoded — risco cor crua se mapear `bg-red-500`

## Riscos Tier 0

1. **CORES/M3 — `STAGE_COLOR_MAP` (linha 60+)**: validar se usa tokens semânticos (`text-destructive`) ou cru (`bg-red-500`). Charter Admin/GovernanceV4 cita anti-pattern explicitamente.
2. **PERF/M2 — `sell_lines`/`payments`/`activities` EAGER**: deferir candidates óbvios.
3. **FSM/L3 — Flag opt-in `mwart.repair_show_fsm_panel.enabled`**: confirmar default seguro + Pest cobre both modos.
4. **CHARTER/L4 — Drift check round 2**.
5. **AUDIT/L4 — `activities` opcional`?: Array<...>`**: se sempre presente, tipo deveria ser non-optional.

## Top 5 recomendações

1. P0 — Auditar `STAGE_COLOR_MAP`: trocar cores cruas por tokens semantic (`text-destructive`, `bg-warning`, etc).
2. P0 — Deferir `sell_lines`+`payments`+`activities` no controller.
3. P1 — Pest GUARD ambos modos (`fsm.enabled=true` e `=false`).
4. P2 — Tipo `activities` strict (não optional se sempre vem).
5. P3 — Charter drift round 2.
