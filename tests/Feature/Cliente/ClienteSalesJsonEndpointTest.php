<?php

declare(strict_types=1);
// @covers-us US-CRM-065

/**
 * Fix 2026-06-08 — endpoint JSON das vendas do cliente (drawer Operacoes -> Vendas).
 *
 * Bug: no paradigma drawer 760px (ADR 0179, MWART_CLIENTE_INDEX) o SalesTab dentro
 * de OssTab recebia `sales=undefined` e NAO tinha carga inicial — so buscava ao
 * filtrar/paginar. Resultado: skeleton infinito, "as vendas nao aparecem no
 * cadastro de cliente". No Show.tsx full-page funcionava via <Deferred data="sales">
 * (Inertia::defer); o drawer nao tem esse wrapper. GET /cliente/{id}/sales-json da
 * ao SalesTab a fonte AJAX que faltava (espelha o self-fetch de PaymentsTab/anexos).
 *
 * GUARDs estruturais (file_get_contents) + 1 integracao (auth gate graceful-skip),
 * mesmo bar do ClienteAnexosEndpointTest.
 *
 * Refs: ADR 0093 (multi-tenant Tier 0) · ADR 0179 (drawer 760).
 */

// ─── GUARD 1: rota registrada ─────────────────────────────────────────────

test('GUARD 1 — routes/web.php registra GET /cliente/{id}/sales-json', function () {
    $contents = file_get_contents(__DIR__ . '/../../../routes/web.php');

    expect($contents)
        ->toContain("Route::get('/cliente/{id}/sales-json', [ContactController::class, 'salesJson'])")
        ->toContain("->name('cliente.sales.json')");
});

// ─── GUARD 2: ContactController::salesJson — tenant scope + permissao ──────

test('GUARD 2 — ContactController::salesJson com business_id scope + permissao + delega ao paginador', function () {
    $contents = file_get_contents(__DIR__ . '/../../../app/Http/Controllers/ContactController.php');

    expect($contents)
        ->toContain('public function salesJson($id)')
        // Permissao: precisa poder ver cliente/fornecedor (espelha show()).
        ->toContain("auth()->user()->can('customer.view')")
        ->toContain("abort(403, 'Unauthorized action.')")
        // Tier 0 (ADR 0093 IRREVOGAVEL): 404 cross-tenant antes de qualquer dado.
        ->toContain("Contact::where('business_id', \$business_id)->findOrFail(\$id)")
        // Reusa o paginador canon (ja forca business_id + type=sell + !draft).
        ->toContain('$this->buildClienteSalesPaginator((int) $id, (int) $business_id, request())')
        ->toContain('response()->json(');
});

// ─── GUARD 3: drawer (OssTab) faz self-fetch via jsonEndpoint ─────────────

test('GUARD 3 — OssTab passa jsonEndpoint pro SalesTab (nao mais sales=undefined)', function () {
    $contents = file_get_contents(__DIR__ . '/../../../resources/js/Pages/Cliente/_drawer/OssTab.tsx');

    expect($contents)
        // Fonte de dados do drawer = endpoint JSON dedicado.
        ->toContain('jsonEndpoint={`/cliente/${contact.id}/sales-json`}')
        // O bug antigo (sales=undefined fixo) nao volta.
        ->not->toContain('sales={undefined}');
});

// ─── GUARD 4: SalesTab self-fetch (mount) sem quebrar modo Inertia ────────

test('GUARD 4 — SalesTab busca sozinho quando jsonEndpoint presente, preserva modo Inertia', function () {
    $contents = file_get_contents(__DIR__ . '/../../../resources/js/Pages/Cliente/_show/SalesTab.tsx');

    expect($contents)
        // Modo self-fetch.
        ->toContain('jsonEndpoint?: string')
        ->toContain('const selfFetch = jsonEndpoint != null')
        ->toContain('useEffect(')
        ->toContain('loadJson')
        ->toContain('await fetch(url')
        // Modo Inertia legado intacto (Show.tsx full-page).
        ->toContain("only: ['sales']")
        ->toContain('router.visit')
        // Estado de erro com retry (nao fica preso em skeleton em falha de rede).
        ->toContain('data-testid="sales-error"')
        ->toContain('data-testid="sales-retry-btn"');
});

// ─── GUARD 5: endpoint exige sessao (integracao, nao vaza sem auth) ───────

test('GUARD 5 — GET /cliente/{id}/sales-json nao vaza vendas sem auth', function () {
    // Cross-tenant real precisa de DB+seed que sqlite memory CI nao tem;
    // skip-graceful pattern canon (igual GUARD 4 de ClienteAnexosEndpointTest).
    if (! \Illuminate\Support\Facades\Schema::hasTable('contacts')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $response = $this->get('/cliente/1/sales-json');
    // Sem auth: redirect login (302) ou 401/403/404. O importante e NAO 200
    // (vazaria vendas sem sessao) nem 500 (erro de codigo).
    expect(in_array($response->status(), [302, 401, 403, 404], true))->toBeTrue();
});
