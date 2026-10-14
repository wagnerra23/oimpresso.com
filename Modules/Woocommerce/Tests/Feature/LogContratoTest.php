<?php

declare(strict_types=1);
// Cobre UC-WOOL-01, UC-WOOL-02, UC-WOOL-03, UC-WOOL-04 (Woocommerce/Log/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Thread woocommerce/playbook/02, F3 — /woocommerce/view-sync-log em Inertia atrás da flag useV2WoocommercePainel.
 * O ramo React se decide por `X-Inertia`: os pedidos daqui mandam também `X-Requested-With`, como o browser faz.
 * O DataTables de hoje está travado em LogBaselineTest. Tenant de teste 98 x cliente fictício 99 (ADR 0358).
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('woocommerce_sync_logs')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
});

function woolContrEntrar($teste, array $permissoes): void
{
    $user = $teste->usuarioComPermissoes($permissoes, $teste->business);
    $teste->actingAs($user);
    session(['user.business_id' => $teste->business->id, 'user.id' => $user->id, 'business.id' => $teste->business->id]);
    $teste->criador = $user->id;
}

/** Um minuto no futuro: as execuções do teste ficam na primeira página (mais recentes primeiro). */
function woolContrLog(int $businessId, int $criador, string $tipo, array $registros = [], ?array $detalhes = null): int
{
    return DB::table('woocommerce_sync_logs')->insertGetId([
        'business_id' => $businessId, 'sync_type' => $tipo, 'operation_type' => 'created',
        'data' => json_encode($registros ?: ['WOOL-'.uniqid()]), 'details' => $detalhes === null ? null : json_encode($detalhes),
        'created_by' => $criador, 'created_at' => now()->addMinute(), 'updated_at' => now()->addMinute(),
    ]);
}

function woolContrInertia($teste, bool $parcial = false): \Illuminate\Testing\TestResponse
{
    $h = ['X-Inertia' => 'true', 'X-Inertia-Version' => $teste->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'];
    if ($parcial) {
        $h += ['X-Inertia-Partial-Component' => 'Woocommerce/Log/Index', 'X-Inertia-Partial-Data' => 'logs'];
    }

    return $teste->withHeaders($h)->get('/woocommerce/view-sync-log');
}

test('UC-WOOL-01 com a flag ligada, GET /woocommerce/view-sync-log renderiza Inertia', function () {
    woolContrEntrar($this, ['superadmin']);
    config(['feature-flags.forced_on' => 'useV2WoocommercePainel']);

    $r = woolContrInertia($this);

    $r->assertOk();
    expect($r->json('component'))->toBe('Woocommerce/Log/Index');
});

test('UC-WOOL-02 a Blade e o DataTables dela continuam', function () {
    woolContrEntrar($this, ['superadmin']);
    app()->instance(\App\Services\FeatureFlagService::class, new class extends \App\Services\FeatureFlagService
    {
        public function isOn(string $flag, array $attrs = []): bool
        {
            return $flag === 'useV2WoocommercePainel' ? false : parent::isOn($flag, $attrs);
        }
    });
    $this->get('/woocommerce/view-sync-log')->assertOk()->assertViewIs('woocommerce::woocommerce.sync_log');

    app()->forgetInstance(\App\Services\FeatureFlagService::class);
    config(['feature-flags.forced_on' => 'useV2WoocommercePainel']);
    $this->get('/woocommerce/view-sync-log?classico=1')->assertOk()->assertViewIs('woocommerce::woocommerce.sync_log');

    $ajax = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get('/woocommerce/view-sync-log?length=10&start=0&draw=1');
    $ajax->assertOk();
    expect($ajax->json('data'))->toBeArray();
});

test('UC-WOOL-03 Tier 0 — só meus logs e só os tipos que posso ver, o mesmo conjunto do DataTables', function () {
    $this->mock(\App\Utils\ModuleUtil::class)->makePartial()
        ->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
    woolContrEntrar($this, ['woocommerce.sync_products']);
    config(['feature-flags.forced_on' => 'useV2WoocommercePainel']);
    $outro = $this->seededSupportClientTenant();
    $produtos = woolContrLog($this->business->id, $this->criador, 'all_products');
    $pedidos = woolContrLog($this->business->id, $this->criador, 'orders');
    $alheio = woolContrLog($outro->id, $this->criador, 'all_products');

    $r = woolContrInertia($this, true);
    $r->assertOk();
    $tela = collect($r->json('props.logs.itens'))->pluck('id')->all();

    expect($tela)->toContain($produtos);
    expect(in_array($pedidos, $tela, true))->toBeFalse();
    expect(in_array($alheio, $tela, true))->toBeFalse();

    // Segundo caminho: o DataTables da Blade, mesma regra de tipos.
    $this->flushHeaders();
    $datatables = collect($this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->get('/woocommerce/view-sync-log?length=-1&start=0&draw=1')->json('data'))
        ->pluck('DT_RowId')->map(fn ($id) => (int) $id)->all();
    $nossos = [$produtos, $pedidos, $alheio];
    expect(array_values(array_intersect($nossos, $tela)))->toBe(array_values(array_intersect($nossos, $datatables)));
});

test('UC-WOOL-04 a execução com erro mostra o erro e os registros', function () {
    woolContrEntrar($this, ['superadmin']);
    config(['feature-flags.forced_on' => 'useV2WoocommercePainel']);
    $id = woolContrLog($this->business->id, $this->criador, 'orders', ['#9001'], [
        ['error_type' => 'order_product_not_found', 'product' => 'CAMISA WOOL', 'order_number' => '9001'],
    ]);

    $exec = collect(woolContrInertia($this, true)->json('props.logs.itens'))->firstWhere('id', $id);

    expect($exec)->not->toBeNull();
    expect($exec['registros'])->toBe(['#9001']);
    expect($exec['erros'])->toHaveCount(1);
    expect($exec['erros'][0])->toContain('CAMISA WOOL');
    expect($exec['erros'][0])->toContain('9001');
    expect($exec['erros'][0])->not->toContain('<strong>');
});
