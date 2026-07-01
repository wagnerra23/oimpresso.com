<?php

declare(strict_types=1);

// @covers-us US-SELL-034

use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageAction;
use App\Domain\Fsm\Models\SaleStageHistory;
use App\Domain\Fsm\Models\StockReservation;
use App\Domain\Fsm\Models\TransactionDocument;
use App\Domain\Fsm\Services\ExecuteStageActionService;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * CU-05 (G6) — CancelarVendaCascade side-effect orquestra:
 *   - CancelarNfeJob (via NfeBrasil — não pula sequencial, CU-01)
 *   - EstornarBoletoJob (Asaas/Inter — US-CASCADE-BOLETO-002 integrado)
 *   - LiberarReserva (estoque)
 *   - NotificarClienteJob (WhatsApp/email)
 *
 * Specs FAILING-FIRST:
 *   1. App\Domain\Fsm\SideEffects\CancelarVendaCascade ainda não existe
 *   2. Jobs CancelarNfeJob / EstornarBoletoJob / NotificarClienteJob ainda não existem
 *
 * Pain point Wagner 2026-05-12:
 *   "cancelam nota" — implícito: cancelar venda é processo que envolve
 *   múltiplos sistemas e hoje é manual + propenso a inconsistência.
 *
 * Ver: memory/requisitos/Sells/CASOS-USO-PIPELINE-VENDAS.md §CU-05
 */

class CancelTestSubject extends Model
{
    protected $table = 'cancel_test_subjects';

    protected $guarded = ['id'];

    public $timestamps = false;
}

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    Schema::create('users', function (Blueprint $t) {
        $t->increments('id');
        $t->string('username')->unique();
        $t->string('password');
        $t->integer('business_id')->nullable();
        $t->rememberToken();
        $t->softDeletes();
        $t->timestamps();
    });
    Schema::create('cancel_test_subjects', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id');
        $t->unsignedBigInteger('current_stage_id')->nullable();
    });
    foreach (['permissions', 'roles'] as $tbl) {
        Schema::create($tbl, function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name');
            $t->string('guard_name');
            $t->timestamps();
            $t->unique(['name', 'guard_name']);
        });
    }
    Schema::create('model_has_roles', function (Blueprint $t) {
        $t->unsignedBigInteger('role_id');
        $t->string('model_type');
        $t->unsignedBigInteger('model_id');
        $t->primary(['role_id', 'model_id', 'model_type'], 'mhr_pk');
    });
    Schema::create('model_has_permissions', function (Blueprint $t) {
        $t->unsignedBigInteger('permission_id');
        $t->string('model_type');
        $t->unsignedBigInteger('model_id');
        $t->primary(['permission_id', 'model_id', 'model_type'], 'mhp_pk');
    });
    Schema::create('role_has_permissions', function (Blueprint $t) {
        $t->unsignedBigInteger('permission_id');
        $t->unsignedBigInteger('role_id');
        $t->primary(['permission_id', 'role_id']);
    });

    foreach (glob(database_path('migrations/2026_05_11_12*_create_sale_*.php')) ?: [] as $f) {
        (require $f)->up();
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        foreach (array_reverse(glob(database_path('migrations/2026_05_11_12*_create_sale_*.php')) ?: []) as $f) {
            (require $f)->down();
        }
        foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions', 'cancel_test_subjects', 'users'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
});

function cancelSetup(int $bizId): array
{
    $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $bizId, 'key' => 'venda_com_producao',
        'name' => 'Venda Com Produção', 'default_for_contact_type' => 'any', 'active' => true,
    ]);

    $invoiced = SaleProcessStage::create([
        'process_id' => $process->id, 'key' => 'invoiced',
        'name' => 'Faturada', 'sort_order' => 5, 'is_initial' => true,
    ]);
    $cancelled = SaleProcessStage::create([
        'process_id' => $process->id, 'key' => 'cancelled',
        'name' => 'Cancelada', 'sort_order' => 10, 'is_terminal' => true,
    ]);

    SaleStageAction::create([
        'stage_id' => $invoiced->id,
        'key' => 'cancelar_venda',
        'label' => 'Cancelar venda',
        'target_stage_id' => $cancelled->id,
        'side_effect_class' => \App\Domain\Fsm\SideEffects\CancelarVendaCascade::class,
    ]);

    return compact('process', 'invoiced', 'cancelled');
}

function cancelSubject(int $bizId, int $stageId): CancelTestSubject
{
    $s = new CancelTestSubject;
    $s->business_id = $bizId;
    $s->current_stage_id = $stageId;
    $s->save();
    return $s;
}

function cancelUser(int $bizId): User
{
    Role::findOrCreate('vendas.gerente', 'web');
    $u = User::forceCreate(['username' => 'u' . uniqid(), 'password' => bcrypt('x'), 'business_id' => $bizId]);
    $u->assignRole('vendas.gerente');
    return $u;
}

// ─── Specs ────────────────────────────────────────────────────────────────

it('1. cancelar_venda com NFe+reserva dispara CancelarNfeJob + libera reserva + notifica', function () {
    Bus::fake();

    ['invoiced' => $inv, 'cancelled' => $cancelled] = cancelSetup(1);
    $subject = cancelSubject(1, $inv->id);

    // Seed: NFe + reserva ligados ao subject (sem boleto neste spec)
    TransactionDocument::create([
        'business_id' => 1, 'transaction_id' => $subject->id,
        'doc_type' => 'nfe55', 'doc_class' => 'Modules\NfeBrasil\Models\NfeEmissao',
        'doc_id' => 1, 'status' => 'authorized',
    ]);
    StockReservation::create([
        'business_id' => 1, 'transaction_id' => $subject->id,
        'product_id' => 1, 'qty_reserved' => 5, 'status' => 'active',
    ]);

    (new ExecuteStageActionService)->execute($subject, 'cancelar_venda', cancelUser(1), [
        'motivo' => 'Cliente desistiu',
    ]);

    Bus::assertDispatched(\Modules\NfeBrasil\Jobs\CancelarNfeJob::class);
    Bus::assertNotDispatched(\App\Jobs\EstornarBoletoJob::class);
    Bus::assertDispatched(\Modules\Whatsapp\Jobs\NotificarClienteCancelamentoJob::class);

    // Reserva liberada (síncrono via LiberarReserva)
    $reservation = StockReservation::where('transaction_id', $subject->id)->first();
    expect($reservation->status)->toBe('released');

    // Stage moveu pra cancelled
    expect($subject->fresh()->current_stage_id)->toBe($cancelled->id);
});

it('2. cancelar venda sem NFe não dispara CancelarNfeJob (idempotência conceitual)', function () {
    Bus::fake();

    ['invoiced' => $inv] = cancelSetup(1);
    $subject = cancelSubject(1, $inv->id);

    // Sem TransactionDocument — venda sem nota
    (new ExecuteStageActionService)->execute($subject, 'cancelar_venda', cancelUser(1), [
        'motivo' => 'Venda sem nota — desistência',
    ]);

    Bus::assertNotDispatched(\Modules\NfeBrasil\Jobs\CancelarNfeJob::class);
});

it('3. NFe já cancelada antes (status=cancelled) não duplica CancelarNfeJob', function () {
    Bus::fake();

    ['invoiced' => $inv] = cancelSetup(1);
    $subject = cancelSubject(1, $inv->id);

    TransactionDocument::create([
        'business_id' => 1, 'transaction_id' => $subject->id,
        'doc_type' => 'nfe55', 'doc_class' => 'Modules\NfeBrasil\Models\NfeEmissao',
        'doc_id' => 1,
        'status' => 'cancelled', // já cancelada antes via tela NFe
    ]);

    (new ExecuteStageActionService)->execute($subject, 'cancelar_venda', cancelUser(1), ['motivo' => 'X']);

    Bus::assertNotDispatched(\Modules\NfeBrasil\Jobs\CancelarNfeJob::class);
});

it('4. NFe + reserva ativa: ambos efeitos rodam independente (best-effort)', function () {
    Bus::fake();

    ['invoiced' => $inv] = cancelSetup(1);
    $subject = cancelSubject(1, $inv->id);

    TransactionDocument::create([
        'business_id' => 1, 'transaction_id' => $subject->id,
        'doc_type' => 'nfe55', 'doc_class' => 'Modules\NfeBrasil\Models\NfeEmissao',
        'doc_id' => 1, 'status' => 'authorized',
    ]);
    StockReservation::create([
        'business_id' => 1, 'transaction_id' => $subject->id,
        'product_id' => 1, 'qty_reserved' => 5, 'status' => 'active',
    ]);

    (new ExecuteStageActionService)->execute($subject, 'cancelar_venda', cancelUser(1), ['motivo' => 'Y']);

    // CancelarNfeJob disparado
    Bus::assertDispatched(\Modules\NfeBrasil\Jobs\CancelarNfeJob::class);
    // Reserva liberada (síncrono)
    expect(StockReservation::find(1)->status)->toBe('released');
    // Notificação cliente disparada
    Bus::assertDispatched(\Modules\Whatsapp\Jobs\NotificarClienteCancelamentoJob::class);
});

it('5. motivo é registrado em payload_snapshot do sale_stage_history', function () {
    Bus::fake();

    ['invoiced' => $inv] = cancelSetup(1);
    $subject = cancelSubject(1, $inv->id);

    (new ExecuteStageActionService)->execute($subject, 'cancelar_venda', cancelUser(1), [
        'motivo' => 'Cliente arrependeu da compra após 24h',
    ]);

    $history = SaleStageHistory::where('business_id', 1)
        ->where('transaction_id', $subject->id)
        ->latest('executed_at')->first();

    expect($history)->not->toBeNull();
    expect($history->payload_snapshot)
        ->toBeArray()
        ->toHaveKey('motivo', 'Cliente arrependeu da compra após 24h');
});

it('6. cancelar venda com boleto pending dispatch EstornarBoletoJob', function () {
    // US-CASCADE-BOLETO-002 — hub EstornarBoletoJob é integrado no cascade.
    Bus::fake();

    ['invoiced' => $inv, 'cancelled' => $cancelled] = cancelSetup(1);
    $subject = cancelSubject(1, $inv->id);

    // Seed: 1 boleto Asaas pending + 1 boleto Inter pending + 1 boleto Asaas
    // já cancelled (não deve duplicar) + 1 NFe authorized (cascade paralelo)
    $boletoAsaas = TransactionDocument::create([
        'business_id' => 1, 'transaction_id' => $subject->id,
        'doc_type' => TransactionDocument::DOC_BOLETO_ASAAS,
        'doc_class' => 'Modules\\RecurringBilling\\Models\\AsaasCharge',
        'doc_id' => 10, 'status' => TransactionDocument::STATUS_PENDING,
    ]);
    $boletoInter = TransactionDocument::create([
        'business_id' => 1, 'transaction_id' => $subject->id,
        'doc_type' => TransactionDocument::DOC_BOLETO_INTER,
        'doc_class' => 'App\\Models\\InterCharge',
        'doc_id' => 20, 'status' => TransactionDocument::STATUS_PENDING,
    ]);
    TransactionDocument::create([
        'business_id' => 1, 'transaction_id' => $subject->id,
        'doc_type' => TransactionDocument::DOC_BOLETO_ASAAS,
        'doc_class' => 'Modules\\RecurringBilling\\Models\\AsaasCharge',
        'doc_id' => 30, 'status' => TransactionDocument::STATUS_CANCELLED,
    ]);
    TransactionDocument::create([
        'business_id' => 1, 'transaction_id' => $subject->id,
        'doc_type' => 'nfe55', 'doc_class' => 'Modules\NfeBrasil\Models\NfeEmissao',
        'doc_id' => 1, 'status' => 'authorized',
    ]);

    (new ExecuteStageActionService)->execute($subject, 'cancelar_venda', cancelUser(1), [
        'motivo' => 'Cliente cancelou — estornar boletos',
    ]);

    // Só os 2 boletos PENDING geram dispatch — boleto já cancelled não duplica
    Bus::assertDispatchedTimes(\App\Jobs\EstornarBoletoJob::class, 2);

    Bus::assertDispatched(\App\Jobs\EstornarBoletoJob::class, function ($job) use ($boletoAsaas) {
        return $job->businessId === 1
            && $job->transactionDocumentId === (int) $boletoAsaas->id
            && $job->motivo === 'Cliente cancelou — estornar boletos';
    });
    Bus::assertDispatched(\App\Jobs\EstornarBoletoJob::class, function ($job) use ($boletoInter) {
        return $job->businessId === 1
            && $job->transactionDocumentId === (int) $boletoInter->id;
    });

    // Cascade paralelo segue rodando — NFe e notificação não bloqueiam
    Bus::assertDispatched(\Modules\NfeBrasil\Jobs\CancelarNfeJob::class);
    Bus::assertDispatched(\Modules\Whatsapp\Jobs\NotificarClienteCancelamentoJob::class);

    // Stage moveu pra cancelled
    expect($subject->fresh()->current_stage_id)->toBe($cancelled->id);
});

it('7. cancelar venda sem boleto não dispara EstornarBoletoJob', function () {
    Bus::fake();

    ['invoiced' => $inv] = cancelSetup(1);
    $subject = cancelSubject(1, $inv->id);

    // Sem TransactionDocument boleto_* — só NFe
    TransactionDocument::create([
        'business_id' => 1, 'transaction_id' => $subject->id,
        'doc_type' => 'nfe55', 'doc_class' => 'Modules\NfeBrasil\Models\NfeEmissao',
        'doc_id' => 1, 'status' => 'authorized',
    ]);

    (new ExecuteStageActionService)->execute($subject, 'cancelar_venda', cancelUser(1), [
        'motivo' => 'Sem boleto',
    ]);

    Bus::assertNotDispatched(\App\Jobs\EstornarBoletoJob::class);
});
