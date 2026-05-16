# RUNBOOK MWART — JobSheet/AddParts

> **Tela:** `/repair/job-sheet/add-parts/{id}` · **Componente:** `resources/js/Pages/Repair/JobSheet/AddParts.tsx`
> **Wave:** W3-B6 Repair · **Refs:** [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)

## Status

| Item | Estado |
|---|---|
| Blade legacy | `Modules/Repair/Resources/views/job_sheet/add_parts.blade.php` |
| Inertia branch | `JobSheetController::addParts($id)` linha ~874 (NOVO) |
| Flag | `MWART_REPAIR_JOB_SHEET_ADD_PARTS` |

## F1 PLAN

1. **Divergência blueprint**: form add-peças NÃO existe no blueprint OS (assumido fluxo POS). Pattern reuse parcial: header + tabela linhas peças (adapt POS pattern).
2. Form lista de peças (variation_id + quantidade) — adiciona linha dinamicamente.
3. **Sem FSM** — addParts é ação não-FSM (não muda current_stage_id).
4. Submit POST `/repair/job-sheet/save-parts/{id}`.

## F2 BASELINE

Pest `Wave3B6JobSheetAddPartsBaselineTest.php`:
- Flag OFF → Blade
- Permission `job_sheet.create` OR `edit` deny → 403

## F3 CODE

Controller:
```php
if ($this->mwartEnabled('repair_job_sheet_add_parts', (int) $business_id)) {
    return Inertia::render('Repair/JobSheet/AddParts', [
        'job_sheet' => $this->buildJobSheetMinPayload($job_sheet),
        'parts' => $parts,
        'status_update_data' => $status_update_data,
        'status_dropdown' => $status_dropdown,
        'status_template_tags' => $status_template_tags,
    ]);
}
```

UI:
- Tabela peças editável (variation_id select + qty number)
- Add/Remove row
- Submit → `/repair/job-sheet/save-parts/{id}`

## F4 QA

Pest `Wave3B6JobSheetAddPartsInertiaTest.php`:
- Flag ON → Inertia
- biz=99 → 404

## F5 CUTOVER

Canary biz=1.

## Riscos

- **R1 (BAIXO)** — variation lookup AJAX legacy via `/repair/job-sheet/get-part-row`. UI pode adaptar pra select async direto.
