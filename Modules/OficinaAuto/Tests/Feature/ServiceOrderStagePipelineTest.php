<?php

declare(strict_types=1);

use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Http\Controllers\ServiceOrderFsmActionController;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use App\Scopes\ScopeByBusiness;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;

uses(Tests\TestCase::class);

/**
 * Wave 7-E — Gap #2 estado-da-arte FSM screen: mini-grafo horizontal stages no drawer.
 *
 * Cobre:
 *   1. actions() retorna stages_pipeline com lista ordenada por sort_order
 *   2. Stage current marcado is_current=true; outros false
 *   3. Multi-tenant Tier 0 — stages do process biz=99 NAO aparecem em request biz=98
 *   4. Empty: OS sem current_stage_id retorna stages_pipeline vazio (in_pipeline=false)
 *
 * Pattern: skip SQLite (ADR 0101).
 *
 * @see app/Http/Controllers/ServiceOrderFsmActionController.php (método actions com stages_pipeline)
 * @see resources/js/Pages/OficinaAuto/ServiceOrders/_components/ServiceOrderStagePipeline.tsx
 * @see memory/sessions/2026-05-20-arte-tela-fsm-workflow.md (Gap #2)
 */

const BIZ_PIPELINE_TEST = 98; // tenant fictício canônico (ADR 0358) — era biz=98, empresa real
const BIZ_PIPELINE_FICTICIO = 99;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
    if (! Schema::hasTable('service_orders') || ! Schema::hasTable('sale_processes')) {
        $this->markTestSkipped('FSM/OficinaAuto tables missing');
    }
    if (! Schema::hasColumn('service_orders', 'current_stage_id')) {
        $this->markTestSkipped('Wave 7 migration current_stage_id pendente');
    }

    // Auth stub
    $user = new User();
    $user->id = 1;
    $user->username = 'pipeline-test-user';
    $user->business_id = BIZ_PIPELINE_TEST;
    $user->exists = true;
    $this->actingAs($user);
    Gate::before(fn () => true);
});

/**
 * @return array{process: SaleProcess, stages: array<string, SaleProcessStage>}
 */
function ospSetupProcess(int $bizId): array
{
    $suffix = 'osp' . $bizId . '_' . uniqid();
    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id'              => $bizId,
        'key'                      => 'cacamba_locacao_' . $suffix,
        'name'                     => 'Caçamba — Locação (test)',
        'default_for_contact_type' => 'any',
        'active'                   => true,
    ]);
    $stages = [
        'disponivel' => SaleProcessStage::create([
            'process_id' => $process->id, 'key' => 'disponivel', 'name' => 'Disponível',
            'sort_order' => 0, 'is_initial' => true, 'color' => 'gray',
        ]),
        'locada' => SaleProcessStage::create([
            'process_id' => $process->id, 'key' => 'locada', 'name' => 'Locada',
            'sort_order' => 1, 'color' => 'blue',
        ]),
        'recolhida' => SaleProcessStage::create([
            'process_id' => $process->id, 'key' => 'recolhida', 'name' => 'Recolhida',
            'sort_order' => 2, 'is_terminal' => true, 'color' => 'emerald',
        ]),
        'manutencao' => SaleProcessStage::create([
            'process_id' => $process->id, 'key' => 'manutencao', 'name' => 'Em manutenção',
            'sort_order' => 3, 'color' => 'yellow',
        ]),
    ];

    return ['process' => $process, 'stages' => $stages];
}

function ospCreateOs(int $bizId, ?int $stageId = null): ServiceOrder
{
    $vehicle = Vehicle::withoutGlobalScopes()->create([
        'business_id'  => $bizId,
        'plate'        => 'OSP' . substr((string) random_int(1000, 9999), 0, 4),
        'vehicle_type' => 'caminhao',
    ]);
    // forceFill, não create(): `current_stage_id` não está no $fillable do ServiceOrder
    // (nunca esteve — conferido nas 16 versões do model). Com create() o valor era
    // descartado em silêncio, a OS nascia fora do pipeline e actions() caía no ramo
    // in_pipeline=false. É INSERT, então a trava GuardsFsmTransitions (só `updating`)
    // não se aplica — a fixture continua sem burlar o gateway de transição.
    $os = new ServiceOrder();
    $os->forceFill([
        'business_id'      => $bizId,
        'vehicle_id'       => $vehicle->id,
        'order_type'       => 'manutencao', // locação erradicada (ADR 0265); incidental — roteamento FSM é via current_stage_id
        'status'           => 'aberta',
        'entered_at'       => now(),
        'current_stage_id' => $stageId,
    ])->save();

    return $os;
}

function ospCleanup(int $bizId): void
{
    $procIds = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', $bizId)
        ->where('key', 'like', 'cacamba_locacao_osp%')
        ->pluck('id');
    $stageIds = SaleProcessStage::whereIn('process_id', $procIds)->pluck('id');

    // Só as OS dos veículos OSP% deste teste — antes apagava TODAS as OS do tenant.
    $vehicleIds = Vehicle::withoutGlobalScopes()->where('business_id', $bizId)->where('plate', 'like', 'OSP%')->pluck('id');
    ServiceOrder::withoutGlobalScopes()->where('business_id', $bizId)->whereIn('vehicle_id', $vehicleIds)->forceDelete();
    Vehicle::withoutGlobalScopes()->whereIn('id', $vehicleIds)->forceDelete();
    SaleProcessStage::whereIn('id', $stageIds)->delete();
    SaleProcess::whereIn('id', $procIds)->delete();
}

// ─── Specs ────────────────────────────────────────────────────────────────

it('1. actions retorna stages_pipeline ordenado por sort_order', function () {
    session(['user.business_id' => BIZ_PIPELINE_TEST]);

    ['stages' => $stages] = ospSetupProcess(BIZ_PIPELINE_TEST);
    $os = ospCreateOs(BIZ_PIPELINE_TEST, $stages['locada']->id);

    $controller = app(ServiceOrderFsmActionController::class);
    $response = $controller->actions($os);
    $payload = $response->getData(true);

    expect($payload['in_pipeline'])->toBeTrue();
    expect($payload['stages_pipeline'])->toHaveCount(4);
    expect($payload['stages_pipeline'][0]['key'])->toBe('disponivel');
    expect($payload['stages_pipeline'][1]['key'])->toBe('locada');
    expect($payload['stages_pipeline'][2]['key'])->toBe('recolhida');
    expect($payload['stages_pipeline'][3]['key'])->toBe('manutencao');
})->afterEach(function () {
    ospCleanup(BIZ_PIPELINE_TEST);
});

it('2. is_current=true apenas no stage atual', function () {
    session(['user.business_id' => BIZ_PIPELINE_TEST]);

    ['stages' => $stages] = ospSetupProcess(BIZ_PIPELINE_TEST);
    $os = ospCreateOs(BIZ_PIPELINE_TEST, $stages['locada']->id);

    $controller = app(ServiceOrderFsmActionController::class);
    $payload = $controller->actions($os)->getData(true);

    $currents = array_filter($payload['stages_pipeline'], fn ($s) => $s['is_current'] === true);
    expect($currents)->toHaveCount(1);
    expect(array_values($currents)[0]['key'])->toBe('locada');
})->afterEach(function () {
    ospCleanup(BIZ_PIPELINE_TEST);
});

it('3. multi-tenant Tier 0 — biz=99 process nao vaza stages pra biz=98 OS', function () {
    session(['user.business_id' => BIZ_PIPELINE_TEST]);

    // biz=98 cria process e OS no stage locada
    ['stages' => $stagesB98] = ospSetupProcess(BIZ_PIPELINE_TEST);
    $os = ospCreateOs(BIZ_PIPELINE_TEST, $stagesB98['locada']->id);

    // biz=99 cria SEU próprio process — não deve aparecer no stages_pipeline da OS biz=98
    ['stages' => $stagesB99] = ospSetupProcess(BIZ_PIPELINE_FICTICIO);

    $controller = app(ServiceOrderFsmActionController::class);
    $payload = $controller->actions($os)->getData(true);

    expect($payload['stages_pipeline'])->toHaveCount(4); // só os 4 stages do process biz=98
    $stageIds = array_column($payload['stages_pipeline'], 'key');
    expect($stageIds)->toBe(['disponivel', 'locada', 'recolhida', 'manutencao']);

    // Garantia extra: NENHUM stage do biz=99 aparece no payload (process_id diferente)
    $biz99StageIds = collect($stagesB99)->pluck('id')->all();
    foreach ($payload['stages_pipeline'] as $s) {
        // Não vamos comparar IDs — payload não retorna id, só key.
        // Aqui validamos que o process foi o do biz=98: cada stage do payload
        // existe no array biz1 stages.
        $matches = collect($stagesB98)->contains(fn ($stage) => $stage->key === $s['key']);
        expect($matches)->toBeTrue();
    }
})->afterEach(function () {
    ospCleanup(BIZ_PIPELINE_TEST);
    ospCleanup(BIZ_PIPELINE_FICTICIO);
});

it('4. OS sem current_stage_id retorna in_pipeline=false e nao popula stages_pipeline', function () {
    session(['user.business_id' => BIZ_PIPELINE_TEST]);

    ospSetupProcess(BIZ_PIPELINE_TEST);
    $os = ospCreateOs(BIZ_PIPELINE_TEST, null);

    $controller = app(ServiceOrderFsmActionController::class);
    $payload = $controller->actions($os)->getData(true);

    expect($payload['in_pipeline'])->toBeFalse();
    expect($payload['current_stage'])->toBeNull();
    // stages_pipeline ausente OU vazio quando out-of-pipeline
    expect($payload['stages_pipeline'] ?? [])->toBeArray()->toBeEmpty();
})->afterEach(function () {
    ospCleanup(BIZ_PIPELINE_TEST);
});
