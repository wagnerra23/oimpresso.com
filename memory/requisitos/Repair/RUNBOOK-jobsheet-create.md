# RUNBOOK MWART — JobSheet/Create

> **Tela:** `/repair/job-sheet/create` · **Componente:** `resources/js/Pages/Repair/JobSheet/Create.tsx`
> **Wave:** W3-B6 Repair · **Refs:** [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)

## Status

| Item | Estado |
|---|---|
| Blade legacy | `Modules/Repair/Resources/views/job_sheet/create.blade.php` |
| Inertia branch | `JobSheetController::create()` linha ~373 (NOVO) |
| Flag | `MWART_REPAIR_JOB_SHEET_CREATE` |

## F1 PLAN

1. Pattern reuse: blueprint `prototipo-ui/prototipos/os/cowork-app.jsx` (NewOsModal pattern adaptado).
2. Wizard step-by-step: (1) Cliente · (2) Aparelho · (3) Defeitos+Checklist · (4) Anexos.
3. **FSM**: na criação, OS nasce SEM `current_stage_id` (legacy `status_id` apenas). Pipeline FSM iniciado opcionalmente em `Show.tsx`.
4. Submit type pode ser: `save`, `save_and_add_parts`, `save_and_upload_docs` (preservado do Blade).

## F2 BASELINE

Pest `Wave3B6JobSheetCreateBaselineTest.php`:
- Flag OFF → Blade preservado
- Permission `job_sheet.create` deny → 403

## F3 CODE

Controller:
```php
if ($this->mwartEnabled('repair_job_sheet_create', (int) $business_id)) {
    return Inertia::render('Repair/JobSheet/Create', [
        'options' => Inertia::defer(fn () => $this->buildJobSheetCreateOptions($business_id)),
        'walk_in_customer' => $walk_in_customer,
        'default_status' => $default_status,
    ]);
}
```

UI:
- Form Inertia `useForm`
- Combobox cliente (search async — versão simples: select com `options.contacts`)
- Submit POST `/repair/job-sheet` → redirect Show ou AddParts

## F4 QA

Pest `Wave3B6JobSheetCreateInertiaTest.php`:
- Flag ON → componente correto
- `options` carrega via defer
- Permission deny → 403

## F5 CUTOVER

Canary biz=1.

## Riscos

- **R1 (BAIXO)** — `walk_in_customer` é Contact especial — UI fallback se ausente.
