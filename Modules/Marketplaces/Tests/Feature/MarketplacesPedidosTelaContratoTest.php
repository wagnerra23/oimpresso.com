<?php

declare(strict_types=1);
// @covers-us US-MKT-008
// Cobre UC-PEDIDO-01, UC-PEDIDO-02, UC-PEDIDO-03 e UC-PEDIDO-04 (Marketplaces/Pedidos.casos.md).

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Marketplaces\Entities\ContaVendedor;
use Modules\Marketplaces\Entities\Marketplace;
use Modules\Marketplaces\Entities\PedidoMarketplace;
use Modules\Marketplaces\Services\MarketplacesAcesso;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato da tela "Pedidos dos marketplaces" (US-MKT-008). Tenant 98 contra 99 (ADR 0358).
 * ⚠️ SKIP em SQLite (schema UltimatePOS).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasColumn('mkt_account_credentials', 'location_id')) {
        $this->markTestSkipped('Precisa do schema MySQL com as tabelas mkt_* (ADR 0358).');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([MarketplacesAcesso::PERMISSAO, MarketplacesAcesso::PERMISSAO_CONECTAR], $this->seededTenant());
    $this->u98SoVer = $this->usuarioComPermissoes([MarketplacesAcesso::PERMISSAO], $this->seededTenant());
    $this->ml = Marketplace::query()->where('slug', Marketplace::MERCADO_LIVRE)->firstOrFail();
    $this->loc98 = (int) DB::table('business_locations')->where('business_id', $this->b98)->value('id');
    $this->loc99 = (int) DB::table('business_locations')->where('business_id', $this->b99)->value('id');

    $GLOBALS['mkt_tela_pacote'] = [$this->b98, $this->b99];
    $fake = Mockery::mock(ModuleUtil::class)->makePartial();
    $fake->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($biz, $perm) => $perm === MarketplacesAcesso::PACOTE ? in_array((int) $biz, $GLOBALS['mkt_tela_pacote'], true) : true);
    app()->instance(ModuleUtil::class, $fake);

    // SUPERADMIN: fixture grava contas e pedidos nas duas empresas.
    $this->conta98 = ContaVendedor::withoutGlobalScopes()->create(['business_id' => $this->b98, 'marketplace_id' => $this->ml->id, 'seller_id_external' => 'tela-98']);
    $this->conta99 = ContaVendedor::withoutGlobalScopes()->create(['business_id' => $this->b99, 'marketplace_id' => $this->ml->id, 'seller_id_external' => 'tela-99']);
    $pedido = fn (int $biz, int $conta, string $num, ?int $tx) => PedidoMarketplace::withoutGlobalScopes()->create([
        'business_id' => $biz, 'account_id' => $conta, 'marketplace_id' => $this->ml->id, 'order_id_external' => $num,
        'status_external' => 'paid', 'total_amount' => 10, 'transaction_id' => $tx, 'date_created_external' => now(),
    ]);
    $tx98 = (int) DB::table('transactions')->insertGetId([
        'business_id' => $this->b98, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'due', 'transaction_date' => now(),
        'total_before_tax' => 10, 'final_total' => 10, 'created_by' => $this->u98->id, 'essentials_duration' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->comVenda98 = $pedido($this->b98, $this->conta98->id, 'TELA-98-COM', $tx98);
    $this->semVenda98 = $pedido($this->b98, $this->conta98->id, 'TELA-98-SEM', null);
    $this->pedido99 = $pedido($this->b99, $this->conta99->id, 'TELA-99', null);
});

function mktTelaComo($t, $user)
{
    return $t->actingAs($user)->withSession(['user.business_id' => (int) $user->business_id]);
}

/** Prop deferida pela visita parcial do Inertia (mesmos cabeçalhos que o navegador manda). */
function mktTelaProp($t, $user, string $rota, string $prop): array
{
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $r = mktTelaComo($t, $user)->get($rota, ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) $versao, 'X-Inertia-Partial-Data' => $prop, 'X-Inertia-Partial-Component' => 'Marketplaces/Pedidos']);
    $r->assertOk();

    return (array) $r->json('props.'.$prop);
}

it('UC-PEDIDO-01 · a lista traz só os pedidos da empresa, com a venda quando já virou venda', function () {
    mktTelaComo($this, $this->u98)->get('/marketplaces/pedidos')->assertOk();

    $pedidos = collect(mktTelaProp($this, $this->u98, '/marketplaces/pedidos', 'pedidos'));
    expect($pedidos->pluck('numero')->sort()->values()->all())->toBe(['TELA-98-COM', 'TELA-98-SEM'])
        ->and($pedidos->firstWhere('numero', 'TELA-98-COM')['venda_id'])->not->toBeNull()
        ->and($pedidos->firstWhere('numero', 'TELA-98-SEM')['venda_id'])->toBeNull();

    $contas = collect(mktTelaProp($this, $this->u98, '/marketplaces/pedidos', 'contas'));
    expect($contas->pluck('id')->all())->toBe([(int) $this->conta98->id]);
});

it('UC-PEDIDO-02 · o filtro separa pedidos com e sem venda', function () {
    foreach (['sem_venda' => ['TELA-98-SEM'], 'com_venda' => ['TELA-98-COM']] as $situacao => $esperado) {
        $pedidos = collect(mktTelaProp($this, $this->u98, '/marketplaces/pedidos?situacao='.$situacao, 'pedidos'));
        expect($pedidos->pluck('numero')->all())->toBe($esperado);
    }
});

it('UC-PEDIDO-03 · escolher o local: aceita local da empresa, recusa local e conta de outra empresa, e exige a permissão', function () {
    if ($this->loc98 === 0 || $this->loc99 === 0) {
        $this->markTestSkipped('Tenant sem local de venda no seed.');
    }

    mktTelaComo($this, $this->u98)->patch('/marketplaces/contas/'.$this->conta98->id.'/local', ['location_id' => $this->loc98])->assertRedirect();
    // SUPERADMIN: releitura direta.
    expect((int) ContaVendedor::withoutGlobalScopes()->find($this->conta98->id)->location_id)->toBe($this->loc98);

    mktTelaComo($this, $this->u98)->patch('/marketplaces/contas/'.$this->conta98->id.'/local', ['location_id' => $this->loc99])->assertSessionHasErrors('location_id');
    expect((int) ContaVendedor::withoutGlobalScopes()->find($this->conta98->id)->location_id)->toBe($this->loc98);

    mktTelaComo($this, $this->u98)->patch('/marketplaces/contas/'.$this->conta99->id.'/local', ['location_id' => $this->loc98])->assertNotFound();
    mktTelaComo($this, $this->u98SoVer)->patch('/marketplaces/contas/'.$this->conta98->id.'/local', ['location_id' => $this->loc98])->assertForbidden();
});

it('UC-PEDIDO-04 · sem o pacote a tela não abre', function () {
    $GLOBALS['mkt_tela_pacote'] = [$this->b99];

    mktTelaComo($this, $this->u98)->get('/marketplaces/pedidos')->assertForbidden();
});

it('UC-PEDIDO-00 · o item Marketplaces aparece no menu para quem pode ver e some sem o pacote', function () {
    $labels = function () {
        \Menu::create('admin-sidebar-menu', fn ($menu) => $menu);
        (new \Modules\Marketplaces\Http\Controllers\DataController())->modifyAdminMenu();

        return collect((new \App\Services\LegacyMenuAdapter())->build());
    };

    mktTelaComo($this, $this->u98);
    session(['user.business_id' => $this->b98]);
    $item = $labels()->firstWhere('label', 'Marketplaces');
    expect($item)->not->toBeNull()
        ->and((string) $item['href'])->toContain('/marketplaces/pedidos');

    // Controle negativo: a mesma usuária, empresa sem o pacote → sem item.
    $GLOBALS['mkt_tela_pacote'] = [$this->b99];
    expect($labels()->firstWhere('label', 'Marketplaces'))->toBeNull();
});
