<?php

declare(strict_types=1);

use App\Business;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Database\Seeders\OficinaAutoDemoSeeder;
use Modules\OficinaAuto\Entities\OaInspectionItem;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\ServiceOrderItem;
use Modules\OficinaAuto\Entities\Vehicle;

/**
 * CU-6 smoke resiliente (worklist TRAVA-SEGUNDA Martinho) — "o wow".
 *
 * Prova que a DEMO LIMPA do documento-vivo OficinaAuto monta e percorre
 * check-in → DVI → execução SEM erro, com dados semeados (não depende de prod
 * biz=164). Falha alto se o seeder ou o fluxo FSM quebrar antes do balcão.
 *
 * Canon: real MySQL (regra Wagner não-mocka-DB), skip em sqlite (ADR 0101).
 *
 * @see Modules/OficinaAuto/Database/Seeders/OficinaAutoDemoSeeder.php
 * @see Modules/OficinaAuto/Tests/Feature/FsmTransitionTest.php (idioma espelhado)
 */
uses(Tests\TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101).');
    }
    if (! Schema::hasTable('service_orders') || ! Schema::hasTable('vehicles') || ! Schema::hasTable('oa_inspection_items')) {
        $this->markTestSkipped('Tabelas OficinaAuto ausentes — rode as migrations do módulo primeiro.');
    }
    $this->business = Business::query()->orderBy('id')->first();
    if (! $this->business) {
        $this->markTestSkipped('Sem business no banco — rode o seed base do UltimatePOS.');
    }
    session(['user.business_id' => $this->business->id]);
});

it('CU-6: demo seeder monta o documento-vivo (veículo + OS aberta + DVI + itens)', function () {
    $this->seed(OficinaAutoDemoSeeder::class);

    $vehicle = Vehicle::withoutGlobalScopes()
        ->where('business_id', $this->business->id)
        ->where('plate', OficinaAutoDemoSeeder::DEMO_PLATE)
        ->first();
    expect($vehicle)->not->toBeNull('seeder deveria criar o veículo demo');

    $os = ServiceOrder::withoutGlobalScopes()
        ->where('business_id', $this->business->id)
        ->where('vehicle_id', $vehicle->id)
        ->where('status', 'aberta')
        ->first();
    expect($os)->not->toBeNull('check-in: OS demo em aberta');
    expect($os->order_type)->toBe('manutencao');

    // DVI — inspeção visual presente (severidades válidas do enum ok/atencao/critico)
    $dvi = OaInspectionItem::withoutGlobalScopes()
        ->where('service_order_id', $os->id)
        ->get();
    expect($dvi)->toHaveCount(2);
    expect($dvi->pluck('severity')->all())->toContain('atencao');

    // Itens do documento-vivo (peça + mão-de-obra)
    $itens = ServiceOrderItem::withoutGlobalScopes()
        ->where('service_order_id', $os->id)
        ->pluck('tipo')
        ->all();
    expect($itens)->toContain('peca')->toContain('mao_obra');
});

it('CU-6: seeder é idempotente — re-rodar não duplica', function () {
    $this->seed(OficinaAutoDemoSeeder::class);
    $this->seed(OficinaAutoDemoSeeder::class);

    $count = Vehicle::withoutGlobalScopes()
        ->where('business_id', $this->business->id)
        ->where('plate', OficinaAutoDemoSeeder::DEMO_PLATE)
        ->count();
    expect($count)->toBe(1, 'firstOrCreate deve garantir 1 único veículo demo');
});

it('CU-6: stepper percorre check-in → execução sem erro (aberta → em_servico → concluida)', function () {
    $this->seed(OficinaAutoDemoSeeder::class);

    $os = ServiceOrder::withoutGlobalScopes()
        ->where('business_id', $this->business->id)
        ->where('status', 'aberta')
        ->whereHas('vehicle', fn ($q) => $q->withoutGlobalScopes()->where('plate', OficinaAutoDemoSeeder::DEMO_PLATE))
        ->first();
    expect($os)->not->toBeNull();

    $os->update(['status' => 'em_servico']);
    expect($os->fresh()->status)->toBe('em_servico');

    $os->update(['status' => 'concluida', 'completed_at' => now()]);
    $final = $os->fresh();
    expect($final->status)->toBe('concluida');
    expect($final->completed_at)->not->toBeNull('execução conclui o documento-vivo');
});
