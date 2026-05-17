---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: Repair/JobSheet/Create
file: resources/js/Pages/Repair/JobSheet/Create.tsx
charter_present: true
charter_file: Create.charter.md
runbook_present: true
runbook_file: memory/requisitos/Repair/RUNBOOK-jobsheet-create.md
append_only: true
---

# Review estática — `Repair/JobSheet/Create.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos

- AppShellV2 ✓ · PageHeader shared ✓ · charter+RUNBOOK ✓ (compliance MWART completo)
- `useForm` (Inertia) ✓ · `Deferred` IMPORTADO ✓ (positivo — uso comprovado pattern Tier 0)
- `options` prop com 7 dropdowns (repair_statuses, device_models, brands, devices, technecians, business_locations, repair_settings) — pesada
- `walk_in_customer` + `default_status` props pequenas EAGER OK
- Comment "OS nasce SEM `current_stage_id`" — FSM opt-in coerente com ADR 0143

## Riscos Tier 0

1. **PERF/M2 — `options` EAGER**: 7 dropdowns enormes (device_models pode ter 1000+) chegam síncronos. Candidato `Inertia::defer(['options'])` + `<Deferred>` skeleton no form.
2. **FSM/L3 — Pipeline iniciado opt-in no Show**: validar via Pest que Create NÃO seta `current_stage_id` (ADR 0143 + proibições § "UPDATE direto em current_stage_id").
3. **MULTI-TENANT/L3 — `business_id` injetado backend**: confirmar controller faz `Auth::user()->business_id`; sem ele no payload (correto, mas Pest GUARD).
4. **CHARTER/L4 — Drift check round 2**.
5. **VALIDATION/L4 — Campos de senha (`security_pwd`, `security_pattern`)**: armazenamento em plaintext? Risco LGPD se sim. Validar backend hash/encrypt.

## Top 5 recomendações

1. P0 — Deferir `options` no controller (`Inertia::defer(fn () => $this->buildOptions())`).
2. P0 — Validar criptografia `security_pwd`/`security_pattern` (LGPD Art. 46).
3. P1 — Pest GUARD: Create não inicia FSM pipeline.
4. P2 — Charter drift round 2.
5. P3 — UX: client-side required indicators (`*`) em campos obrigatórios.
