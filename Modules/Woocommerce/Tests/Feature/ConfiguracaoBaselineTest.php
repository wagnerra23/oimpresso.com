<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Thread woocommerce/playbook/02, F2 — o que a Blade da configuração (/woocommerce/api-settings) e o salvar
 * (POST /woocommerce/update-api-settings) fazem HOJE, antes da tela React.
 * Tenant 98 × 99 (ADR 0358).
 *
 * @see memory/requisitos/Woocommerce/RUNBOOK-configuracao.md
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasColumn('business', 'woocommerce_api_settings')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['superadmin'], $this->business);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
    app()->instance(\App\Services\FeatureFlagService::class, new class extends \App\Services\FeatureFlagService
    {
        public function isOn(string $flag, array $attrs = []): bool
        {
            return $flag === 'useV2WoocommercePainel' ? false : parent::isOn($flag, $attrs);
        }
    });
});

function woocfgBaseGrupo(int $businessId): int
{
    return DB::table('selling_price_groups')->insertGetId([
        'name' => 'WOOCFG '.uniqid(), 'business_id' => $businessId, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** O formulário inteiro, como a Blade posta (os 4 segredos de webhook sempre vão, mesmo vazios). */
function woocfgBasePayload(array $sobrescreve = []): array
{
    return $sobrescreve + [
        'woocommerce_app_url' => 'https://loja.exemplo.test', 'woocommerce_consumer_key' => 'ck_novo',
        'woocommerce_consumer_secret' => 'cs_novo', 'location_id' => '', 'default_tax_class' => '',
        'product_tax_type' => 'inc', 'default_selling_price_group' => '', 'sync_description_as' => 'long',
        'product_fields_for_create' => ['category'], 'product_fields_for_update' => ['name', 'price'],
        'woocommerce_wh_oc_secret' => 'wh_oc', 'woocommerce_wh_ou_secret' => 'wh_ou',
        'woocommerce_wh_od_secret' => 'wh_od', 'woocommerce_wh_or_secret' => 'wh_or',
    ];
}

test('a Blade da configuração lista só os grupos de preço do negócio da sessão', function () {
    $outro = $this->seededSupportClientTenant();
    $meu = woocfgBaseGrupo($this->business->id);
    $alheio = woocfgBaseGrupo($outro->id);

    $r = $this->get('/woocommerce/api-settings');

    $r->assertOk()->assertViewIs('woocommerce::woocommerce.api_settings');
    $grupos = collect($r->viewData('price_groups'));
    expect($grupos->has($meu))->toBeTrue();
    expect($grupos->has($alheio))->toBeFalse();
});

test('o salvar grava no negócio da sessão, não toca o outro, e segredo vazio apaga o salvo', function () {
    $outro = $this->seededSupportClientTenant();
    $antesDoOutro = DB::table('business')->where('id', $outro->id)->value('woocommerce_api_settings');
    DB::table('business')->where('id', $this->business->id)->update([
        'woocommerce_api_settings' => json_encode(['woocommerce_consumer_key' => 'ck_antigo', 'woocommerce_consumer_secret' => 'cs_antigo']),
        'woocommerce_wh_oc_secret' => 'wh_antigo',
    ]);

    $this->post('/woocommerce/update-api-settings', woocfgBasePayload([
        'woocommerce_consumer_secret' => '', 'woocommerce_wh_oc_secret' => '',
    ]))->assertRedirect();

    $meu = DB::table('business')->where('id', $this->business->id)->first();
    $json = json_decode($meu->woocommerce_api_settings, true);
    expect($json['woocommerce_app_url'])->toBe('https://loja.exemplo.test');
    expect($json['woocommerce_consumer_key'])->toBe('ck_novo');
    expect($json['woocommerce_consumer_secret'] ?? null)->toBeEmpty();
    expect($meu->woocommerce_wh_oc_secret)->toBeEmpty();
    expect($meu->woocommerce_wh_ou_secret)->toBe('wh_ou');
    expect(DB::table('business')->where('id', $outro->id)->value('woocommerce_api_settings'))->toBe($antesDoOutro);
});
