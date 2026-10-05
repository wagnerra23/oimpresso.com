<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Busca por sub_sku fica dentro do negócio — playbook Produto · thread 09.
 *
 * O import de preço (SellingPriceGroupController::import) buscava o SKU no banco inteiro e foi
 * consertado em 2026-10-01. A thread 09 conferiu as outras 5 buscas `Variation::where('sub_sku', …)`
 * no main: TODAS já filtram por `products.business_id`. Duas já tinham teste de outro negócio
 * (ProdutoImportacaoContratoTest UC-PIMP-10 · ImportSalesContratoTest UC-IMPV-04). Este arquivo
 * TRAVA as duas que não tinham, sem mudar código de produção:
 *
 *   - ProductController::validateVaritionSkus  (POST /products/validate_variation_skus)
 *   - PurchaseController::importPurchaseProducts (POST /import-purchase-products)
 *
 * Cada caso tem controle positivo (SKU do próprio negócio É encontrado), para que o verde não
 * seja vácuo: tirar o filtro de business_id faz o caso negativo cair.
 *
 * Fora daqui: ProductController::checkProductSku termina em `echo …; exit;` — um teste HTTP mata o
 * runner. A busca dele (join products + where business_id) foi conferida por leitura.
 *
 * ⛔ Tenant 98 contra o cliente fictício 99 (ADR 0358). NUNCA biz=4.
 * ⚠️ SKIP sem schema MySQL: leia assertions, não "0 failed" (LC-13).
 */
uses(DatabaseTransactions::class);

function skuEscUsuario(int $bizId, array $permissoes = []): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'SubSku T09', 'username' => 'skuesc_' . uniqid(), 'password' => bcrypt('ci'),
        'business_id' => $bizId, 'user_type' => 'user', 'allow_login' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $user = User::findOrFail($id);
    foreach ($permissoes as $p) {
        $user->givePermissionTo(Permission::findOrCreate($p, 'web'));
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function skuEscLogin(object $test, User $user): object
{
    session([
        'user.business_id' => (int) $user->business_id, 'user.id' => $user->id,
        'financial_year.start' => '2026-01-01',
        'currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);

    return $test->actingAs($user);
}

/** Produto single com a variação renomeada para o sub_sku pedido. Devolve o variation_id. */
function skuEscVariacao(int $bizId, string $subSku): int
{
    $p = EstoqueFixture::singleProduct($bizId);
    DB::table('variations')->where('id', $p->variationId())->update(['sub_sku' => $subSku]);

    return (int) $p->variationId();
}

/** Planilha de compra: sku, quantidade (cabeçalho na 1ª linha). */
function skuEscPlanilhaCompra(string $sku): UploadedFile
{
    return UploadedFile::fake()->createWithContent('compra.csv', "sku,qtd\n{$sku},3\n");
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente — roda na lane MySQL / CT 100.');
    }
    $this->biz = $this->seededTenant();
    $this->vizinho = $this->seededSupportClientTenant();
    expect($this->biz->id)->not->toBe($this->vizinho->id);
});

it('T09 · validate_variation_skus não enxerga o sub_sku de outro negócio; o do próprio negócio é acusado [T0]', function () {
    skuEscVariacao($this->vizinho->id, 'SKU09-VIZ');
    skuEscVariacao($this->biz->id, 'SKU09-MEU');
    $user = skuEscUsuario($this->biz->id);

    // SKU que só existe no vizinho: livre para este negócio.
    skuEscLogin($this, $user)->post('/products/validate_variation_skus', [
        'skus' => [['sku' => 'SKU09-VIZ', 'variation_id' => '']],
    ])->assertOk()->assertExactJson(['success' => 1]);

    // Controle positivo: o SKU do próprio negócio É encontrado.
    skuEscLogin($this, $user)->post('/products/validate_variation_skus', [
        'skus' => [['sku' => 'SKU09-MEU', 'variation_id' => '']],
    ])->assertOk()->assertExactJson(['success' => 0, 'sku' => 'SKU09-MEU']);
});

it('T09 · import-purchase-products recusa SKU de outro negócio com a linha e nada é gravado [T0]', function () {
    $varViz = skuEscVariacao($this->vizinho->id, 'SKU09-CVIZ');
    skuEscVariacao($this->biz->id, 'SKU09-CMEU');
    EstoqueFixture::unitId($this->biz->id);
    $local = EstoqueFixture::locationId($this->biz->id, '-SKU09');
    $user = skuEscUsuario($this->biz->id, ['purchase.create']);
    $linhasAntes = DB::table('purchase_lines')->where('variation_id', $varViz)->count();

    $naoAchado = __('lang_v1.product_not_found_exception', ['row' => 1, 'sku' => 'SKU09-CVIZ']);
    $r = skuEscLogin($this, $user)->post('/import-purchase-products', [
        'file' => skuEscPlanilhaCompra('SKU09-CVIZ'), 'location_id' => $local, 'row_count' => 0,
    ])->assertOk()->json();
    expect($r['success'])->toBeFalse();
    expect($r['msg'])->toBe($naoAchado);

    // Controle positivo: o SKU do próprio negócio passa da busca (não cai no "não encontrado").
    $r2 = skuEscLogin($this, $user)->post('/import-purchase-products', [
        'file' => skuEscPlanilhaCompra('SKU09-CMEU'), 'location_id' => $local, 'row_count' => 0,
    ])->assertOk()->json();
    expect($r2['msg'] ?? '')->not->toBe(__('lang_v1.product_not_found_exception', ['row' => 1, 'sku' => 'SKU09-CMEU']));

    expect(DB::table('purchase_lines')->where('variation_id', $varViz)->count())->toBe($linhasAntes);
});
