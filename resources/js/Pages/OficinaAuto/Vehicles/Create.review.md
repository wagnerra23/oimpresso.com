---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: OficinaAuto/Vehicles/Create
file: resources/js/Pages/OficinaAuto/Vehicles/Create.tsx
charter_present: false
charter_file: null
runbook_present: true
runbook_file: memory/requisitos/OficinaAuto/RUNBOOK-create.md
append_only: true
---

# Review estática — `OficinaAuto/Vehicles/Create.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos

- AppShellV2 ✓ · PageHeader ✓ · RUNBOOK ✓ · **charter AUSENTE**
- `useForm` ✓
- Campos: plate, secondary_plate, chassis, secondary_chassis, vehicle_type, manufacture_year, model_year, renavam, engine, mileage_at_entry, fuel_type, color, notes
- Default `vehicle_type: 'automovel'` — fixed string
- Sem máscara plate (Mercosul ABC1D23) — frontend valida formato?

## Riscos Tier 0

1. **CHARTER/M2 — AUSENTE**: hook bloquear ou `/mwart-override`.
2. **VALIDATION/M2 — `plate` sem máscara Mercosul + sem regex client-side**: backend pode aceitar inválido, lixo no DB. Componente `MercosulPlate.tsx` existe em `_components/` — usar.
3. **PII/L3 — `chassis`+`renavam` PII fiscal**: log requests com `[REDACTED]` (proibições § PII).
4. **VALIDATION/L3 — `manufacture_year`/`model_year` sem min/max**: `1900-2100` boas faixas.
5. **MWART/L3 — RUNBOOK compartilhado** com ServiceOrders.

## Top 5 recomendações

1. P0 — Criar `Vehicles/Create.charter.md`.
2. P0 — Aplicar `MercosulPlate` formato + regex `^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$` client.
3. P1 — `chassis`/`renavam`: PII redactor backend + masking UI opcional.
4. P2 — Min/max anos manufatura/modelo.
5. P3 — Toast erro inline melhor.
