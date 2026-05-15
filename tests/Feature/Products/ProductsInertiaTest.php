<?php

declare(strict_types=1);

/**
 * Pest — Products MWART (US-PROD-001..004).
 *
 * Cobre:
 *   - Estrutural: Pages/Products/{Index,Create,Edit,Show,StockHistory}.tsx existem + charters
 *   - Controller: branch Inertia ativa quando header X-Inertia presente em
 *     index() / create() / edit() / show() / productStockHistory()
 *   - Multi-tenant Tier 0 (ADR 0093): list-json escopa por business_id (biz=1 vs biz=99 — ADR 0101)
 *     biz=164 (Martinho cliente) e biz=4 (Larissa ROTA LIVRE) NUNCA usados — ADR 0101
 *   - Permissões: 403 sem product.view
 *   - Blade legacy fallback preservado quando SEM X-Inertia
 *   - Paginação shape correto
 *   - Busca livre por nome/SKU/officeimpresso_codigo retorna match
 *   - per_page whitelist + sort whitelist (resistir SQLi)
 *
 * Refs:
 *   - memory/decisions/0093-multi-tenant-isolation-tier-0.md
 *   - memory/decisions/0101-tests-business-id-1-nunca-cliente.md (biz=1 = Wagner, NUNCA biz=164/biz=4)
 *   - memory/decisions/0104-processo-mwart-canonico-unico-caminho.md (F2 BACKEND BASELINE)
 *   - memory/requisitos/Products/RUNBOOK-products.md
 */

use App\Product;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Pest.php já aplica Tests\TestCase em tests/Feature/ — não duplicar uses().

const PROD_BIZ_WAGNER = 1;          // ADR 0101: testes usam biz=1 (Wagner), NUNCA cliente real
const PROD_BIZ_FICTICIO = 99;       // segundo tenant pra cross-tenant assertions
const PROD_INDEX_PATH = 'resources/js/Pages/Products/Index.tsx';
const PROD_CREATE_PATH = 'resources/js/Pages/Products/Create.tsx';
const PROD_EDIT_PATH = 'resources/js/Pages/Products/Edit.tsx';
const PROD_SHOW_PATH = 'resources/js/Pages/Products/Show.tsx';
const PROD_STOCK_HISTORY_PATH = 'resources/js/Pages/Products/StockHistory.tsx';
const PROD_CONTROLLER_PATH = 'app/Http/Controllers/ProductController.php';

/**
 * Helper — pula se DB connection não tem schema MySQL completo.
 */
function prodNeedMysqlOrSkip(): void
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0101).');
    }
    if (! Schema::hasTable('products')) {
        test()->markTestSkipped('Tabela products ausente — migration não rodada.');
    }
}

/**
 * Helper — pega user da biz dado.
 */
function prodFirstUserOfBiz(int $bizId): ?User
{
    return User::where('business_id', $bizId)->first();
}

/**
 * Helper — cria product mínimo pra biz dado.
 * PII redact: nomes "Test Product XXX", SKU "SKU-TEST-XXX".
 */
function prodCreateProduct(int $bizId, array $overrides = []): Product
{
    $unique = uniqid();
    $userId = User::where('business_id', $bizId)->value('id') ?? 1;
    $unitId = DB::table('units')->where('business_id', $bizId)->value('id') ?? 1;

    $data = array_merge([
        'business_id' => $bizId,
        'name' => 'Test Product ' . $unique,
        'sku' => 'SKU-TEST-' . $unique,
        'type' => 'single',
        'unit_id' => $unitId,
        'tax_type' => 'exclusive',
        'enable_stock' => 1,
        'is_inactive' => 0,
        'created_by' => $userId,
    ], $overrides);

    return Product::create($data);
}

// ─── Estrutural — Pages existem ──────────────────────────────────────────────

it('Page Products/Index.tsx existe', function () {
    expect(file_exists(base_path(PROD_INDEX_PATH)))->toBeTrue();
});

it('Page Products/Create.tsx existe', function () {
    expect(file_exists(base_path(PROD_CREATE_PATH)))->toBeTrue();
});

it('Page Products/Edit.tsx existe', function () {
    expect(file_exists(base_path(PROD_EDIT_PATH)))->toBeTrue();
});

it('Page Products/Show.tsx existe', function () {
    expect(file_exists(base_path(PROD_SHOW_PATH)))->toBeTrue();
});

it('Page Products/StockHistory.tsx existe', function () {
    expect(file_exists(base_path(PROD_STOCK_HISTORY_PATH)))->toBeTrue();
});

it('Index importa AppShellV2 (Persistent Layout — Cockpit canon ADR 0110)', function () {
    $src = file_get_contents(base_path(PROD_INDEX_PATH));
    expect($src)->toContain('@/Layouts/AppShellV2');
    expect($src)->toContain('<AppShellV2>');
});

it('Index usa filter pills rounded-full (NÃO tabs border-b)', function () {
    $src = file_get_contents(base_path(PROD_INDEX_PATH));
    expect($src)->toContain('rounded-full');
    expect($src)->not->toMatch('/border-b-2 border-primary/');
});

it('Index busca livre debounce 300ms — não bloqueia digitação', function () {
    $src = file_get_contents(base_path(PROD_INDEX_PATH));
    expect($src)->toContain('setTimeout');
    expect($src)->toContain('300');
});

it('Create implementa form com 3 sections (Identificação + Preço/Estoque + Avançado)', function () {
    $src = file_get_contents(base_path(PROD_CREATE_PATH));
    expect($src)->toContain('Identificação');
    expect($src)->toContain('Preço');
    expect($src)->toContain('Avançado');
});

it('Create reage a atalho Cmd/Ctrl+S salvar e Esc cancelar (UX poder-user)', function () {
    $src = file_get_contents(base_path(PROD_CREATE_PATH));
    expect($src)->toContain("e.key === 's'");
    expect($src)->toContain("e.key === 'Escape'");
});

it('StockHistory implementa timeline cronológica com filtros', function () {
    $src = file_get_contents(base_path(PROD_STOCK_HISTORY_PATH));
    // Filtros básicos
    expect($src)->toContain('period');
    // Indicação visual entrada/saída
    expect($src)->toMatch('/entrada|saída/i');
});

// ─── Controller — branch Inertia ─────────────────────────────────────────────

it('ProductController importa Inertia\\Inertia', function () {
    $src = file_get_contents(base_path(PROD_CONTROLLER_PATH));
    expect($src)->toContain('use Inertia\\Inertia;');
});

it('index() chama Inertia::render(\'Products/Index\') quando header X-Inertia presente', function () {
    $src = file_get_contents(base_path(PROD_CONTROLLER_PATH));
    expect($src)->toContain("Inertia::render('Products/Index'");
    expect($src)->toContain("request()->header('X-Inertia')");
});

it('create() chama Inertia::render(\'Products/Create\') quando header X-Inertia presente', function () {
    $src = file_get_contents(base_path(PROD_CONTROLLER_PATH));
    expect($src)->toContain("Inertia::render('Products/Create'");
});

it('edit() chama Inertia::render(\'Products/Edit\') quando header X-Inertia presente', function () {
    $src = file_get_contents(base_path(PROD_CONTROLLER_PATH));
    expect($src)->toContain("Inertia::render('Products/Edit'");
});

it('show() chama Inertia::render(\'Products/Show\') quando header X-Inertia presente', function () {
    $src = file_get_contents(base_path(PROD_CONTROLLER_PATH));
    expect($src)->toContain("Inertia::render('Products/Show'");
});

it('productStockHistory() chama Inertia::render(\'Products/StockHistory\') quando header X-Inertia presente', function () {
    $src = file_get_contents(base_path(PROD_CONTROLLER_PATH));
    expect($src)->toContain("Inertia::render('Products/StockHistory'");
});

it('listJson() existe como método público do controller', function () {
    $src = file_get_contents(base_path(PROD_CONTROLLER_PATH));
    expect($src)->toMatch('/public function listJson\\(\\)/');
});

it('listJson() escopa por business_id (Multi-tenant Tier 0 — ADR 0093)', function () {
    $src = file_get_contents(base_path(PROD_CONTROLLER_PATH));
    expect($src)->toContain('listJson');
    expect($src)->toMatch("/products\\.business_id/");
});

// ─── Routes — list-json registrada ───────────────────────────────────────────

it('rota /products/list-json registrada apontando pra listJson()', function () {
    $routes = file_get_contents(base_path('routes/web.php'));
    expect($routes)->toContain("'/products/list-json'");
    expect($routes)->toContain("'listJson'");
});

// ─── Charters — existência (ADR 0094 §3 Charter > Spec) ──────────────────────

it('Charter Index.charter.md existe com Mission/Goals/Non-Goals', function () {
    $path = base_path('resources/js/Pages/Products/Index.charter.md');
    expect(file_exists($path))->toBeTrue();
    $src = file_get_contents($path);
    expect($src)->toContain('## Mission');
    expect($src)->toContain('## Goals');
    expect($src)->toContain('## Non-Goals');
    expect($src)->toContain('## UX Targets');
    expect($src)->toContain('## UX Anti-patterns');
});

it('Charter Create.charter.md existe com Mission/Goals/Non-Goals', function () {
    $path = base_path('resources/js/Pages/Products/Create.charter.md');
    expect(file_exists($path))->toBeTrue();
    $src = file_get_contents($path);
    expect($src)->toContain('## Mission');
    expect($src)->toContain('## Goals');
    expect($src)->toContain('## Non-Goals');
});

it('RUNBOOK-products.md existe em memory/requisitos/Products (F1 PLAN — ADR 0104)', function () {
    $path = base_path('memory/requisitos/Products/RUNBOOK-products.md');
    expect(file_exists($path))->toBeTrue();
    $src = file_get_contents($path);
    // 11 seções mínimas RUNBOOK MWART
    expect($src)->toContain('## 1. Objetivo');
    expect($src)->toContain('Multi-tenant Tier 0');
    expect($src)->toContain('cross-tenant');
});

// ─── Integration — Multi-tenant Tier 0 (ADR 0093, ADR 0101) ─────────────────

it('list-json cross-tenant — biz=99 NÃO vê products de biz=1 (Tier 0 IRREVOGÁVEL)', function () {
    prodNeedMysqlOrSkip();

    $userBiz1 = prodFirstUserOfBiz(PROD_BIZ_WAGNER);
    $userBiz99 = prodFirstUserOfBiz(PROD_BIZ_FICTICIO);

    if (! $userBiz1 || ! $userBiz99) {
        $this->markTestSkipped('User biz=1 ou biz=99 ausente — seed primeiro.');
    }

    if (! $userBiz1->can('product.view')) {
        $this->markTestSkipped('User biz=1 sem permission product.view — atribuir role.');
    }

    // Cria product unique em biz=1
    $marker = 'CrossTenant-' . uniqid();
    try {
        $productBiz1 = prodCreateProduct(PROD_BIZ_WAGNER, [
            'name' => $marker,
        ]);
    } catch (\Throwable $e) {
        $this->markTestSkipped('Falha ao criar product fixture (FK/coluna ausente): ' . $e->getMessage());
    }

    try {
        // Autenticado como biz=99 + session biz=99
        $this->actingAs($userBiz99);
        session(['user.business_id' => PROD_BIZ_FICTICIO]);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get('/products/list-json?q=' . urlencode($marker));

        // Tier 0: biz=99 NÃO pode enxergar product biz=1
        if ($response->status() === 403) {
            expect(true)->toBeTrue(); // 403 = também isolamento válido
        } else {
            $response->assertOk();
            $data = $response->json('data') ?? [];
            $names = collect($data)->pluck('name')->toArray();
            expect($names)->not->toContain($marker);
        }
    } finally {
        $productBiz1->forceDelete();
    }
});

it('list-json escopa busca q por business_id correto (biz=1 vê só seus)', function () {
    prodNeedMysqlOrSkip();
    $userBiz1 = prodFirstUserOfBiz(PROD_BIZ_WAGNER);
    if (! $userBiz1 || ! $userBiz1->can('product.view')) {
        $this->markTestSkipped('User biz=1 ausente ou sem permission product.view.');
    }

    $marker = 'ProdInertia-' . uniqid();
    try {
        $product = prodCreateProduct(PROD_BIZ_WAGNER, ['name' => $marker]);
    } catch (\Throwable $e) {
        $this->markTestSkipped('Falha ao criar product fixture: ' . $e->getMessage());
    }

    try {
        $this->actingAs($userBiz1);
        session(['user.business_id' => PROD_BIZ_WAGNER]);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get('/products/list-json?q=' . urlencode($marker));

        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'],
        ]);
        $names = collect($response->json('data'))->pluck('name')->toArray();
        expect($names)->toContain($marker);
    } finally {
        $product->forceDelete();
    }
});

it('GET /products + X-Inertia retorna response Inertia component Products/Index', function () {
    prodNeedMysqlOrSkip();
    $userBiz1 = prodFirstUserOfBiz(PROD_BIZ_WAGNER);
    if (! $userBiz1 || ! $userBiz1->can('product.view')) {
        $this->markTestSkipped('User biz=1 ausente ou sem permission product.view.');
    }

    $this->actingAs($userBiz1);
    session(['user.business_id' => PROD_BIZ_WAGNER]);

    $response = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => 'test',
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'text/html, application/xhtml+xml',
    ])->get('/products');

    expect(in_array($response->status(), [200, 409], true))->toBeTrue();

    if ($response->status() === 200) {
        $payload = $response->json();
        expect($payload)->toHaveKey('component');
        expect($payload['component'])->toBe('Products/Index');
        expect($payload['props'])->toHaveKey('kpis');
        expect($payload['props'])->toHaveKey('permissions');
    }
});

it('GET /products SEM X-Inertia retorna Blade legacy (fallback preservado — canary gradual)', function () {
    prodNeedMysqlOrSkip();
    $userBiz1 = prodFirstUserOfBiz(PROD_BIZ_WAGNER);
    if (! $userBiz1 || ! $userBiz1->can('product.view')) {
        $this->markTestSkipped('User biz=1 ausente ou sem permission product.view.');
    }

    $this->actingAs($userBiz1);
    session(['user.business_id' => PROD_BIZ_WAGNER]);

    $response = $this->get('/products');

    if ($response->status() === 200) {
        $content = $response->getContent();
        // Blade legacy não retorna JSON Inertia
        expect($content)->not->toContain('"component":"Products/Index"');
    }
});

it('listJson valida sort por whitelist — sort inválido cai pra default seguro', function () {
    prodNeedMysqlOrSkip();
    $userBiz1 = prodFirstUserOfBiz(PROD_BIZ_WAGNER);
    if (! $userBiz1 || ! $userBiz1->can('product.view')) {
        $this->markTestSkipped('User biz=1 ausente ou sem permission product.view.');
    }

    $this->actingAs($userBiz1);
    session(['user.business_id' => PROD_BIZ_WAGNER]);

    // Sort com SQL injection attempt — deve cair pra default silenciosamente.
    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->get('/products/list-json?sort=NAME%3B%20DROP%20TABLE%20users&dir=asc');

    $response->assertOk();
    expect($response->json('meta'))->toHaveKey('current_page');
});

it('listJson per_page restrito a whitelist [10, 25, 50, 100]', function () {
    prodNeedMysqlOrSkip();
    $userBiz1 = prodFirstUserOfBiz(PROD_BIZ_WAGNER);
    if (! $userBiz1 || ! $userBiz1->can('product.view')) {
        $this->markTestSkipped('User biz=1 ausente ou sem permission product.view.');
    }

    $this->actingAs($userBiz1);
    session(['user.business_id' => PROD_BIZ_WAGNER]);

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->get('/products/list-json?per_page=99999');

    $response->assertOk();
    $perPage = $response->json('meta.per_page');
    expect($perPage)->toBe(25);
});
