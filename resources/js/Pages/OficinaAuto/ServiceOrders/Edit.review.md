---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: OficinaAuto/ServiceOrders/Edit
file: resources/js/Pages/OficinaAuto/ServiceOrders/Edit.tsx
charter_present: false
charter_file: null
runbook_present: true
runbook_file: memory/requisitos/OficinaAuto/RUNBOOK-edit.md
append_only: true
---

# Review estática — `OficinaAuto/ServiceOrders/Edit.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos

- AppShellV2 ✓ · PageHeader ✓ · RUNBOOK ✓ · **charter AUSENTE**
- `useForm` (put) ✓
- `toLocalInput()` helper p/ `datetime-local` — boa prática
- `status` free string editável diretamente — pode burlar FSM
- Pequeno e enxuto V0

## Riscos Tier 0

1. **CHARTER/M2 — AUSENTE**: hook `block-mwart-violation.ps1` permitiu Edit em `.tsx` sem `.charter.md`? Verificar override.
2. **FSM BYPASS/M1 — `status` editável free string**: usuário pode setar `status='entregue'` sem passar por FSM (ADR 0143 § "UPDATE direto em current_stage_id" — Trait `GuardsFsmTransitions`). Se `status` ≠ `current_stage_id` OK, mas validar significado de `status` no model.
3. **TIMEZONE/L3 — `toLocalInput` usa browser TZ**: cliente em outra TZ pode ver hora errada (ADR 0066 format_date shift +3h ROTA LIVRE pegadinha).
4. **RUNBOOK/L4 — RUNBOOK-edit.md cobre 2 telas (ServiceOrders+Vehicles)**: validar SoC.
5. **VALIDATION/L4 — Sem indicação obrigatórios**.

## Top 5 recomendações

1. P0 — Criar `Edit.charter.md` (template `Show.charter.md` ao lado).
2. P0 — Validar relação `status` (Edit) vs FSM `current_stage_id`: deprecar `status` free se FSM é canônico (ADR 0143 V1).
3. P1 — TZ: usar `Intl.DateTimeFormat` ou enviar UTC sempre.
4. P2 — RUNBOOK específico ou doc SoC compartilhada.
5. P3 — Required visual indicators.
