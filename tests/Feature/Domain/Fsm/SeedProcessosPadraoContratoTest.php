<?php

declare(strict_types=1);

// @covers-us US-SELL-012

use App\Console\Commands\SeedDefaultFsmProcesses;
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
use Tests\Support\EstoqueFixture;

/**
 * Os processos padrão da FSM não podem apontar para evento inexistente: o
 * ExecuteStageActionService faz `event(new $action->event_class(...))` e a ação quebra com
 * "Class not found" (auditoria NfeBrasil, N4-08).
 *
 * Roda no MySQL real, no tenant fictício, porque o GateEmissaoPorVendaTest só roda em sqlite e a
 * lane domain-pest usa MySQL.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! Schema::hasTable('sale_processes')) {
        $this->markTestSkipped('Schema UltimatePOS ausente — roda na lane MySQL / CT 100.');
    }
    $this->biz = EstoqueFixture::businessId();
    (new SeedDefaultFsmProcesses)->seedForBusiness($this->biz);
    $this->processo = fn (string $chave) => SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', $this->biz)->where('key', $chave)->firstOrFail();
});

it('toda ação semeada com event_class aponta para classe que existe', function () {
    $processos = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', $this->biz)
        ->whereIn('key', ['venda_sem_nota', 'venda_com_nota_manual', 'venda_com_nota_auto'])
        ->pluck('id');
    expect($processos)->toHaveCount(3);

    $etapas = SaleProcessStage::whereIn('process_id', $processos)->pluck('id');
    $classes = SaleStageAction::whereIn('stage_id', $etapas)->whereNotNull('event_class')->pluck('event_class');

    $inexistentes = $classes->reject(fn (string $c) => class_exists($c))->values()->all();
    expect($inexistentes)->toBe([]);
});

it('emitir_nfe segue no estágio paga do processo automático, que é o gate do listener', function () {
    $etapa = SaleProcessStage::where('process_id', ($this->processo)('venda_com_nota_auto')->id)->where('key', 'paga')->firstOrFail();

    expect(SaleStageAction::where('stage_id', $etapa->id)->where('key', 'emitir_nfe')->exists())->toBeTrue();
});

it('executar emitir_nfe no processo automático avança a venda sem quebrar', function () {
    $processo = ($this->processo)('venda_com_nota_auto');
    $paga = SaleProcessStage::where('process_id', $processo->id)->where('key', 'paga')->firstOrFail();
    $emitida = SaleProcessStage::where('process_id', $processo->id)->where('key', 'emitida')->firstOrFail();
    $usuario = EstoqueFixture::userId($this->biz);

    $id = DB::table('transactions')->insertGetId([
        'business_id' => $this->biz, 'location_id' => EstoqueFixture::locationId($this->biz, 'FSMNFE'),
        'type' => 'sell', 'status' => 'final', 'payment_status' => 'paid', 'transaction_date' => now(),
        'final_total' => 10, 'created_by' => $usuario, 'current_stage_id' => $paga->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    app(ExecuteStageActionService::class)->execute(Transaction::findOrFail($id), 'emitir_nfe', User::findOrFail($usuario));

    expect((int) DB::table('transactions')->where('id', $id)->value('current_stage_id'))->toBe((int) $emitida->id);
});
