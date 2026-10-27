<?php

declare(strict_types=1);
// @covers-us US-WOO-007

use App\Utils\ModuleUtil;
use App\VariationLocationDetails;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Modules\Woocommerce\Jobs\EnviarEstoqueWooJob;
use Modules\Woocommerce\Utils\WoocommerceUtil;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato do estoque que volta para a loja WooCommerce (US-WOO-007).
 *
 * Regra mestre de estoque: este fluxo só LÊ o estoque do ERP. O valor enviado é provado por dois
 * caminhos — (a) a linha de `variation_location_details` lida direto do banco e (b) a conta à mão
 * da fixture (7,5 unidades com 2 casas = "7.50").
 *
 * A Woo é falsa (o cliente Automattic não passa pelo Http do Laravel): o `woo_client` do util é
 * trocado por um objeto que só registra o PUT. Tenant 98 contra 99 (ADR 0358). SKIP em SQLite.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasColumn('products', 'woocommerce_product_id')) {
        $this->markTestSkipped('Precisa do schema MySQL do UltimatePOS com as colunas do WooCommerce (ADR 0358).');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->loc98 = (int) DB::table('business_locations')->where('business_id', $this->b98)->value('id');
    if ($this->loc98 === 0) {
        $this->markTestSkipped('Tenant 98 sem local de venda no seed.');
    }

    $GLOBALS['woo_pacote'] = [$this->b98, $this->b99];
    $mu = Mockery::mock(ModuleUtil::class)->makePartial();
    $mu->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($biz, $perm) => $perm === 'woocommerce_module' && in_array((int) $biz, $GLOBALS['woo_pacote'], true));
    app()->instance(ModuleUtil::class, $mu);

    $GLOBALS['woo_puts'] = [];
    $util = Mockery::mock(WoocommerceUtil::class)->makePartial();
    $util->shouldReceive('woo_client')->andReturnUsing(fn ($biz) => new class((int) $biz) {
        public function __construct(private int $biz) {}

        public function put(string $endpoint, array $dados): array
        {
            $GLOBALS['woo_puts'][] = ['biz' => $this->biz, 'endpoint' => $endpoint, 'dados' => $dados];

            return [];
        }
    });
    app()->instance(WoocommerceUtil::class, $util);

    DB::table('business')->where('id', $this->b98)->update([
        'quantity_precision' => 2,
        'woocommerce_api_settings' => json_encode(['location_id' => $this->loc98, 'woocommerce_app_url' => 'https://loja-98.example']),
    ]);
});

/** Produto simples com estoque, ligado à Woo, com $qtd no local de venda dado. */
function wooProduto(int $biz, ?int $loc, float $qtd, ?int $wooId = 555, int $disable = 0): array
{
    $user = (int) DB::table('users')->where('business_id', $biz)->value('id');
    $unit = (int) DB::table('units')->insertGetId([
        'business_id' => $biz, 'actual_name' => 'Unidade', 'short_name' => 'un', 'allow_decimal' => 1,
        'created_by' => $user, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $prod = (int) DB::table('products')->insertGetId([
        'name' => 'Produto Woo '.uniqid(), 'business_id' => $biz, 'unit_id' => $unit, 'type' => 'single',
        'tax_type' => 'inclusive', 'sku' => 'SKU-'.uniqid(), 'barcode_type' => 'C128', 'enable_stock' => 1,
        'woocommerce_product_id' => $wooId, 'woocommerce_disable_sync' => $disable,
        'created_by' => $user, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $pv = (int) DB::table('product_variations')->insertGetId([
        'name' => 'DUMMY', 'product_id' => $prod, 'is_dummy' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $var = (int) DB::table('variations')->insertGetId([
        'name' => 'DUMMY', 'product_id' => $prod, 'sub_sku' => 'SKU-'.uniqid(), 'product_variation_id' => $pv,
        'default_purchase_price' => 1, 'dpp_inc_tax' => 1, 'profit_percent' => 0, 'default_sell_price' => 1,
        'sell_price_inc_tax' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    if ($loc !== null) {
        DB::table('variation_location_details')->insert([
            'product_id' => $prod, 'product_variation_id' => $pv, 'variation_id' => $var, 'location_id' => $loc,
            'qty_available' => $qtd, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return ['produto' => $prod, 'variacao' => $var];
}

it('US-WOO-007 · o estoque enviado é o do local da Woo, provado por dois caminhos', function () {
    $p = wooProduto($this->b98, $this->loc98, 7.5);

    $lidoDoBanco = (float) DB::table('variation_location_details')->where('product_id', $p['produto'])->where('location_id', $this->loc98)->value('qty_available');

    expect(EnviarEstoqueWooJob::quantidadeParaWoo($this->b98, $p['produto']))
        ->toBe(number_format($lidoDoBanco, 2, '.', ''))
        ->toBe('7.50');
});

it('US-WOO-007 · o job manda só manage_stock e stock_quantity para o produto da Woo', function () {
    $p = wooProduto($this->b98, $this->loc98, 3);

    app()->call([new EnviarEstoqueWooJob($this->b98, $p['produto']), 'handle']);

    expect($GLOBALS['woo_puts'])->toBe([
        ['biz' => $this->b98, 'endpoint' => 'products/555', 'dados' => ['manage_stock' => true, 'stock_quantity' => '3.00']],
    ]);
});

it('US-WOO-007 · não envia sem pacote, sem local configurado, desligado no produto ou de outra empresa', function () {
    $p = wooProduto($this->b98, $this->loc98, 3);
    $semSync = wooProduto($this->b98, $this->loc98, 3, 556, 1);
    $p99 = wooProduto($this->b99, null, 0, 557);

    expect(EnviarEstoqueWooJob::quantidadeParaWoo($this->b98, $semSync['produto']))->toBeNull()
        ->and(EnviarEstoqueWooJob::quantidadeParaWoo($this->b98, $p99['produto']))->toBeNull();

    $GLOBALS['woo_pacote'] = [$this->b99];
    expect(EnviarEstoqueWooJob::quantidadeParaWoo($this->b98, $p['produto']))->toBeNull();

    $GLOBALS['woo_pacote'] = [$this->b98];
    DB::table('business')->where('id', $this->b98)->update(['woocommerce_api_settings' => json_encode(['location_id' => null])]);
    expect(EnviarEstoqueWooJob::quantidadeParaWoo($this->b98, $p['produto']))->toBeNull();

    app()->call([new EnviarEstoqueWooJob($this->b98, $p['produto']), 'handle']);
    expect($GLOBALS['woo_puts'])->toBe([]);
});

it('US-WOO-007 · baixa de estoque de produto ligado à Woo agenda o envio; produto sem Woo não agenda', function () {
    $ligado = wooProduto($this->b98, $this->loc98, 10);
    $solto = wooProduto($this->b98, $this->loc98, 10, null);
    Queue::fake();

    VariationLocationDetails::query()->where('product_id', $solto['produto'])->first()->decrement('qty_available', 2);
    Queue::assertNothingPushed();

    VariationLocationDetails::query()->where('product_id', $ligado['produto'])->first()->decrement('qty_available', 2);
    Queue::assertPushed(EnviarEstoqueWooJob::class, fn ($job) => $job->businessId === $this->b98 && $job->productId === $ligado['produto']);
});
