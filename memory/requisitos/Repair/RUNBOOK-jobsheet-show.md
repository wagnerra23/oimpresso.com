# RUNBOOK MWART — JobSheet/Show

> **Tela:** `/repair/job-sheet/{id}` · **Componente:** `resources/js/Pages/Repair/JobSheet/Show.tsx`
> **Wave:** W3-B6 Repair · **Sprint:** MWART massiva 2026-05-15
> **Refs:** [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)

## Status

| Item | Estado |
|---|---|
| Blade legacy | `Modules/Repair/Resources/views/job_sheet/show.blade.php` |
| Inertia branch | `JobSheetController::show($id)` linha ~526 (NOVO) |
| Flag | `MWART_REPAIR_JOB_SHEET_SHOW` |
| Cliente piloto canary | biz=1 |

## Decisões F1 PLAN

1. **Pattern reuse**: blueprint `prototipo-ui/prototipos/os/cowork-app.jsx` painel detalhe OS (drawer-like main view).
2. **FSM Panel integrado**: importa `resources/js/Pages/Sells/_components/FsmActionPanel.tsx` (componente shared) — mas REPAIR usa endpoint próprio `/api/repair/job-sheets/{id}/fsm-actions` (ver `RepairFsmActionController`). Por isso o componente é **adaptado** num wrapper local `JobSheetFsmPanel` que aceita `endpoints` prop.
3. **Estado FSM dual**: se `current_stage_id IS NULL` mostra "Iniciar pipeline FSM" empty state. Se preenchido mostra stage badge + actions.
4. **Sections**: Header (OS#, status, cliente) · Aparelho (brand/device/model/serial) · Defects · Checklist (read) · Parts usadas · Anexos · Timeline (activities) · **FSM Panel**.

## F2 BASELINE

Pest `Wave3B6JobSheetShowBaselineTest.php`:
- Flag OFF → Blade preservado
- biz=99 não acessa OS biz=1 (404)
- Permission `job_sheet.view_all` OU `view_assigned` (created_by/service_staff próprio)

## F3 CODE

Controller (`JobSheetController::show`):
```php
if ($this->mwartEnabled('repair_job_sheet_show', (int) $business_id)) {
    return Inertia::render('Repair/JobSheet/Show', [
        'job_sheet' => $this->buildJobSheetShowPayload($job_sheet),
        'parts' => Inertia::defer(fn () => $this->buildJobSheetPartsPayload($job_sheet)),
        'activities' => Inertia::defer(fn () => $this->buildJobSheetActivitiesPayload($job_sheet)),
        'anexos' => Inertia::defer(fn () => $job_sheet->anexos->values()->toArray()),
        'fsm' => [
            'in_pipeline' => $job_sheet->current_stage_id !== null,
            'endpoints' => [
                'actions' => "/api/repair/job-sheets/{$job_sheet->id}/fsm-actions",
                'execute' => "/repair/job-sheets/{$job_sheet->id}/fsm-action",
                'start_pipeline' => "/repair/job-sheets/{$job_sheet->id}/fsm-start-pipeline",
            ],
        ],
        'permissions' => [...],
    ]);
}
```

UI (`Show.tsx`):
- Header com badge status + ações (Edit, Add Parts, Print, Delete)
- Grid 2 colunas: detalhes OS + FSM panel sidebar
- `<JobSheetFsmPanel>` chama endpoints próprios
- Timeline activities via `<Deferred>`

## ⛔ FSM RULE TIER 0 (ADR 0143)

**NÃO faz UPDATE direto em `current_stage_id`**. UI executa transição via POST `/repair/job-sheets/{id}/fsm-action` → backend chama `ExecuteStageActionService` → trait `GuardsFsmTransitions` aciona `FsmAuthorizationFlag::mark()` → save autorizado.

## F4 QA

Pest `Wave3B6JobSheetShowInertiaTest.php`:
- Flag ON → componente Inertia
- `fsm.endpoints` apontando rotas FSM corretas
- biz=99 → 404
- Permission deny → 403

**Pest FSM trait** (cross-feature):
- `it('UPDATE direto em current_stage_id lança UnauthorizedActionException')` — usa `JobSheet` direto, valida trait
- `it('ExecuteStageActionService respeita business_id (não muda OS de outro biz)')` — service via DI

## F5 CUTOVER

Canary biz=1 7d → expansão.

## Riscos

- **R1 (MÉDIO)** — `<FsmActionPanel>` shared Sells assume endpoints `/sells/...`. Adaptamos via wrapper `JobSheetFsmPanel` no Show.tsx que injeta endpoints REPAIR. Componente original Sells permanece intacto.
- **R2 (BAIXO)** — `activitylog` pesado pra OS com muitas mudanças — `Inertia::defer` mitiga.
