# MWART migrations log

> Tracking de rotas migradas Blade → Inertia/React via padrão **MWART**
> (Module Web App React Transition). Ver [ADR MWART-0001](sprints/s2-os-listagem/01-adr-mwart-contract.md).
>
> Status: 🟡 staging | 🟢 100% prod | ⚫ Blade deletado

## 2026-05-06 — repair.index (`/repair/repair`)

- **Sprint:** 2
- **Módulo:** Repair
- **PR:** TBD (PR3)
- **Flag:** `MWART_REPAIR_INDEX` (default `false`) + `MWART_REPAIR_INDEX_BIZ` (CSV de `business_id`s; vazio = todos)
- **Beta inicial proposto:** `business_id=4` (ROTA LIVRE)
- **Soak staging:** TBD
- **Status:** ⏳ planejado / código em PR
- **Owner:** [W]

Notas:

- Caminho AJAX (DataTables JSON do Blade legacy) **preservado intacto**. Branch Inertia adicionado depois do `if (request()->ajax())`, antes do `return view(...)`.
- Helpers privados no `RepairController`: `mwartEnabled(string $key, int $business_id)` + `buildInertiaIndexData(Request $request, int $business_id)`.
- Migration de 5 índices compostos em `transactions` (todos com `business_id` primeiro, filtrando por `sub_type='repair'`).
- Resource: `Modules\Repair\Http\Resources\RepairListResource`.
- Tests: `Modules/Repair/Tests/Feature/RepairIndexMwartTest.php` (registrado em `phpunit.xml`).
