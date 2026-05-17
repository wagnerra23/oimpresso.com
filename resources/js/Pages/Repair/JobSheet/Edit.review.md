---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: Repair/JobSheet/Edit
file: resources/js/Pages/Repair/JobSheet/Edit.tsx
charter_present: true
charter_file: Edit.charter.md
runbook_present: true
runbook_file: memory/requisitos/Repair/RUNBOOK-jobsheet-edit.md
append_only: true
---

# Review estática — `Repair/JobSheet/Edit.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos

- AppShellV2 ✓ · PageHeader shared ✓ · charter+RUNBOOK ✓ (compliance MWART completo)
- `useForm` ✓ · `Deferred` importado ✓
- `JobSheetPayload` com `custom_field_1..5` — extensibilidade legacy
- `options` 6 dropdowns pesados (mesma issue Create)
- Comment "FSM transitions ficam no Show (não aqui)" — separação correta ADR 0143
- Campos `security_pwd`/`security_pattern` carregados — risco LGPD se plaintext

## Riscos Tier 0

1. **PERF/M2 — `options` EAGER** (mesmo issue Create — deferir).
2. **LGPD/M2 — `security_pwd`/`security_pattern` carregados em payload Edit**: se plaintext, qualquer admin vê senha. Confirmar masking + `Crypt::decryptString` controlado.
3. **FSM/L3 — Validar Pest que Edit NÃO mexe `current_stage_id`** (proibições § trait `GuardsFsmTransitions`).
4. **AUDIT/L3 — Custom fields 1-5 sem rastro**: alteração de defects/security não gera activity log visível.
5. **CHARTER/L4 — Drift check round 2**.

## Top 5 recomendações

1. P0 — Deferir `options` no controller.
2. P0 — Confirmar `security_pwd`/`security_pattern` encrypted at rest + masked UI (mostrar `••••` + botão "revelar" com audit log).
3. P1 — Pest GUARD: Edit não dispara FSM transition (apenas atributos).
4. P1 — Activity log nas alterações sensíveis (Spatie ActivityLog se disponível).
5. P3 — Charter drift round 2.
