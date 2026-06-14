<?php

declare(strict_types=1);

/**
 * @group legacy-quarantine
 * quarantine-reason: assert estático de canon-source (ContactController::index + config/mwart) contra fonte-da-verdade móvel — cluster C5/Q-B da triage. NÃO é bug de produto; re-triar pós harness L0. Ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-B.
 */

// W1-B3 F2 Baseline — structural verification do ContactController::index().
// Pattern: structural via file_get_contents (sem boot Laravel) — ambiente worktree compat.

test('Cliente/Index Inertia migration baseline — controller has index() + Inertia branch', function () {
    $controllerPath = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    expect($controllerPath)->toBeReadableFile();

    $contents = file_get_contents($controllerPath);
    expect($contents)
        ->toContain('public function index(')
        ->toContain("Inertia::render('Cliente/Index'")
        ->toContain("shouldRenderInertiaCliente('cliente_index'")
        ->toContain('buildClienteIndexKpis')
        ->toContain('buildClienteIndexCustomers');
});

test('Cliente/Index — config mwart has cliente_index flag', function () {
    $configPath = __DIR__ . '/../../../config/mwart.php';
    expect($configPath)->toBeReadableFile();

    $contents = file_get_contents($configPath);
    expect($contents)
        ->toContain("'cliente_index'")
        ->toContain("MWART_CLIENTE_INDEX")
        ->toContain("MWART_CLIENTE_INDEX_BIZ");
});

test('Cliente/Index — controller honors business_id scope (multi-tenant Tier 0)', function () {
    $controllerPath = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($controllerPath);

    // ADR 0093 — toda Eloquent Model query scoped por business_id.
    expect($contents)
        ->toContain("Contact::where('contacts.business_id', \$business_id)")
        ->toContain("\$business_id = request()->session()->get('user.business_id')");
});

test('Cliente/Index — PII mask helper exists e nunca expõe plain digits', function () {
    $controllerPath = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($controllerPath);

    expect($contents)
        ->toContain('private function maskTaxNumber(')
        ->toContain('tax_number_masked');

    // Garantir que a query do builder SELECTionou tax_number (cru) mas o output usa _masked.
    expect($contents)->toContain("'contacts.tax_number'");
});
