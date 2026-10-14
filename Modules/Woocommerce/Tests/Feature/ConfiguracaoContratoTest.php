<?php

declare(strict_types=1);
// Cobre UC-WOOC-01, UC-WOOC-02, UC-WOOC-03, UC-WOOC-04, UC-WOOC-05 (Woocommerce/Configuracao/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Thread woocommerce/playbook/02, F3 — /woocommerce/api-settings em Inertia atrás da flag useV2WoocommercePainel.
 *
 * A flag é ligada pelo override de ambiente (`feature-flags.forced_on`, inerte em produção). O acesso vem da permissão
 * `superadmin` (WoocommerceAuthorizationService). A Blade e o salvar de hoje estão travados em ConfiguracaoBaselineTest.
 * Tenant de teste 98 x cliente fictício 99 (ADR 0358).
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasColumn('business', 'woocommerce_api_settings')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['superadmin'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

function woocContrGet($teste, string $url = '/woocommerce/api-settings'): \Illuminate\Testing\TestResponse
{
    return $teste->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $teste->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest',
    ])->get($url);
}

/** Os 6 segredos com valores aleatórios, salvos no negócio. */
function woocContrSegredos(int $businessId): array
{
    $s = [];
    foreach (['woocommerce_consumer_key' => 'ck_', 'woocommerce_consumer_secret' => 'cs_', 'woocommerce_wh_oc_secret' => 'oc_',
        'woocommerce_wh_ou_secret' => 'ou_', 'woocommerce_wh_od_secret' => 'od_', 'woocommerce_wh_or_secret' => 'or_'] as $k => $p) {
        $s[$k] = $p.bin2hex(random_bytes(8));
    }
    DB::table('business')->where('id', $businessId)->update([
        'woocommerce_api_settings' => json_encode([
            'woocommerce_app_url' => 'https://loja.exemplo.test',
            'woocommerce_consumer_key' => $s['woocommerce_consumer_key'], 'woocommerce_consumer_secret' => $s['woocommerce_consumer_secret'],
            'woocommerce_wh_oc_secret' => $s['woocommerce_wh_oc_secret'],
        ]),
        'woocommerce_wh_oc_secret' => $s['woocommerce_wh_oc_secret'], 'woocommerce_wh_ou_secret' => $s['woocommerce_wh_ou_secret'],
        'woocommerce_wh_od_secret' => $s['woocommerce_wh_od_secret'], 'woocommerce_wh_or_secret' => $s['woocommerce_wh_or_secret'],
    ]);

    return $s;
}

function woocContrFlagDesligada(): void
{
    app()->instance(\App\Services\FeatureFlagService::class, new class extends \App\Services\FeatureFlagService
    {
        public function isOn(string $flag, array $attrs = []): bool
        {
            return $flag === 'useV2WoocommercePainel' ? false : parent::isOn($flag, $attrs);
        }
    });
}

test('UC-WOOC-01 com a flag ligada, GET /woocommerce/api-settings renderiza Inertia', function () {
    config(['feature-flags.forced_on' => 'useV2WoocommercePainel']);

    $r = woocContrGet($this);

    $r->assertOk();
    expect($r->json('component'))->toBe('Woocommerce/Configuracao/Index');
});

test('UC-WOOC-02 com a flag desligada, ou com ?classico=1, a configuração segue na Blade', function () {
    woocContrFlagDesligada();
    $this->get('/woocommerce/api-settings')->assertOk()->assertViewIs('woocommerce::woocommerce.api_settings');

    app()->forgetInstance(\App\Services\FeatureFlagService::class);
    config(['feature-flags.forced_on' => 'useV2WoocommercePainel']);
    $this->get('/woocommerce/api-settings?classico=1')->assertOk()->assertViewIs('woocommerce::woocommerce.api_settings');
});

test('UC-WOOC-03 Tier 0 — nenhum dos 6 segredos chega à página, só se está preenchido', function () {
    config(['feature-flags.forced_on' => 'useV2WoocommercePainel']);
    $segredos = woocContrSegredos($this->business->id);

    $r = woocContrGet($this);
    $r->assertOk();

    foreach ($segredos as $campo => $valor) {
        expect($r->getContent())->not->toContain($valor);
        expect($r->json("props.segredos.$campo"))->toBeTrue();
    }
    expect($r->json('props.config.woocommerce_app_url'))->toBe('https://loja.exemplo.test');
    expect(array_keys((array) $r->json('props.config')))->not->toContain('woocommerce_consumer_key');
});

test('UC-WOOC-04 Tier 0 — salvar pela tela nova mantém o segredo deixado em branco e troca o escrito', function () {
    $outro = $this->seededSupportClientTenant();
    $antesDoOutro = DB::table('business')->where('id', $outro->id)->value('woocommerce_api_settings');
    $segredos = woocContrSegredos($this->business->id);

    $this->post('/woocommerce/update-api-settings', [
        'manter_segredos' => '1',
        'woocommerce_app_url' => 'https://loja.exemplo.test', 'woocommerce_consumer_key' => 'ck_trocada',
        'woocommerce_consumer_secret' => '', 'location_id' => '', 'default_tax_class' => '', 'product_tax_type' => 'inc',
        'default_selling_price_group' => '', 'sync_description_as' => 'long', 'product_fields_for_create' => ['category'],
        'product_fields_for_update' => ['name'], 'woocommerce_wh_oc_secret' => '', 'woocommerce_wh_ou_secret' => 'ou_trocado',
        'woocommerce_wh_od_secret' => '', 'woocommerce_wh_or_secret' => '',
    ])->assertRedirect();

    $meu = DB::table('business')->where('id', $this->business->id)->first();
    $json = json_decode($meu->woocommerce_api_settings, true);
    expect($json['woocommerce_consumer_key'])->toBe('ck_trocada');
    expect($json['woocommerce_consumer_secret'])->toBe($segredos['woocommerce_consumer_secret']);
    expect(array_key_exists('manter_segredos', $json))->toBeFalse();
    expect($meu->woocommerce_wh_oc_secret)->toBe($segredos['woocommerce_wh_oc_secret']);
    expect($meu->woocommerce_wh_ou_secret)->toBe('ou_trocado');
    expect($meu->woocommerce_wh_od_secret)->toBe($segredos['woocommerce_wh_od_secret']);
    expect($meu->woocommerce_wh_or_secret)->toBe($segredos['woocommerce_wh_or_secret']);
    expect(DB::table('business')->where('id', $outro->id)->value('woocommerce_api_settings'))->toBe($antesDoOutro);
});

test('UC-WOOC-05 Tier 0 — só os grupos de preço do meu negócio', function () {
    config(['feature-flags.forced_on' => 'useV2WoocommercePainel']);
    $outro = $this->seededSupportClientTenant();
    $meu = DB::table('selling_price_groups')->insertGetId(['name' => 'WOOC '.uniqid(), 'business_id' => $this->business->id, 'created_at' => now(), 'updated_at' => now()]);
    $alheio = DB::table('selling_price_groups')->insertGetId(['name' => 'WOOC '.uniqid(), 'business_id' => $outro->id, 'created_at' => now(), 'updated_at' => now()]);

    $ids = collect(woocContrGet($this)->json('props.opcoes.grupos_preco'))->pluck('id')->all();

    expect($ids)->toContain((string) $meu);
    expect(in_array((string) $alheio, $ids, true))->toBeFalse();
});
