<?php

declare(strict_types=1);

use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageHistory;
use App\Domain\Fsm\Services\FsmDriftDetector;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;

/**
 * US-SELL-032 v2 — Detector offline de drift no FSM (ADR 0129).
 *
 * Cenários cobertos:
 *   1. zero drifts (subject coerente com history)
 *   2. orphan (subject com current_stage_id sem nenhuma history)
 *   3. mismatch (current ≠ último to_stage_id em history)
 *   4. filtro por businessId
 *   5. log.warning estruturado pra cada drift
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    Schema::create('fsm_test_subjects', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id');
        $t->unsignedBigInteger('current_stage_id')->nullable();
    });

    foreach (glob(database_path('migrations/2026_05_11_12*_create_sale_*.php')) ?: [] as $f) {
        (require $f)->up();
    }
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        foreach (array_reverse(glob(database_path('migrations/2026_05_11_12*_create_sale_*.php')) ?: []) as $f) {
            (require $f)->down();
        }

        Schema::dropIfExists('fsm_test_subjects');
    }
});

/**
 * Cria SaleProcess+stages e devolve [process, stageA, stageB].
 */
function driftSetup(int $bizId): array
{
    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $bizId,
        'key' => 'venda_drift_' . $bizId,
        'name' => 'Drift biz=' . $bizId,
        'default_for_contact_type' => 'any',
        'active' => true,
    ]);

    $a = SaleProcessStage::create([
        'process_id' => $process->id, 'key' => 'sa_' . $bizId, 'name' => 'StageA', 'sort_order' => 0, 'is_initial' => true,
    ]);
    $b = SaleProcessStage::create([
        'process_id' => $process->id, 'key' => 'sb_' . $bizId, 'name' => 'StageB', 'sort_order' => 1,
    ]);

    return compact('process', 'a', 'b');
}

/**
 * Insere um subject FSM-test (driver bruto — não passa por nenhum Observer).
 */
function driftSubject(int $bizId, ?int $stageId): int
{
    return (int) DB::table('fsm_test_subjects')->insertGetId([
        'business_id' => $bizId,
        'current_stage_id' => $stageId,
    ]);
}

/**
 * Insere uma history row direto via SaleStageHistory (sem global scope).
 */
function driftHistory(int $bizId, int $txId, ?int $fromStageId, ?int $toStageId, string $executedAt): void
{
    SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $bizId,
        'transaction_id' => $txId,
        'action_id' => 1, // arbitrário pro teste — sem FK no schema atual
        'from_stage_id' => $fromStageId,
        'to_stage_id' => $toStageId,
        'executed_at' => $executedAt,
    ]);
}

// ─── Specs ────────────────────────────────────────────────────────────────

it('1. zero drifts quando current_stage_id bate com último to_stage_id em history', function () {
    ['a' => $a, 'b' => $b] = driftSetup(1);
    $txId = driftSubject(1, $b->id);

    // History coerente: passou A→B, current está em B
    driftHistory(1, $txId, null, $a->id, '2026-05-12 10:00:00');
    driftHistory(1, $txId, $a->id, $b->id, '2026-05-12 11:00:00');

    $drifts = (new FsmDriftDetector)->scan('fsm_test_subjects');

    expect($drifts)->toBe([]);
});

it('2. drift orphan: subject com current_stage_id sem nenhuma history detectado', function () {
    ['a' => $a] = driftSetup(1);
    $txId = driftSubject(1, $a->id);

    // Nenhuma history → orphan

    $drifts = (new FsmDriftDetector)->scan('fsm_test_subjects');

    expect($drifts)->toHaveCount(1);
    expect($drifts[0]['severity'])->toBe('orphan');
    expect($drifts[0]['business_id'])->toBe(1);
    expect($drifts[0]['transaction_id'])->toBe($txId);
    expect($drifts[0]['current_stage_id'])->toBe($a->id);
    expect($drifts[0]['expected_stage_id'])->toBeNull();
    expect($drifts[0]['last_history_at'])->toBeNull();
});

it('3. drift mismatch: current_stage_id diferente do último to_stage_id', function () {
    ['a' => $a, 'b' => $b] = driftSetup(1);

    // Subject foi pra B legitimamente
    $txId = driftSubject(1, $b->id);
    driftHistory(1, $txId, null, $a->id, '2026-05-12 10:00:00');
    driftHistory(1, $txId, $a->id, $b->id, '2026-05-12 11:00:00');

    // Mass-update BYPASS: alguém moveu pra A diretamente sem passar pelo Service
    DB::table('fsm_test_subjects')->where('id', $txId)->update(['current_stage_id' => $a->id]);

    $drifts = (new FsmDriftDetector)->scan('fsm_test_subjects');

    expect($drifts)->toHaveCount(1);
    expect($drifts[0]['severity'])->toBe('mismatch');
    expect($drifts[0]['transaction_id'])->toBe($txId);
    expect($drifts[0]['current_stage_id'])->toBe($a->id);
    expect($drifts[0]['expected_stage_id'])->toBe($b->id);
    expect($drifts[0]['last_history_at'])->not->toBeNull();
});

it('4. scan filtra por businessId quando passado', function () {
    ['a' => $a1] = driftSetup(1);
    ['a' => $a99] = driftSetup(99);

    $tx1 = driftSubject(1, $a1->id);   // orphan biz=1
    $tx99 = driftSubject(99, $a99->id); // orphan biz=99

    // Sem filtro: vê os 2
    $all = (new FsmDriftDetector)->scan('fsm_test_subjects');
    expect($all)->toHaveCount(2);

    // Filtro biz=1: vê só 1
    $only1 = (new FsmDriftDetector)->scan('fsm_test_subjects', 1);
    expect($only1)->toHaveCount(1);
    expect($only1[0]['business_id'])->toBe(1);
    expect($only1[0]['transaction_id'])->toBe($tx1);

    // Filtro biz=99: vê só 99
    $only99 = (new FsmDriftDetector)->scan('fsm_test_subjects', 99);
    expect($only99)->toHaveCount(1);
    expect($only99[0]['business_id'])->toBe(99);
    expect($only99[0]['transaction_id'])->toBe($tx99);
});

it('5. log.warning emitido pra cada drift detectado', function () {
    ['a' => $a, 'b' => $b] = driftSetup(1);
    driftSubject(1, $a->id); // orphan

    $txMis = driftSubject(1, $b->id);
    driftHistory(1, $txMis, null, $a->id, '2026-05-12 10:00:00');
    DB::table('fsm_test_subjects')->where('id', $txMis)->update(['current_stage_id' => $b->id]);
    // ↑ history aponta to_stage_id=A, current=B → mismatch

    Log::spy();

    $drifts = (new FsmDriftDetector)->scan('fsm_test_subjects');

    expect($drifts)->toHaveCount(2);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $msg, array $ctx) =>
            $msg === 'FsmDriftDetector: drift detected'
            && isset($ctx['severity'])
            && isset($ctx['business_id'])
            && isset($ctx['transaction_id'])
        )
        ->twice();
});
