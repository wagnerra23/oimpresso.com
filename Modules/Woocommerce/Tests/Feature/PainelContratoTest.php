<?php

declare(strict_types=1);
// Cobre UC-WOOP-01, UC-WOOP-02, UC-WOOP-03, UC-WOOP-04, UC-WOOP-05, UC-WOOP-06, UC-WOOP-07, UC-WOOP-08 (Woocommerce/Painel/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Thread woocommerce/playbook/01, F3 — /woocommerce em Inertia atrás da flag useV2WoocommercePainel.
 *
 * A flag é ligada pelo override de ambiente (`feature-flags.forced_on`, inerte em produção). O acesso ao módulo vem da
 * permissão `superadmin` (WoocommerceAuthorizationService). A Blade está travada em PainelBaselineTest.
 * Tenant de teste 98 x cliente fictício 99 (ADR 0358).
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasColumn('products', 'woocommerce_product_id')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['superadmin'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

/** Nome com prefixo que ordena primeiro: a lista vem 50 por página, por nome. */
function woopContrProduto(int $businessId, int $criador, ?int $idNaLoja, int $foraDaFila = 0, ?string $alteradoEm = null): int
{
    return DB::table('products')->insertGetId([
        'name' => '0000 WOOP '.uniqid(), 'business_id' => $businessId, 'type' => 'single', 'tax_type' => 'inclusive',
        'sku' => 'WOOP-'.uniqid(), 'created_by' => $criador, 'woocommerce_product_id' => $idNaLoja,
        'woocommerce_disable_sync' => $foraDaFila, 'created_at' => now()->subDay(), 'updated_at' => $alteradoEm ?? now()->subHours(3),
    ]);
}

function woopContrGet($teste, array $extra = [], string $url = '/woocommerce'): \Illuminate\Testing\TestResponse
{
    return $teste->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $teste->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest',
    ] + $extra)->get($url);
}

test('UC-WOOP-01 com a flag ligada, GET /woocommerce renderiza Inertia', function () {
    config(['feature-flags.forced_on' => 'useV2WoocommercePainel']);

    $r = woopContrGet($this);

    $r->assertOk();
    expect($r->json('component'))->toBe('Woocommerce/Painel/Index');
});

test('UC-WOOP-02 com a flag desligada, ou com ?classico=1, GET /woocommerce segue na Blade', function () {
    app()->instance(\App\Services\FeatureFlagService::class, new class extends \App\Services\FeatureFlagService
    {
        public function isOn(string $flag, array $attrs = []): bool
        {
            return $flag === 'useV2WoocommercePainel' ? false : parent::isOn($flag, $attrs);
        }
    });
    $this->get('/woocommerce')->assertOk()->assertViewIs('woocommerce::woocommerce.index');

    app()->forgetInstance(\App\Services\FeatureFlagService::class);
    config(['feature-flags.forced_on' => 'useV2WoocommercePainel']);
    $this->get('/woocommerce?classico=1')->assertOk()->assertViewIs('woocommerce::woocommerce.index');
});

test('UC-WOOP-03 Tier 0 — meus produtos com a situação certa e o mesmo número da Blade', function () {
    config(['feature-flags.forced_on' => 'useV2WoocommercePainel']);
    $outro = $this->seededSupportClientTenant();
    DB::table('woocommerce_sync_logs')->insert([
        'business_id' => $this->business->id, 'sync_type' => 'all_products', 'operation_type' => 'created',
        'created_by' => $this->user->id, 'created_at' => now()->subHour(), 'updated_at' => now()->subHour(),
    ]);
    $pendente = woopContrProduto($this->business->id, $this->user->id, null);
    $sincronizado = woopContrProduto($this->business->id, $this->user->id, 501);
    $fora = woopContrProduto($this->business->id, $this->user->id, null, 1);
    $alterado = woopContrProduto($this->business->id, $this->user->id, 502, 0, now()->toDateTimeString());
    $alheio = woopContrProduto($outro->id, $this->user->id, null);

    $r = woopContrGet($this, ['X-Inertia-Partial-Component' => 'Woocommerce/Painel/Index', 'X-Inertia-Partial-Data' => 'resumo,produtos']);
    $r->assertOk();
    $itens = collect($r->json('props.produtos.itens'))->keyBy('id');

    expect($itens->has($alheio))->toBeFalse();
    expect([$itens[$pendente]['situacao'], $itens[$sincronizado]['situacao'], $itens[$fora]['situacao'], $itens[$alterado]['situacao']])
        ->toBe(['pendente', 'sincronizado', 'fora_da_fila', 'alterado']);
    expect($itens[$sincronizado]['id_na_loja'])->toEqual(501);

    // Dois caminhos: a contagem direta no banco e o alerta que a Blade monta com o mesmo número.
    $direto = DB::table('products')->where('business_id', $this->business->id)->whereIn('type', ['single', 'variable'])
        ->whereNull('woocommerce_product_id')->where('woocommerce_disable_sync', 0)->count();
    expect($r->json('props.resumo.produtos_pendentes'))->toBe($direto);

    config(['feature-flags.forced_on' => '']);
    $this->flushHeaders(); // withHeaders() persiste no teste: o pedido da Blade não pode herdar o X-Inertia parcial.
    app()->instance(\App\Services\FeatureFlagService::class, new class extends \App\Services\FeatureFlagService
    {
        public function isOn(string $flag, array $attrs = []): bool
        {
            return $flag === 'useV2WoocommercePainel' ? false : parent::isOn($flag, $attrs);
        }
    });
    $alerta = $this->get('/woocommerce')->viewData('alerts')['not_synced_product'] ?? null;
    expect($alerta)->toBe($direto == 1
        ? __('woocommerce::lang.one_product_not_sync_alert')
        : __('woocommerce::lang.product_not_sync_alert', ['count' => $direto]));
});

test('UC-WOOP-04 Tier 0 — as chaves da loja nunca chegam à página', function () {
    config(['feature-flags.forced_on' => 'useV2WoocommercePainel']);
    $chave = 'ck_'.bin2hex(random_bytes(8));
    $segredo = 'cs_'.bin2hex(random_bytes(8));
    DB::table('business')->where('id', $this->business->id)->update(['woocommerce_api_settings' => json_encode([
        'woocommerce_app_url' => 'https://loja.exemplo.test', 'woocommerce_consumer_key' => $chave, 'woocommerce_consumer_secret' => $segredo,
    ])]);

    $r = woopContrGet($this);
    $r->assertOk();
    expect($r->json('props.configurado'))->toBeTrue();
    expect($r->getContent())->not->toContain($chave);
    expect($r->getContent())->not->toContain($segredo);

    $parcial = woopContrGet($this, ['X-Inertia-Partial-Component' => 'Woocommerce/Painel/Index', 'X-Inertia-Partial-Data' => 'resumo,produtos']);
    expect($parcial->getContent())->not->toContain($chave);
    expect($parcial->getContent())->not->toContain($segredo);
});

test('UC-WOOP-05 cada bloco de sincronização aparece só para quem tem a permissão dele', function () {
    config(['feature-flags.forced_on' => 'useV2WoocommercePainel']);
    $mu = Mockery::mock(\App\Utils\ModuleUtil::class)->makePartial();
    $mu->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
    app()->instance(\App\Utils\ModuleUtil::class, $mu);
    $soPedidos = $this->usuarioComPermissoes(['woocommerce.sync_orders'], $this->business);
    $this->actingAs($soPedidos);
    session(['user.id' => $soPedidos->id]);

    $r = woopContrGet($this);

    $r->assertOk();
    expect($r->json('props.pode'))->toMatchArray([
        'pedidos' => true, 'categorias' => false, 'sincronizar' => false, 'configurar' => false, 'impostos' => false,
    ]);
    // As datas da última sincronização vêm adiadas, como resumo e produtos.
    expect($r->json('deferredProps.default'))->toContain('ultimas');
});

test('UC-WOOP-06 Tier 0 — reiniciar categorias pela tela nova só limpa o vínculo do meu negócio', function () {
    $outro = $this->seededSupportClientTenant();
    $minha = DB::table('categories')->insertGetId([
        'name' => 'WOOP cat '.uniqid(), 'business_id' => $this->business->id, 'parent_id' => 0, 'created_by' => $this->user->id,
        'category_type' => 'product', 'woocommerce_cat_id' => 901, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $alheia = DB::table('categories')->insertGetId([
        'name' => 'WOOP cat '.uniqid(), 'business_id' => $outro->id, 'parent_id' => 0, 'created_by' => $this->user->id,
        'category_type' => 'product', 'woocommerce_cat_id' => 902, 'created_at' => now(), 'updated_at' => now(),
    ]);

    // Mesma chamada que SincronizarLoja.tsx faz: GET com X-Requested-With, resposta JSON.
    $r = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])->get('/woocommerce/reset-categories');

    $r->assertOk();
    expect((bool) $r->json('success'))->toBeTrue();
    expect(DB::table('categories')->where('id', $minha)->value('woocommerce_cat_id'))->toBeNull();
    expect((int) DB::table('categories')->where('id', $alheia)->value('woocommerce_cat_id'))->toBe(902);
});

function woopTaxa(int $businessId, int $criador, string $nome, ?int $idNaLoja): int
{
    return DB::table('tax_rates')->insertGetId([
        'business_id' => $businessId, 'name' => $nome, 'amount' => 17, 'is_tax_group' => 0, 'for_tax_group' => 0,
        'created_by' => $criador, 'woocommerce_tax_rate_id' => $idNaLoja, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

test('UC-WOOP-07 Tier 0 — o mapa de impostos traz só as taxas do meu negócio, e diz quando a loja não respondeu', function () {
    $outro = $this->seededSupportClientTenant();
    $minha = woopTaxa($this->business->id, $this->user->id, 'WOOP ICMS '.uniqid(), 7);
    $alheia = woopTaxa($outro->id, $this->user->id, 'WOOP alheia '.uniqid(), 8);

    $r = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])->get('/woocommerce/tax-rates-map');

    $r->assertOk();
    $ids = collect($r->json('erp'))->pluck('id')->all();
    expect($ids)->toContain($minha);
    expect($ids)->not->toContain($alheia);
    expect(collect($r->json('erp'))->firstWhere('id', $minha)['loja_id'])->toBe(7);
    // Sem loja configurada no teste, a API não responde: a tela avisa em vez de mostrar um mapa vazio.
    expect($r->json('loja_respondeu'))->toBeFalse();
});

test('UC-WOOP-08 Tier 0 — gravar o mapa como a tela nova grava não toca taxa de outro negócio', function () {
    $outro = $this->seededSupportClientTenant();
    $minha = woopTaxa($this->business->id, $this->user->id, 'WOOP ICMS '.uniqid(), 7);
    $alheia = woopTaxa($outro->id, $this->user->id, 'WOOP alheia '.uniqid(), 8);

    // Mesmo corpo que MapaImpostos.tsx manda: taxes[id_do_erp] = id_na_loja ('' = nenhuma).
    $this->post('/woocommerce/map-taxrates', ['taxes' => [$minha => '', $alheia => '99']])->assertRedirect();

    expect(DB::table('tax_rates')->where('id', $minha)->value('woocommerce_tax_rate_id'))->toBeNull();
    expect((int) DB::table('tax_rates')->where('id', $alheia)->value('woocommerce_tax_rate_id'))->toBe(8);
});
