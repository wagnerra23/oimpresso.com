<?php

declare(strict_types=1);
// @covers-us US-MKT-007

use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Services\ExecuteStageActionService;
use App\Scopes\ScopeByBusiness;
use App\Transaction;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Marketplaces\Database\Seeders\FsmProcessoVendaMarketplaceSeeder;
use Modules\Marketplaces\Services\IniciarPipelineMarketplace;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato do processo FSM "Venda de marketplace" (US-MKT-007 · ADR 0439 D2 · ADR 0143):
 * seed idempotente por empresa, toda ação crítica com papel da própria empresa, a venda entra no
 * estágio inicial pelo caminho auditado, e a transição só anda com o papel certo.
 * Tenant 98 contra 99 (ADR 0358). SKIP em SQLite.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('sale_processes')) {
        $this->markTestSkipped('Precisa do schema MySQL com as tabelas do FSM (ADR 0358).');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
});

function mktVendaCrua(int $biz): Transaction
{
    $user = (int) DB::table('users')->where('business_id', $biz)->value('id');
    $loc = (int) DB::table('business_locations')->where('business_id', $biz)->value('id');
    $id = (int) DB::table('transactions')->insertGetId([
        'business_id' => $biz, 'location_id' => $loc ?: null, 'type' => 'sell', 'status' => 'final',
        'payment_status' => 'due', 'transaction_date' => now(), 'total_before_tax' => 10, 'final_total' => 10,
        'created_by' => $user, 'essentials_duration' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    // SUPERADMIN: fixture lida fora de sessão.
    return Transaction::withoutGlobalScopes()->findOrFail($id);
}

it('US-MKT-007 · o seed cria o processo da empresa uma vez só, com papel da empresa em toda ação crítica', function () {
    $seeder = new FsmProcessoVendaMarketplaceSeeder();
    $p1 = $seeder->runForBusiness($this->b98);
    $p2 = $seeder->runForBusiness($this->b98);

    expect((int) $p1->id)->toBe((int) $p2->id)
        ->and(SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $this->b98)->where('key', 'venda_marketplace')->count())->toBe(1)
        ->and(SaleProcessStage::query()->where('process_id', $p1->id)->pluck('key')->sort()->values()->all())->toBe(['cancelado', 'entregue', 'enviado', 'pedido_recebido']);

    $criticas = SaleStageAction::with('roles')->whereIn('stage_id', SaleProcessStage::query()->where('process_id', $p1->id)->pluck('id'))->get();
    expect($criticas)->toHaveCount(4);
    foreach ($criticas as $a) {
        expect((bool) $a->is_critical)->toBeTrue()
            ->and($a->roles)->not->toBeEmpty();
        foreach ($a->roles as $r) {
            expect(str_ends_with($r->role_name, '#'.$this->b98) || ! Schema::hasColumn('roles', 'business_id'))->toBeTrue();
        }
    }
});

it('US-MKT-007 · a venda entra no estágio inicial do processo da própria empresa, com histórico', function () {
    $venda = mktVendaCrua($this->b98);

    app(IniciarPipelineMarketplace::class)->iniciar($venda, (int) $venda->created_by, 'mercado_livre:TESTE');

    $inicial = SaleProcessStage::query()->find(Transaction::withoutGlobalScopes()->find($venda->id)->current_stage_id);
    $processo = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->find($inicial->process_id);
    expect($inicial->key)->toBe('pedido_recebido')
        ->and((int) $processo->business_id)->toBe($this->b98)
        ->and($processo->key)->toBe('venda_marketplace')
        ->and(DB::table('sale_stage_history')->where('transaction_id', $venda->id)->whereNull('action_id')->where('to_stage_id', $inicial->id)->count())->toBe(1);
});

it('US-MKT-007 · marcar como enviado só anda com o papel da empresa; sem ele é recusado', function () {
    $venda = mktVendaCrua($this->b98);
    app(IniciarPipelineMarketplace::class)->iniciar($venda, (int) $venda->created_by, 'mercado_livre:TESTE');
    $venda = Transaction::withoutGlobalScopes()->find($venda->id);

    $semPapel = User::query()->where('business_id', $this->b98)->whereDoesntHave('roles')->first()
        ?? User::query()->findOrFail((int) $venda->created_by);
    $semPapel->syncRoles([]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    expect(fn () => app(ExecuteStageActionService::class)->execute($venda, 'marcar_enviado', $semPapel))
        ->toThrow(UnauthorizedActionException::class);

    $papel = Schema::hasColumn('roles', 'business_id') ? 'marketplaces.atendimento#'.$this->b98 : 'marketplaces.atendimento';
    $semPapel->assignRole($papel);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    app(ExecuteStageActionService::class)->execute($venda, 'marcar_enviado', $semPapel->fresh());

    expect(SaleProcessStage::query()->find(Transaction::withoutGlobalScopes()->find($venda->id)->current_stage_id)->key)->toBe('enviado');
});
