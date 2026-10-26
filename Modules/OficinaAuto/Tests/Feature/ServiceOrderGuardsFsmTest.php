<?php
// @covers-us US-OFICINA-006

declare(strict_types=1);

use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Domain\Fsm\Support\FsmAuthorizationFlag;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Database\Seeders\OficinaAutoFsmSeeder;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Services\ServiceOrderPipelineStarter;
use Tests\Support\WithSeededTenant;

uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

/**
 * US-OFICINA-006 — `ServiceOrder` adota a trava `GuardsFsmTransitions` (ADR 0129/0143).
 *
 * Antes desta trava, `$os->current_stage_id = X; $os->save()` passava sem erro e a OS pulava de
 * etapa sem histórico, sem RBAC e sem side-effect. Agora só muda de etapa quem marca a flag:
 * `ExecuteStageActionService` e `ServiceOrderPipelineStarter`. Mesma regra de Transaction e JobSheet.
 *
 * Cada recusa tem âncora positiva no mesmo teste (o caminho autorizado move a etapa), para que o
 * verde não seja vácuo (§5 2026-09-27).
 *
 * Tenant 98 (ADR 0358). Nunca biz=4 nem biz=164.
 * ⚠️ SKIP em SQLite / sem schema: leia assertions, não "0 failed" (LC-13).
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS.');
    }
    foreach (['service_orders', 'vehicles', 'sale_processes', 'sale_process_stages', 'sale_stage_history'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }
    try {
        $this->biz = (int) $this->seededTenant()->id;
    } catch (\Throwable $e) {
        $this->markTestSkipped('Tenant de teste ausente: ' . $e->getMessage());
    }
    (new OficinaAutoFsmSeeder())->runForBusiness($this->biz);
    session(['user.business_id' => $this->biz]);

    $this->etapa = fn (string $key) => (int) DB::table('sale_process_stages as s')
        ->join('sale_processes as p', 'p.id', '=', 's.process_id')
        ->where('p.business_id', $this->biz)->where('p.key', 'oficina_mecanica_os')->where('s.key', $key)
        ->value('s.id');

    $veiculo = DB::table('vehicles')->insertGetId([
        'business_id' => $this->biz, 'plate' => 'GRD' . random_int(1000, 9999), 'vehicle_type' => 'caminhao',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->os = ServiceOrder::create([
        'business_id' => $this->biz,
        'vehicle_id'  => $veiculo,
        'order_type'  => 'mecanica',
        'status'      => 'aberta',
        'entered_at'  => now(),
    ]);
});

function grdStageNoBanco(int $osId): ?int
{
    $v = DB::table('service_orders')->where('id', $osId)->value('current_stage_id');

    return $v === null ? null : (int) $v;
}

it('ServiceOrder usa a trait GuardsFsmTransitions', function () {
    expect(class_uses_recursive(ServiceOrder::class))
        ->toHaveKey(\App\Domain\Fsm\Concerns\GuardsFsmTransitions::class);
});

it('pipeline iniciado pelo ServiceOrderPipelineStarter entra na etapa inicial (caminho autorizado)', function () {
    $stage = app(ServiceOrderPipelineStarter::class)->start($this->os);

    expect(grdStageNoBanco($this->os->id))->toBe((int) $stage->id);
    expect($stage->id)->toBe(($this->etapa)('recepcao'));
});

it('UPDATE direto em current_stage_id é recusado e o banco não muda', function () {
    app(ServiceOrderPipelineStarter::class)->start($this->os);
    $antes = grdStageNoBanco($this->os->id);
    expect($antes)->toBe(($this->etapa)('recepcao'));

    $os = ServiceOrder::withoutGlobalScopes()->findOrFail($this->os->id); // teste: OS do 98 explícita
    $os->current_stage_id = ($this->etapa)('entregue');

    expect(fn () => $os->save())->toThrow(UnauthorizedActionException::class);
    expect(grdStageNoBanco($this->os->id))->toBe($antes);
});

it('com a flag marcada a mesma troca passa, e a flag vale uma vez só', function () {
    app(ServiceOrderPipelineStarter::class)->start($this->os);
    $os = ServiceOrder::withoutGlobalScopes()->findOrFail($this->os->id); // teste: OS do 98 explícita

    FsmAuthorizationFlag::mark(ServiceOrder::class, $os->getKey());
    $os->current_stage_id = ($this->etapa)('em_diagnostico');
    $os->save();
    expect(grdStageNoBanco($this->os->id))->toBe(($this->etapa)('em_diagnostico'));

    // consume-once: a segunda troca sem nova flag é recusada
    $os->current_stage_id = ($this->etapa)('entregue');
    expect(fn () => $os->save())->toThrow(UnauthorizedActionException::class);
    expect(grdStageNoBanco($this->os->id))->toBe(($this->etapa)('em_diagnostico'));
});

it('editar outros campos da OS segue livre (a trava só olha current_stage_id)', function () {
    app(ServiceOrderPipelineStarter::class)->start($this->os);
    $os = ServiceOrder::withoutGlobalScopes()->findOrFail($this->os->id); // teste: OS do 98 explícita

    $os->status = 'em_servico';
    $os->save();

    expect(DB::table('service_orders')->where('id', $this->os->id)->value('status'))->toBe('em_servico');
    expect(grdStageNoBanco($this->os->id))->toBe(($this->etapa)('recepcao'));
});
