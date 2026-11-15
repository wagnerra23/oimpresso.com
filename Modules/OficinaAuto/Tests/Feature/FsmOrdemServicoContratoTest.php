<?php
// @covers-us US-OFICINA-006

declare(strict_types=1);

use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Services\ExecuteStageActionService;
use App\Domain\Fsm\SideEffects\EnviarCacambaManutencao;
use App\Domain\Fsm\SideEffects\VoltarCacambaDisponivel;
use App\Scopes\ScopeByBusiness;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Database\Seeders\OficinaAutoFsmSeeder;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Dois defeitos achados ao ligar KanbanDragDropTest e ServiceOrderFsmActionControllerTest
 * na lane (lote 1.2 da auditoria, run 42619030857):
 *
 * - `voltar_disponivel` e `enviar_manutencao` recusavam a OS com 500, porque o side effect
 *   só aceitava Vehicle e o controller da FSM executa pela OS;
 * - em sessão de outra empresa o Service caía em ErrorException em vez de recusar, porque
 *   o escopo esconde o processo e o código lia business_id de null.
 *
 * Tenant 98 contra 99 (ADR 0358).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS');
    }
    if (! Schema::hasColumn('service_orders', 'current_stage_id') || ! Schema::hasColumn('vehicles', 'current_status')) {
        $this->markTestSkipped('schema do OficinaAuto ausente — rode o migrate do módulo');
    }

    $this->biz = (int) $this->seededTenant()->id;
    $this->outra = (int) $this->seededSupportClientTenant()->id;
    session(['user.business_id' => $this->biz]);

    $this->veiculo = fn (int $biz, string $placa, string $status) => Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: fixture em tenant explícito
        'business_id'    => $biz,
        'plate'          => $placa,
        'vehicle_type'   => 'caminhao',
        'current_status' => $status,
    ]);
    $this->os = fn (int $biz, int $veiculoId) => ServiceOrder::withoutGlobalScopes()->create([ // SUPERADMIN: fixture em tenant explícito
        'business_id' => $biz,
        'vehicle_id'  => $veiculoId,
        'order_type'  => 'manutencao',
        'status'      => 'aberta',
        'entered_at'  => now(),
    ]);
});

it('voltar_disponivel pela OS devolve o veículo dela ao pool', function () {
    $veiculo = ($this->veiculo)($this->biz, 'FSM98A1', 'manutencao');
    $os = ($this->os)($this->biz, $veiculo->id);

    (new VoltarCacambaDisponivel())->execute($os);

    $linha = DB::table('vehicles')->where('id', $veiculo->id)->first();
    expect($linha->current_status)->toBe('disponivel');
    expect($linha->current_rental_id)->toBeNull();
});

it('enviar_manutencao pela OS põe o veículo dela em manutenção', function () {
    $veiculo = ($this->veiculo)($this->biz, 'FSM98A2', 'disponivel');
    $os = ($this->os)($this->biz, $veiculo->id);

    (new EnviarCacambaManutencao())->execute($os, ['motivo' => 'freio']);

    expect(DB::table('vehicles')->where('id', $veiculo->id)->value('current_status'))->toBe('manutencao');
});

it('os side effects seguem aceitando o próprio Vehicle', function () {
    $veiculo = ($this->veiculo)($this->biz, 'FSM98A3', 'manutencao');

    (new VoltarCacambaDisponivel())->execute($veiculo);

    expect(DB::table('vehicles')->where('id', $veiculo->id)->value('current_status'))->toBe('disponivel');
});

it('OS apontando para veículo de outra empresa é recusada e o veículo não muda', function () {
    $alheio = ($this->veiculo)($this->outra, 'FSM99A4', 'manutencao');
    $os = ($this->os)($this->biz, $alheio->id);

    expect(fn () => (new VoltarCacambaDisponivel())->execute($os))
        ->toThrow(InvalidArgumentException::class);

    expect(DB::table('vehicles')->where('id', $alheio->id)->value('current_status'))->toBe('manutencao');
});

it('sessão de outra empresa recebe recusa de tenant e a OS não muda de etapa', function () {
    (new OficinaAutoFsmSeeder())->runForBusiness($this->biz);
    $processo = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', $this->biz)
        ->where('key', 'cacamba_manutencao')
        ->firstOrFail();
    $etapa = SaleProcessStage::where('process_id', $processo->id)
        ->whereHas('actions')
        ->where('is_terminal', false)
        ->orderBy('sort_order')
        ->firstOrFail();
    $acao = $etapa->actions()->firstOrFail();

    $veiculo = ($this->veiculo)($this->biz, 'FSM98A5', 'manutencao');
    $os = ($this->os)($this->biz, $veiculo->id);
    DB::table('service_orders')->where('id', $os->id)->update(['current_stage_id' => $etapa->id]);
    $os = ServiceOrder::withoutGlobalScopes()->find($os->id); // SUPERADMIN: leitura da fixture

    $intruso = $this->usuarioComPermissoes(['oficinaauto.service_order.update'], $this->seededSupportClientTenant());
    $this->actingAs($intruso);
    session(['user.business_id' => $this->outra]);

    expect(fn () => app(ExecuteStageActionService::class)->execute($os, $acao->key, $intruso, []))
        ->toThrow(UnauthorizedActionException::class, 'Cross-tenant');

    expect((int) DB::table('service_orders')->where('id', $os->id)->value('current_stage_id'))->toBe((int) $etapa->id);
    expect(DB::table('sale_stage_history')->where('transaction_id', $os->id)->count())->toBe(0);
});
