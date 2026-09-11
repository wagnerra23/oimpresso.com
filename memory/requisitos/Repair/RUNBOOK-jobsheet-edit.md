# RUNBOOK MWART — JobSheet/Edit

> **Tela:** `/repair/job-sheet/{id}/edit` · **Componente:** `resources/js/Pages/Repair/JobSheet/Edit.tsx`
> **Wave:** W3-B6 Repair · **Refs:** [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)

## Status

| Item | Estado |
|---|---|
| Blade legacy | `Modules/Repair/Resources/views/job_sheet/edit.blade.php` |
| Inertia branch | `JobSheetController::edit($id)` linha ~565 (NOVO) |
| Flag | `MWART_REPAIR_JOB_SHEET_EDIT` |

## F1 PLAN

1. Pattern reuse: blueprint `prototipo-ui/prototipos/os/cowork-app.jsx` (form variant).
2. Form completo: contact_id, service_type, brand_id, device_id, device_model_id, serial_no, status_id (legacy), delivery_date, estimated_cost, defects, product_condition, service_staff, custom_fields (1-5), checklist.
3. **FSM**: `status_id` (RepairStatus legacy) coexiste com `current_stage_id` FSM. Form UI mostra apenas `status_id` (compat); transições FSM ficam no `Show.tsx` panel.
4. **Submit**: POST `/repair/job-sheet/{id}` (PUT via `_method=PUT`) → `JobSheetController::update` — caminho legacy preservado (NÃO toca FSM).

## F2 BASELINE

Pest `Wave3B6JobSheetEditBaselineTest.php`:
- Flag OFF → Blade preservado
- biz cross-tenant: edit OS biz=99 → 404 quando logado biz=1
- Permission `job_sheet.edit` deny → 403

## F3 CODE

Controller:
```php
if ($this->mwartEnabled('repair_job_sheet_edit', (int) $business_id)) {
    return Inertia::render('Repair/JobSheet/Edit', [
        'job_sheet' => $this->buildJobSheetEditPayload($job_sheet),
        'options' => Inertia::defer(fn () => $this->buildJobSheetEditOptions($business_id)),
    ]);
}
```

UI:
- `<form>` controlled via `useForm` Inertia
- Tabs: Cliente / Aparelho / Defeitos / Checklist / Anexos
- Submit → `router.put('/repair/job-sheet/' + id)`
- Cancel → router.visit Show

## F4 QA

Pest `Wave3B6JobSheetEditInertiaTest.php`:
- Flag ON → Inertia
- payload completo
- biz=99 → 404

## F5 CUTOVER

Canary biz=1 7d.

## Riscos

- **R1 (BAIXO)** — checklist dinâmico (texto pipe-separated) — UI converte pra array de checkboxes.
- **R2 (BAIXO)** — `repair_settings` per-business afeta visibilidade de campos (brand, serial_no). UI condicional via `options.repair_settings`.
