<?php

declare(strict_types=1);

/**
 * Wagner 2026-05-27 — sub-tab "Placas" do OssTab drawer 760.
 *
 * Daniela @ Martinho cadastrou Heinig Pre-Moldados em prod e pediu ver
 * caminhoes do cliente direto no drawer Cliente -- sem abrir
 * /oficina-auto/veiculos separado.
 *
 * Reusa `VehiclesTab` legado (Show.tsx) mas com NOVO PlacasSubTab que
 * self-fetch via AJAX (drawer parent nao carrega vehicles no payload).
 *
 * GUARDs estruturais (file_get_contents, sem DB).
 *
 * Refs: ADR 0179 (drawer 760) · ADR 0093 (multi-tenant Tier 0) · ADR 0137 (vehicles).
 */

// ─── GUARD 1: Backend ClienteVeiculosController existe + escopo Tier 0 ───

test('GUARD 1 — ClienteVeiculosController existe com scope multi-tenant Tier 0', function () {
    $path = __DIR__ . '/../../../Modules/Crm/Http/Controllers/ClienteVeiculosController.php';
    expect($path)->toBeReadableFile();

    $contents = file_get_contents($path);
    expect($contents)
        ->toContain('class ClienteVeiculosController')
        ->toContain('public function index(Request $request, int $id)')
        // Multi-tenant Tier 0 ADR 0093 — scope explicito antes da query relacional.
        ->toContain("Contact::where('business_id', \$businessId)")
        ->toContain("->where('id', \$id)")
        // Vehicle scope reforçado (defense-in-depth, mesmo com global scope).
        ->toContain("Vehicle::where('business_id', \$businessId)")
        ->toContain("->where('contact_id', \$contact->id)")
        // Permission gate matricial canon.
        ->toContain("can('customer.view')")
        ->toContain("can('supplier.view')");
});

// ─── GUARD 2: Rota GET /cliente/{id}/veiculos registrada ──────────────────

test('GUARD 2 — rota GET cliente/{id}/veiculos registrada em Modules/Crm/Routes/web.php', function () {
    $path = __DIR__ . '/../../../Modules/Crm/Routes/web.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Route::get('{id}/veiculos'")
        ->toContain("ClienteVeiculosController::class")
        ->toContain("'veiculos.index'");
});

// ─── GUARD 3: PlacasSubTab existe + self-fetch via fetch() ────────────────

test('GUARD 3 — PlacasSubTab faz self-fetch via fetch() sem dependencia Inertia', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/_drawer/oss/PlacasSubTab.tsx';
    expect($path)->toBeReadableFile();

    $contents = file_get_contents($path);
    expect($contents)
        // Componente principal.
        ->toContain('export default function PlacasSubTab')
        // Self-fetch via fetch() — NAO usa router.reload (Inertia).
        ->toContain('fetch(url.toString()')
        ->toContain('/cliente/${contactId}/veiculos')
        // Loading/error/data state local.
        ->toContain('useState<VehiclesPayload | null>')
        ->toContain("setError(") // graceful error
        // Debounce search 300ms (alinhado VehiclesTab _show original).
        ->toContain('setTimeout');
});

// ─── GUARD 4: OssTab integra Placas + visibility gate oficinaAutoEnabled ──

test('GUARD 4 — OssTab declara placas no SUB_TABS + renderiza condicional oficinaAutoEnabled', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/_drawer/OssTab.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        // Import do componente.
        ->toContain("import PlacasSubTab from './oss/PlacasSubTab'")
        // Prop nova com default false (graceful biz=4 Larissa vestuario).
        ->toContain('oficinaAutoEnabled?: boolean')
        ->toContain('oficinaAutoEnabled = false')
        // Sub-tab declarada com gate requiresOficinaAuto.
        ->toContain("{ key: 'placas', label: 'Placas'")
        ->toContain('requiresOficinaAuto: true')
        // Filtra via useMemo.
        ->toContain('requiresOficinaAuto || oficinaAutoEnabled')
        // Render condicional duplo (key === 'placas' AND oficinaAutoEnabled).
        ->toContain("active === 'placas' && oficinaAutoEnabled")
        ->toContain('<PlacasSubTab contactId={contact.id}');
});

// ─── GUARD 5: Cliente/Index.tsx passa oficinaauto_enabled pro OssTab ──────

test('GUARD 5 — Cliente/Index.tsx declara prop + repassa pro OssTab', function () {
    $path = __DIR__ . '/../../../resources/js/Pages/Cliente/Index.tsx';
    $contents = file_get_contents($path);

    expect($contents)
        // PageProps declara.
        ->toContain('oficinaauto_enabled?: boolean')
        // Repassa pro OssTab.
        ->toContain('oficinaAutoEnabled={props.oficinaauto_enabled ?? false}');
});

// ─── GUARD 6: ContactController Cliente/Index payload inclui oficinaauto_enabled

test('GUARD 6 — ContactController::index Cliente/Index payload tem oficinaauto_enabled', function () {
    $path = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("'oficinaauto_enabled' => (bool) \$this->moduleUtil->isModuleInstalled('OficinaAuto')");
});
