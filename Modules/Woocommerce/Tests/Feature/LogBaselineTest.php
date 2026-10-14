<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Thread woocommerce/playbook/02, F2 — o que o log de sincronização (/woocommerce/view-sync-log) devolve HOJE ao
 * DataTables da Blade, antes da tela React: só logs do negócio da sessão, e só os tipos que a permissão deixa ver.
 * Tenant 98 × 99 (ADR 0358).
 *
 * @see memory/requisitos/Woocommerce/RUNBOOK-log.md
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('woocommerce_sync_logs')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    app()->instance(\App\Services\FeatureFlagService::class, new class extends \App\Services\FeatureFlagService
    {
        public function isOn(string $flag, array $attrs = []): bool
        {
            return $flag === 'useV2WoocommercePainel' ? false : parent::isOn($flag, $attrs);
        }
    });
});

function woologBaseEntrar($teste, array $permissoes): void
{
    $user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $user->id, 'business.id' => $teste->business->id]);
    $teste->criador = $user->id;
}

function woologBaseLog(int $businessId, int $criador, string $tipo): int
{
    return DB::table('woocommerce_sync_logs')->insertGetId([
        'business_id' => $businessId, 'sync_type' => $tipo, 'operation_type' => 'created',
        'data' => json_encode(['WOOLOG-'.uniqid()]), 'created_by' => $criador,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** O pedido que o DataTables da Blade faz: AJAX, todas as linhas. */
function woologBaseIds($teste): array
{
    $r = $teste->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get('/woocommerce/view-sync-log?length=-1&start=0&draw=1');
    $r->assertOk();

    return collect($r->json('data'))->pluck('DT_RowId')->map(fn ($id) => (int) $id)->all();
}

test('sem AJAX, /woocommerce/view-sync-log devolve a Blade', function () {
    woologBaseEntrar($this, ['superadmin']);

    $this->get('/woocommerce/view-sync-log')->assertOk()->assertViewIs('woocommerce::woocommerce.sync_log');
});

test('o DataTables do log traz só os logs do negócio da sessão', function () {
    woologBaseEntrar($this, ['superadmin']);
    $outro = $this->seededSupportClientTenant();
    $meu = woologBaseLog($this->business->id, $this->criador, 'all_products');
    $alheio = woologBaseLog($outro->id, $this->criador, 'all_products');

    $ids = woologBaseIds($this);

    expect($ids)->toContain($meu);
    expect(in_array($alheio, $ids, true))->toBeFalse();
});

test('quem só sincroniza produtos não vê o log de pedidos', function () {
    $this->mock(\App\Utils\ModuleUtil::class)->makePartial()
        ->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
    woologBaseEntrar($this, ['woocommerce.sync_products']);
    $produtos = woologBaseLog($this->business->id, $this->criador, 'all_products');
    $pedidos = woologBaseLog($this->business->id, $this->criador, 'orders');

    $ids = woologBaseIds($this);

    expect($ids)->toContain($produtos);
    expect(in_array($pedidos, $ids, true))->toBeFalse();
});
