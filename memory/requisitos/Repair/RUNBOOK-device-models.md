# RUNBOOK MWART — DeviceModels (Index + Create + Edit)

> **Telas:** `/repair/device-models`, `/repair/device-models/create`, `/repair/device-models/{id}/edit`
> **Componentes:** `resources/js/Pages/Repair/DeviceModels/{Index,Create,Edit}.tsx`
> **Wave:** Blade T1 Migration C · **Data:** 2026-05-17
> **Refs:** [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md)

## Status

| Item | Estado |
|---|---|
| Blade legacy index | `Modules/Repair/Resources/views/device_model/index.blade.php` (preservado) |
| Blade legacy create | `Modules/Repair/Resources/views/device_model/create.blade.php` (preservado — modal) |
| Blade legacy edit | `Modules/Repair/Resources/views/device_model/edit.blade.php` (preservado — modal) |
| Inertia Index | `DeviceModelController::index()` branch já existia (sprint 2.5) — **expandida** com filtros |
| Inertia Create | `DeviceModelController::create()` — **NOVO branch** Inertia |
| Inertia Edit | `DeviceModelController::edit()` — **NOVO branch** Inertia |
| Flags | `MWART_REPAIR_DEVICE_MODELS_INDEX` (existente), `MWART_REPAIR_DEVICE_MODELS_CREATE` (novo), `MWART_REPAIR_DEVICE_MODELS_EDIT` (novo) |
| Cliente piloto canary | biz=1 (Wagner WR2 internal — biz=4 ROTA LIVRE NÃO usa Repair) |

## Decisões F1 PLAN

1. **Coexistência preservada**: Blade modal legacy continua respondendo quando flag OFF. Mudança é opt-in.
2. **DeviceModel é catálogo Repair compartilhado** entre verticais (Vestuario, ComunicacaoVisual, OficinaAuto). NÃO toca FSM (ADR 0143).
3. **Página dedicada substitui modal**: Inertia branch usa rota cheia, não modal — alinhado padrão JobSheet/Create.
4. **Filtros server-side**: `brand_id` + `device_id` via querystring → Controller scope.

## F2 BASELINE

Blade existente continua funcional. Pest cobre `flag OFF → Blade`.

## F3 CODE

### Controller — branches Inertia adicionados

```php
// index() — branch existente expandida pra ler filtros (?brand_id=&device_id=)
if ($this->mwartEnabled('repair_device_models_index', (int) $business_id)) {
    $query = DeviceModel::with('Device', 'Brand')->where('business_id', $business_id);
    if ($request->filled('brand_id'))  $query->where('brand_id', (int) $request->brand_id);
    if ($request->filled('device_id')) $query->where('device_id', (int) $request->device_id);
    $models = $query->orderBy('id', 'desc')->get([...]);
    return Inertia::render('Repair/DeviceModels/Index', [...]);
}

// create() — NOVO branch
if ($this->mwartEnabled('repair_device_models_create', (int) $business_id)) {
    return Inertia::render('Repair/DeviceModels/Create', [
        'brands'  => Brands::forDropdown($business_id, false, true),
        'devices' => Category::forDropdown($business_id, 'device'),
    ]);
}

// edit() — NOVO branch
if ($this->mwartEnabled('repair_device_models_edit', (int) $business_id)) {
    return Inertia::render('Repair/DeviceModels/Edit', [
        'model'   => [...],
        'brands'  => Brands::forDropdown($business_id, false, true),
        'devices' => Category::forDropdown($business_id, 'device'),
    ]);
}
```

### UI

- **Index** — KpiGrid (total · marcas · categorias) + filter chips brand/device + DataTable (marca · modelo · categoria · checklist · ações)
- **Create** — Form 4 campos, submit POST `/repair/device-models`
- **Edit** — Form pré-populado, submit PUT `/repair/device-models/{id}`

## F4 QA

Pest `Modules/Repair/Tests/Feature/DeviceModelsInertiaSmokeTest.php`:
- Flag OFF → Blade (sem header `X-Inertia`)
- Flag ON → `Inertia::render('Repair/DeviceModels/{Index,Create,Edit}', ...)`
- Cross-tenant biz=99 NÃO enxerga biz=1 (R-REPA-001 multi-tenant ADR 0093)
- Filtros brand/device aplicados
- `whitelist business_ids` respeitada

## F5 CUTOVER

NÃO cutover automático. Coexistência permanente. Canary opt-in via .env:

```bash
echo "MWART_REPAIR_DEVICE_MODELS_INDEX=true"  >> .env
echo "MWART_REPAIR_DEVICE_MODELS_CREATE=true" >> .env
echo "MWART_REPAIR_DEVICE_MODELS_EDIT=true"   >> .env
echo "MWART_REPAIR_DEVICE_MODELS_INDEX_BIZ=1"  >> .env
echo "MWART_REPAIR_DEVICE_MODELS_CREATE_BIZ=1" >> .env
echo "MWART_REPAIR_DEVICE_MODELS_EDIT_BIZ=1"   >> .env
php artisan config:clear
```

Rollback trivial: remover env vars.

## Riscos

- **R1 (BAIXO)** — Blade legacy usa AJAX submit retornando JSON `{success, msg}`. Inertia branch usa redirect padrão Laravel via `useForm.post()`. Comportamento UX divergente intencional (página dedicada vs modal).
- **R2 (BAIXO)** — `repair_checklist` é string pipe-separated (`'tela|bateria|tampa'`). Frontend preserva textarea simples (sem split UI). Backend grava como veio.
- **R3 (MUITO BAIXO)** — `Category::forDropdown($business_id, 'device')` retorna array `{id: name}` filtrado por categoria_type 'device' — pode estar vazio em business novo (Empty state apropriado).

## Aprovação

Wagner sign-off pendente.
