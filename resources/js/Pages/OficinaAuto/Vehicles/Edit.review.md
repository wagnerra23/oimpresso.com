---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: OficinaAuto/Vehicles/Edit
file: resources/js/Pages/OficinaAuto/Vehicles/Edit.tsx
charter_present: false
charter_file: null
runbook_present: true
runbook_file: memory/requisitos/OficinaAuto/RUNBOOK-edit.md
append_only: true
---

# Review estática — `OficinaAuto/Vehicles/Edit.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos

- AppShellV2 ✓ · PageHeader ✓ · RUNBOOK ✓ · **charter AUSENTE**
- `useForm` (put) ✓ · espelha Create
- `Vehicle` props com legacy_id ausente (vs Show que tem)
- Mesmas pegadinhas Create: sem máscara, sem regex, PII chassis/renavam

## Riscos Tier 0

1. **CHARTER/M2 — AUSENTE**: criar.
2. **VALIDATION/M2 — Mesma: sem MercosulPlate/regex**.
3. **PII/L3 — Edit chassis/renavam: log audit?**: alteração PII fiscal merece trail.
4. **HISTORIA/L3 — Mudar `manufacture_year` post-create faz sentido?**: imutabilidade soft (mileage cresce, ano não muda).
5. **RUNBOOK/L4 — Compartilhado** com ServiceOrders.

## Top 5 recomendações

1. P0 — Criar `Vehicles/Edit.charter.md`.
2. P0 — Aplicar `MercosulPlate` + regex + reusar componente Create.
3. P1 — Audit log alteração PII (Spatie ActivityLog).
4. P2 — Campos imutáveis post-create: `chassis`/`renavam`/`manufacture_year` readonly após primeiro save.
5. P3 — Charter drift round 2.
