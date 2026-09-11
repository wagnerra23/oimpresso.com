# RUNBOOK MWART — JobSheet/Index

> **Tela:** `/repair/job-sheet` · **Componente:** `resources/js/Pages/Repair/JobSheet/Index.tsx`
> **Wave:** W3-B6 Repair · **Sprint:** MWART massiva 2026-05-15
> **Refs:** [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0149](../../decisions/0149-mwart-screen-pattern-reuse-cowork.md) · [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)

## Status

| Item | Estado |
|---|---|
| Blade legacy | `Modules/Repair/Resources/views/job_sheet/index.blade.php` (preservado) |
| Inertia branch | `Modules/Repair/Http/Controllers/JobSheetController::index()` linha ~305 (já existia em flag MWART parcial — esta wave EXPANDE) |
| Flag | `MWART_REPAIR_JOB_SHEET_INDEX` |
| Cliente piloto canary | biz=1 (Wagner WR2) — biz=4 ROTA LIVRE NÃO usa Repair |

## Decisões F1 PLAN

1. **Pattern reuse**: blueprint `prototipo-ui/prototipos/os/cowork-app.jsx` — listagem OS com tabs stage, KPIs (Abertas / Atrasadas / Valor) e filtros. Adaptado pra contexto JobSheet (status legacy via `RepairStatus` + futuro FSM `current_stage_id`).
2. **Coexistência FSM + legacy**: tela funciona em ambos cenários: (a) OS sem `current_stage_id` (legacy) mostra `status.name` via RepairStatus; (b) OS com pipeline FSM mostra stage canônico. Toggling via accessor.
3. **DataTables → Inertia paginator**: substitui pipeline AJAX legacy por `paginate()` server-side com `Inertia::defer` na lista (carga inicial leve).
4. **Filtros**: q (texto), status_id (multiselect), service_staff_id, location_id, due_start/due_end.

## F2 BASELINE — preservar Blade

Pest `Wave3B6JobSheetIndexBaselineTest.php`:
- Flag OFF → retorna Blade (sem `X-Inertia` header) — preserva pipeline DataTables AJAX
- Permission `job_sheet.view_all`/`view_assigned` respeitada
- `business_id` scope (biz cross-tenant 99 não vaza)

## F3 CODE — Inertia branch

Controller (`JobSheetController::index`):
```php
if ($this->mwartEnabled('repair_job_sheet_index', (int) $business_id)) {
    return Inertia::render('Repair/JobSheet/Index', [
        'filters' => $validated,
        'jobsheets' => Inertia::defer(fn () => $this->buildJobSheetsInertiaPayload($request, $business_id)),
        'meta' => Inertia::defer(fn () => $this->buildJobSheetsInertiaMeta($business_id)),
        'permissions' => $this->buildJobSheetsPermissions($request->user()),
    ]);
}
```

UI (`Index.tsx`):
- `<PageHeader>` + KPIs (Abertas, Atrasadas, Valor em aberto, Total mês)
- Tabs filtro por stage/status (Abertas / Atrasadas / Todas / status custom)
- Tabela: # · Cliente · Aparelho · Status · Responsável · Prazo · Valor
- `<EmptyState>` PT-BR quando vazio
- Link "Nova OS" → `/repair/job-sheet/create`

## F4 QA

Pest `Wave3B6JobSheetIndexInertiaTest.php`:
- Flag ON → `Inertia::render('Repair/JobSheet/Index', ...)`
- `permissions.view_all` populado
- Filtros (q, status_id, due_start) refletidos em validated
- Cross-tenant biz=99 não enxerga biz=1
- Sort whitelist (rejeita SQL injection)

## F5 CUTOVER

```bash
# Canary biz=1 (Wagner WR2 internal — não cliente)
echo "MWART_REPAIR_JOB_SHEET_INDEX=true" >> .env
echo "MWART_REPAIR_JOB_SHEET_INDEX_BIZ=1" >> .env
php artisan config:clear
# Smoke real Wagner 7d → expansão
```

Rollback trivial: remover env vars. Coexistência Blade preservada.

## Riscos

- **R1 (BAIXO)** — `RepairStatus.name` é dinâmico per-business. Cores arbitrárias. UI usa fallback `slate` quando color não mapeada.
- **R2 (BAIXO)** — OS legada sem `current_stage_id` (default). Sem panel FSM na linha (panel só em `Show.tsx`).

## Aprovação

Wagner sign-off pendente (Wave 3 batch).
