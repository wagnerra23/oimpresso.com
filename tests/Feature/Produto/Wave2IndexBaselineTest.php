<?php

declare(strict_types=1);

/**
 * Pest test F2 BASELINE — Pages/Produto/Index (Wave 2 B4 Produto · Agent W2-C 2026-05-15)
 *
 * Cobre F2 do processo MWART canônico (ADR 0104):
 *   1. Rota /products responde em modo Blade legacy (sem X-Inertia)
 *   2. Controller index() preserva DataTables ajax pipeline intacto
 *   3. Multi-tenant biz=1 isolamento (Tier 0 ADR 0093)
 *
 * Tests estruturais (file-based) — não bootam o framework Laravel.
 */

if (! function_exists('repo_path')) {
    /**
     * Resolve caminho absoluto relativo à raiz do repo (3 levels above tests/Feature/Produto).
     * Evita dependência de base_path() (que exige Application booted).
     */
    function repo_path(string $relative): string
    {
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . ltrim($relative, '/\\');
    }
}

it('ProductController existe e tem método index()', function () {
    $reflection = new ReflectionClass(\App\Http\Controllers\ProductController::class);
    expect($reflection->hasMethod('index'))->toBeTrue();
});

it('ProductController importa Inertia (Wave 2 MWART)', function () {
    $source = file_get_contents(repo_path('app/Http/Controllers/ProductController.php'));
    expect($source)->toContain('use Inertia\\Inertia;');
});

it('ProductController index() tem branch header X-Inertia → Produto/Index', function () {
    $source = file_get_contents(repo_path('app/Http/Controllers/ProductController.php'));
    expect($source)->toContain("Inertia::render('Produto/Index'");
});

it('ProductController index() preserva return view product.index legacy', function () {
    $source = file_get_contents(repo_path('app/Http/Controllers/ProductController.php'));
    // Coexistência Tier 0: blade legacy ainda renderizado se X-Inertia ausente
    expect($source)->toContain("view('product.index')");
});

it('ProductController index() preserva DataTables ajax pipeline (Yajra)', function () {
    $source = file_get_contents(repo_path('app/Http/Controllers/ProductController.php'));
    expect($source)->toContain('Datatables::of($products)');
});

it('ProductController index() usa business_id global scope (Tier 0 ADR 0093)', function () {
    $source = file_get_contents(repo_path('app/Http/Controllers/ProductController.php'));
    expect($source)->toContain("session()->get('user.business_id')");
    // anti-padrão LICOES_F3: NÃO usar auth()->user()->business_id
    expect($source)->not->toContain('auth()->user()->business_id');
});

// 2026-10-10 — a lista no desenho do protótipo trocou os 3 builders da tela de cards
// (`buildProdutoIndexKpis/Rows/Categorias`) pela MESMA consulta da lista Blade. O comportamento
// (isolamento, paridade com o Blade, busca, paginação) é provado em ProdutoListaConsultaTest;
// estas duas checagens de texto só travam que a consulta recebe o business_id explícito.
it('Builders Produto/Index Inertia passam business_id explícito (Tier 0)', function () {
    $source = file_get_contents(repo_path('app/Http/Controllers/ProductController.php'));
    expect($source)->toContain('protected function consultaListaProdutos(int $business_id, array $f, bool $is_woocommerce)');
    expect($source)->toContain('protected function buildProdutoListaPaginada(int $business_id, array $f,');
});

it('Builders Produto/Index escopam queries por business_id', function () {
    $source = file_get_contents(repo_path('app/Http/Controllers/ProductController.php'));
    // A consulta DEVE filtrar por business_id (Tier 0 IRREVOGÁVEL)
    expect($source)->toContain("->where('products.business_id', \$business_id)");
});
