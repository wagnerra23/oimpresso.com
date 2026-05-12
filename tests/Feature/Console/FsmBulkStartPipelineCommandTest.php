<?php

declare(strict_types=1);

use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageHistory;
use App\Transaction;
use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;

/**
 * US-SELL-035 ext — comando `fsm:bulk-start-pipeline`.
 *
 * Migra vendas legadas (current_stage_id=NULL) pro pipeline FSM em lote,
 * mapeando status legacy → stage_key inicial. Mesmo mapping do
 * SaleFsmActionController::startPipeline().
 *
 * Default biz=1 (Wagner WR2 SC). NUNCA biz=4 (ROTA LIVRE cliente real —
 * feedback_test_business_id_1_nunca_4 + ADR 0101).
 *
 * Setup: cria tabela `transactions` inline (só colunas que o command lê:
 * id, business_id, type, status, payment_status, sub_status,
 * current_stage_id). Migrations FSM `2026_05_11_12*_create_sale_*` + hotfix
 * `2026_05_12_040001_alter_sale_stage_history_action_id_nullable` aplicadas.
 */

beforeEach(function () {
    Schema::create('users', function (Blueprint $t) {
        $t->increments('id');
        $t->string('username')->unique();
        $t->string('password');
        $t->integer('business_id')->nullable();
        $t->rememberToken();
        $t->softDeletes();
        $t->timestamps();
    });

    Schema::create('transactions', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id');
        $t->string('type', 32)->default('sell');
        $t->string('status', 32)->nullable();
        $t->string('payment_status', 32)->nullable();
        $t->string('sub_status', 32)->nullable();
        $t->unsignedBigInteger('current_stage_id')->nullable();
        $t->index(['business_id', 'current_stage_id'], 'transactions_biz_stage_idx');
    });

    // App\Transaction usa trait LogsActivity (spatie/activitylog) que grava em
    // activity_log a cada save. Criar tabela mínima pra não quebrar fixtures.
    Schema::create('activity_log', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('log_name')->nullable()->index();
        $t->text('description');
        $t->unsignedBigInteger('subject_id')->nullable();
        $t->string('subject_type')->nullable();
        $t->unsignedBigInteger('causer_id')->nullable();
        $t->string('causer_type')->nullable();
        $t->string('causer_kind', 32)->nullable();
        $t->json('properties')->nullable();
        $t->uuid('batch_uuid')->nullable();
        $t->string('event')->nullable();
        $t->unsignedInteger('business_id')->nullable();
        $t->boolean('reverted_at')->nullable();
        $t->timestamps();
    });

    foreach (glob(database_path('migrations/2026_05_11_12*_create_sale_*.php')) ?: [] as $f) {
        (require $f)->up();
    }

    // Hotfix action_id NULLABLE (skipa se SQLite — INFORMATION_SCHEMA é MySQL)
    if (config('database.default') === 'mysql') {
        (require database_path('migrations/2026_05_12_040001_alter_sale_stage_history_action_id_nullable.php'))->up();
    } else {
        // SQLite: recria coluna action_id como nullable diretamente
        Schema::table('sale_stage_history', function (Blueprint $t) {
            // No-op em SQLite: schema é redefinido no create. Recriamos a tabela.
        });
        // Drop+recreate em SQLite preserva o teste — action_id ficará nullable
        Schema::dropIfExists('sale_stage_history');
        Schema::create('sale_stage_history', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedInteger('business_id');
            $t->unsignedBigInteger('transaction_id');
            $t->unsignedBigInteger('action_id')->nullable();
            $t->unsignedBigInteger('from_stage_id')->nullable();
            $t->unsignedBigInteger('to_stage_id')->nullable();
            $t->unsignedInteger('user_id')->nullable();
            $t->json('payload_snapshot')->nullable();
            $t->timestamp('executed_at')->useCurrent();
            $t->index(['business_id', 'transaction_id'], 'sale_history_biz_tx_idx');
            $t->index(['business_id', 'executed_at'], 'sale_history_biz_when_idx');
        });
    }
});

afterEach(function () {
    foreach (array_reverse(glob(database_path('migrations/2026_05_11_12*_create_sale_*.php')) ?: []) as $f) {
        (require $f)->down();
    }
    foreach (['transactions', 'activity_log', 'users'] as $tbl) {
        Schema::dropIfExists($tbl);
    }
});

function bulkCriarProcesso(int $bizId, string $key = 'venda_com_producao'): SaleProcess
{
    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $bizId,
        'key' => $key,
        'name' => 'Venda com produção',
        'default_for_contact_type' => 'any',
        'active' => true,
    ]);

    foreach (
        [
            ['quote_draft', 'Rascunho', 0, true],
            ['quote_sent', 'Orçamento Enviado', 1, false],
            ['invoiced', 'Faturada', 2, false],
            ['paid', 'Paga', 3, false],
        ] as [$key, $name, $sort, $isInitial]
    ) {
        SaleProcessStage::create([
            'process_id' => $process->id,
            'key' => $key,
            'name' => $name,
            'sort_order' => $sort,
            'is_initial' => $isInitial,
        ]);
    }

    return $process;
}

function bulkCriarUser(int $bizId): User
{
    return User::forceCreate([
        'username' => 'u_bulk_' . $bizId . '_' . uniqid(),
        'password' => bcrypt('x'),
        'business_id' => $bizId,
    ]);
}

function bulkCriarVenda(int $bizId, string $status, ?string $paymentStatus = null, ?string $subStatus = null): Transaction
{
    $tx = new Transaction;
    $tx->business_id = $bizId;
    $tx->type = 'sell';
    $tx->status = $status;
    $tx->payment_status = $paymentStatus;
    $tx->sub_status = $subStatus;
    $tx->current_stage_id = null;
    $tx->save();

    return $tx;
}

function bulkGetStage(int $bizId, string $stageKey): SaleProcessStage
{
    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', $bizId)
        ->where('key', 'venda_com_producao')
        ->firstOrFail();

    return SaleProcessStage::where('process_id', $process->id)
        ->where('key', $stageKey)
        ->firstOrFail();
}

// ─── Specs ────────────────────────────────────────────────────────────────

it('1. dry-run não persiste — current_stage_id permanece NULL', function () {
    bulkCriarProcesso(1);
    bulkCriarUser(1);
    $venda = bulkCriarVenda(1, 'final', 'paid');

    $this->artisan('fsm:bulk-start-pipeline', [
        'business_id' => 1,
        '--dry-run' => true,
    ])->assertExitCode(0);

    expect($venda->fresh()->current_stage_id)->toBeNull();
    expect(SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)->count())->toBe(0);
});

it('2. migra venda final+paid pra stage paid', function () {
    bulkCriarProcesso(1);
    bulkCriarUser(1);
    $venda = bulkCriarVenda(1, 'final', 'paid');
    $stagePaid = bulkGetStage(1, 'paid');

    $this->artisan('fsm:bulk-start-pipeline', ['business_id' => 1])
        ->assertExitCode(0);

    expect((int) $venda->fresh()->current_stage_id)->toBe($stagePaid->id);

    $hist = SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)
        ->where('transaction_id', $venda->id)
        ->first();
    expect($hist)->not->toBeNull();
    expect((int) $hist->to_stage_id)->toBe($stagePaid->id);
    expect($hist->action_id)->toBeNull();
});

it('3. migra venda final+due pra stage invoiced', function () {
    bulkCriarProcesso(1);
    bulkCriarUser(1);
    $venda = bulkCriarVenda(1, 'final', 'due');
    $stageInvoiced = bulkGetStage(1, 'invoiced');

    $this->artisan('fsm:bulk-start-pipeline', ['business_id' => 1])
        ->assertExitCode(0);

    expect((int) $venda->fresh()->current_stage_id)->toBe($stageInvoiced->id);
});

it('4. migra venda draft+quotation pra stage quote_sent', function () {
    bulkCriarProcesso(1);
    bulkCriarUser(1);
    $venda = bulkCriarVenda(1, 'draft', null, 'quotation');
    $stageQuoteSent = bulkGetStage(1, 'quote_sent');

    $this->artisan('fsm:bulk-start-pipeline', ['business_id' => 1])
        ->assertExitCode(0);

    expect((int) $venda->fresh()->current_stage_id)->toBe($stageQuoteSent->id);
});

it('5. skip vendas já em pipeline (current_stage_id IS NOT NULL)', function () {
    bulkCriarProcesso(1);
    bulkCriarUser(1);
    $stageInvoiced = bulkGetStage(1, 'invoiced');

    // Venda já com stage — deve ser ignorada pela query base do command
    $vendaPipe = bulkCriarVenda(1, 'final', 'paid');
    \DB::table('transactions')->where('id', $vendaPipe->id)
        ->update(['current_stage_id' => $stageInvoiced->id]);

    // Venda nova legada
    $vendaLeg = bulkCriarVenda(1, 'final', 'paid');

    $this->artisan('fsm:bulk-start-pipeline', ['business_id' => 1])
        ->assertExitCode(0);

    // Venda já em pipeline permanece em invoiced (não foi tocada)
    expect((int) $vendaPipe->fresh()->current_stage_id)->toBe($stageInvoiced->id);

    // Venda legada migrou pra paid
    $stagePaid = bulkGetStage(1, 'paid');
    expect((int) $vendaLeg->fresh()->current_stage_id)->toBe($stagePaid->id);

    // Só 1 history entry criada (pra venda legada — a outra já estava em pipeline)
    $histCount = SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)
        ->whereIn('transaction_id', [$vendaPipe->id, $vendaLeg->id])
        ->count();
    expect($histCount)->toBe(1);
});

it('6. cria sale_stage_history com action_id=NULL + payload pipeline_started=true', function () {
    bulkCriarProcesso(1);
    bulkCriarUser(1);
    $venda = bulkCriarVenda(1, 'final', 'paid');

    $this->artisan('fsm:bulk-start-pipeline', ['business_id' => 1])
        ->assertExitCode(0);

    $hist = SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)
        ->where('transaction_id', $venda->id)
        ->first();

    expect($hist)->not->toBeNull();
    expect($hist->action_id)->toBeNull();
    expect($hist->from_stage_id)->toBeNull();
    expect($hist->payload_snapshot)->toBeArray();
    expect($hist->payload_snapshot['pipeline_started'] ?? null)->toBeTrue();
    expect($hist->payload_snapshot['process_key'] ?? null)->toBe('venda_com_producao');
    expect($hist->payload_snapshot['bulk_command'] ?? null)->toBe('fsm:bulk-start-pipeline');
});

it('7. business sem processo cadastrado retorna erro (exit 1)', function () {
    // Sem bulkCriarProcesso — biz 1 não tem processo
    bulkCriarUser(1);
    bulkCriarVenda(1, 'final', 'paid');

    $this->artisan('fsm:bulk-start-pipeline', ['business_id' => 1])
        ->expectsOutputToContain("Processo 'venda_com_producao' não cadastrado")
        ->assertExitCode(1);
});
