<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Thread woocommerce/playbook/01, F2 — o que a Blade do painel (/woocommerce) mostra HOJE, antes da tela React.
 * Trava o alerta de produtos pendentes de vínculo: conta só o negócio da sessão e com o número exato.
 * Tenant 98 × 99 (ADR 0358).
 *
 * @see memory/requisitos/Woocommerce/RUNBOOK-painel.md
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasColumn('products', 'woocommerce_product_id')) {
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

function woopBaseProduto(int $businessId, int $criador, ?int $idNaLoja): void
{
    DB::table('products')->insert([
        'name' => '0000 WOOB '.uniqid(), 'business_id' => $businessId, 'type' => 'single', 'tax_type' => 'inclusive',
        'sku' => 'WOOB-'.uniqid(), 'created_by' => $criador, 'woocommerce_product_id' => $idNaLoja,
        'woocommerce_disable_sync' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function woopBasePendentes(int $businessId): int
{
    return DB::table('products')->where('business_id', $businessId)->whereIn('type', ['single', 'variable'])
        ->whereNull('woocommerce_product_id')->where('woocommerce_disable_sync', 0)->count();
}

test('o alerta de produtos pendentes da Blade conta só o negócio da sessão, com o número exato', function () {
    $outro = $this->seededSupportClientTenant();
    woopBaseProduto($this->business->id, $this->user->id, null);
    woopBaseProduto($this->business->id, $this->user->id, null);
    woopBaseProduto($this->business->id, $this->user->id, 777);
    woopBaseProduto($outro->id, $this->user->id, null);

    $esperado = woopBasePendentes($this->business->id);
    $r = $this->get('/woocommerce');

    $r->assertOk()->assertViewIs('woocommerce::woocommerce.index');
    $alerta = $r->viewData('alerts')['not_synced_product'] ?? null;
    expect($esperado)->toBeGreaterThanOrEqual(2);
    expect($alerta)->toBe($esperado == 1
        ? __('woocommerce::lang.one_product_not_sync_alert')
        : __('woocommerce::lang.product_not_sync_alert', ['count' => $esperado]));
});
