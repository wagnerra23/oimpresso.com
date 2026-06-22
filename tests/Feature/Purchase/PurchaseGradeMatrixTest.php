<?php

declare(strict_types=1);

use App\Product;
use App\Services\Purchase\GradeLayoutBuilder;
use App\Variation;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * US-COM-005 — grade tam×cor plugada em /purchases/create.
 *
 * Cobertura:
 *  - GradeLayoutBuilder (lógica pura, driver-agnostic): auto-detect 2D vs 1-eixo vs single.
 *  - Estrutural (CI, sem DB): rota + Tier 0 scope no gradeMatrix + hardening no store + wiring frontend.
 *  - Cross-tenant Tier 0 (synthetic-sqlite, espelha UpdateCrossTenantIdorTest): produto de outro
 *    business não resolve (404). Skipa em MySQL persistente (quarentena Onda 2 SDD).
 *
 * ADRs: 0093 (Tier 0 IRREVOGÁVEL), 0104 (MWART), 0105 (Larissa sinal), C1 (convergência).
 */
const GM_CONTROLLER = 'app/Http/Controllers/PurchaseController.php';
const GM_ROUTES = 'routes/web.php';
const GM_PAGE = 'resources/js/Pages/Purchase/Create.tsx';
const GM_COMPONENT = 'resources/js/Components/purchase/GradeMatrixInput.tsx';
const GM_COMBOBOX = 'resources/js/Components/purchase/GradeProductCombobox.tsx';

function gmVar(int $id, string $name): array
{
    return ['id' => $id, 'name' => $name];
}

// ─── GradeLayoutBuilder — auto-detect (lógica pura) ──────────────────────────

it('2D: nomes compostos "P/Preto" viram grade tam×cor', function () {
    $layout = (new GradeLayoutBuilder())->build('variable', [
        gmVar(11, 'P/Preto'), gmVar(12, 'M/Preto'), gmVar(13, 'P/Branco'),
    ]);

    expect($layout['mode'])->toBe('2d');
    expect(array_column($layout['rows'], 'label'))->toBe(['P', 'M']);
    expect(array_column($layout['cols'], 'label'))->toBe(['Preto', 'Branco']);
    expect($layout['cellVariationMap']['P__Preto'])->toBe(11);
    expect($layout['cellVariationMap']['P__Branco'])->toBe(13);
    // Célula esparsa (M/Branco não existe) → não mapeada (grade desabilita a célula).
    expect($layout['cellVariationMap'])->not->toHaveKey('M__Branco');
});

it('2D: hífen também monta a grade ("P-Preto")', function () {
    $layout = (new GradeLayoutBuilder())->build('variable', [
        gmVar(1, 'P-Preto'), gmVar(2, 'G-Preto'), gmVar(3, 'P-Azul'),
    ]);

    expect($layout['mode'])->toBe('2d');
    expect(array_column($layout['rows'], 'label'))->toBe(['P', 'G']);
});

it('1 eixo: nomes simples "P","M","G" caem pra matrix-1d (1 coluna Qtd)', function () {
    $layout = (new GradeLayoutBuilder())->build('variable', [
        gmVar(1, 'P'), gmVar(2, 'M'), gmVar(3, 'G'),
    ]);

    expect($layout['mode'])->toBe('matrix-1d');
    expect($layout['cols'])->toBe([['id' => 'qtd', 'label' => 'Qtd']]);
    expect($layout['cellVariationMap']['2__qtd'])->toBe(2);
});

it('single: produto não-variável vira input único', function () {
    $layout = (new GradeLayoutBuilder())->build('single', [gmVar(7, 'DUMMY')]);

    expect($layout['mode'])->toBe('single');
    expect($layout['cellVariationMap']['single__qtd'])->toBe(7);
});

it('ambíguo (combinação duplicada) cai pra 1 eixo — nunca grade quebrada', function () {
    $layout = (new GradeLayoutBuilder())->build('variable', [
        gmVar(1, 'P/Preto'), gmVar(2, 'P/Preto'),
    ]);

    expect($layout['mode'])->toBe('matrix-1d');
});

it('misto (uma variação sem delimitador) cai pra 1 eixo', function () {
    $layout = (new GradeLayoutBuilder())->build('variable', [
        gmVar(1, 'P/Preto'), gmVar(2, 'M'),
    ]);

    expect($layout['mode'])->toBe('matrix-1d');
});

// ─── Estrutural — Tier 0 + wiring (roda em CI sem DB) ────────────────────────

it('rota purchases.grade-matrix registrada', function () {
    $routes = file_get_contents(base_path(GM_ROUTES));
    expect($routes)->toContain("'/purchases/grade-matrix'");
    expect($routes)->toContain("'gradeMatrix'");
});

it('gradeMatrix resolve produto com scope business_id + firstOrFail (Tier 0 → 404 cross-tenant)', function () {
    $src = file_get_contents(base_path(GM_CONTROLLER));
    expect($src)->toContain('public function gradeMatrix');
    expect($src)->toMatch("/Product::where\\('business_id', \\\$business_id\\)\\s*->where\\('id', \\\$product_id\\)\\s*->firstOrFail\\(\\)/");
    // Canon UPOS — session, NÃO auth()->user()->business_id (T-AP-8).
    expect($src)->toContain("session()->get('user.business_id')");
    expect($src)->toContain('GradeLayoutBuilder');
});

it('store() valida ownership Tier 0 das variations (anti payload forjado cross-tenant)', function () {
    $src = file_get_contents(base_path(GM_CONTROLLER));
    expect($src)->toContain('assertPurchaseVariationsOwnership');
    expect($src)->toContain("whereHas('product'");
    expect($src)->toMatch('/abort_if\\(/');
    expect($src)->toContain('422');
});

it('Create.tsx pluga o modo grade (imports + fetch grade-matrix + expande células)', function () {
    $src = file_get_contents(base_path(GM_PAGE));
    expect($src)->toContain('@/Components/purchase/GradeMatrixInput');
    expect($src)->toContain('@/Components/purchase/GradeProductCombobox');
    expect($src)->toContain('/purchases/grade-matrix');
    expect($src)->toContain('adicionarLinhasGrade');
    expect($src)->toContain('purchase_line_tax_id');
});

it('Create.tsx preserva o fluxo manual (invariantes do Wave2)', function () {
    $src = file_get_contents(base_path(GM_PAGE));
    foreach (['Itens da compra', 'adicionarLinhaVazia', 'removerLinha', "form.post('/purchases'"] as $needle) {
        expect($src)->toContain($needle);
    }
});

it('componentes da grade existem em Components/purchase', function () {
    expect(file_exists(base_path(GM_COMPONENT)))->toBeTrue();
    expect(file_exists(base_path(GM_COMBOBOX)))->toBeTrue();
});

// ─── Cross-tenant Tier 0 (synthetic-sqlite) ──────────────────────────────────

describe('Tier 0 cross-tenant (synthetic sqlite)', function () {
    beforeEach(function () {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            test()->markTestSkipped('synthetic-sqlite — quarentena Onda 2 SDD (MySQL persistente).');
        }

        Schema::create('products', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('business_id')->unsigned();
            $t->string('name');
            $t->string('type')->default('variable');
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('variations', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('product_id')->unsigned();
            $t->string('name')->nullable();
            $t->decimal('default_purchase_price', 22, 4)->default(0);
            $t->timestamps();
            $t->softDeletes();
        });
    });

    afterEach(function () {
        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::dropIfExists('variations');
            Schema::dropIfExists('products');
        }
    });

    it('usuário biz=1 NÃO resolve produto de biz=99 (404 ModelNotFoundException)', function () {
        $vitimaId = DB::table('products')->insertGetId([
            'business_id' => 99, 'name' => 'Camiseta vítima', 'type' => 'variable',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $business_id = 1; // atacante (NUNCA biz=4 — feedback test biz=1)

        // Padrão EXATO que PurchaseController@gradeMatrix usa.
        expect(fn () => Product::where('business_id', $business_id)
            ->where('id', $vitimaId)
            ->firstOrFail()
        )->toThrow(ModelNotFoundException::class);
    });

    it('same-tenant biz=1 resolve o próprio produto e monta a grade 2D', function () {
        $prodId = DB::table('products')->insertGetId([
            'business_id' => 1, 'name' => 'Camiseta', 'type' => 'variable',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (['P/Preto', 'M/Preto', 'P/Branco'] as $nome) {
            DB::table('variations')->insert([
                'product_id' => $prodId, 'name' => $nome, 'default_purchase_price' => 22.5,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $product = Product::where('business_id', 1)->where('id', $prodId)->firstOrFail();
        expect($product->id)->toBe($prodId);

        $vars = Variation::where('product_id', $prodId)->orderBy('id')->get()
            ->map(fn ($v) => ['id' => (int) $v->id, 'name' => (string) $v->name])->all();
        $layout = (new GradeLayoutBuilder())->build((string) $product->type, $vars);

        expect($layout['mode'])->toBe('2d');
        expect(count($layout['cellVariationMap']))->toBe(3);
    });
});
