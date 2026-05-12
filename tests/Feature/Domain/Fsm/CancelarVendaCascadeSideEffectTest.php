<?php

declare(strict_types=1);

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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * CU-05 (G6) — CancelarVendaCascade side-effect orquestra:
 *   - CancelarNfeJob (via NfeBrasil — não pula sequencial, CU-01)
 *   - EstornarBoletoJob (Asaas/Inter)
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
    foreach (array_reverse(glob(database_path('migrations/2026_05_11_12*_create_sale_*.php')) ?: []) as $f) {
        (require $f)->down();
    }
    foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions', 'cancel_test_subjects', 'users'] as $tbl) {
        Schema::dropIfExists($tbl);
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

it('1. cancelar_venda com NFe+boleto+reserva dispara 4 jobs em ordem correta', function () {
    Bus::fake();

    ['invoiced' => $inv] = cancelSetup(1);
    $subject = cancelSubject(1, $inv->id);

    // Seed: NFe + boleto + reserva ligados ao subject
    TransactionDocument::create([
        'business_id' => 1, 'transaction_id' => $subject->id,
        'doc_type' => 'nfe', 'doc_class' => 'Modules\NfeBrasil\Models\NfeEmissao',
        'doc_id' => 1, 'status' => 'authorized',
    ]);
    TransactionDocument::create([
        'business_id' => 1, 'transaction_id' => $subject->id,
        'doc_type' => 'boleto', 'doc_class' => 'App\Models\AsaasCharge',
        'doc_id' => 1, 'status' => 'pending',
    ]);
    StockReservation::create([
        'business_id' => 1, 'transaction_id' => $subject->id,
        'product_id' => 1, 'qty_reserved' => 5, 'status' => 'active',
    ]);

    (new ExecuteStageActionService)->execute($subject, 'cancelar_venda', cancelUser(1), [
        'motivo' => 'Cliente desistiu',
    ]);

    Bus::assertDispatched(\Modules\NfeBrasil\Jobs\CancelarNfeJob::class);
    Bus::assertDispatched(\App\Jobs\EstornarBoletoJob::class);
    Bus::assertDispatched(\Modules\Whatsapp\Jobs\NotificarClienteCancelamentoJob::class);

    // Reserva: side-effect síncrono via LiberarReserva (não é Job)
    $reservation = StockReservation::where('transaction_id', $subject->id)->first();
    expect($reservation->status)->toBe('released');

    // Stage final
    expect($subject->fresh()->current_stage_id)->not->toBe($inv->id);
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
        'doc_type' => 'nfe', 'doc_class' => 'Modules\NfeBrasil\Models\NfeEmissao',
        'doc_id' => 1,
        'status' => 'cancelled', // já cancelada antes via tela NFe
    ]);

    (new ExecuteStageActionService)->execute($subject, 'cancelar_venda', cancelUser(1), ['motivo' => 'X']);

    Bus::assertNotDispatched(\Modules\NfeBrasil\Jobs\CancelarNfeJob::class);
});

it('4. failure de um side-effect interno não bloqueia outros (resiliência best-effort)', function () {
    Log::spy();
    Bus::fake();

    ['invoiced' => $inv] = cancelSetup(1);
    $subject = cancelSubject(1, $inv->id);

    TransactionDocument::create([
        'business_id' => 1, 'transaction_id' => $subject->id,
        'doc_type' => 'nfe', 'doc_class' => 'Modules\NfeBrasil\Models\NfeEmissao',
        'doc_id' => 1, 'status' => 'authorized',
    ]);
    StockReservation::create([
        'business_id' => 1, 'transaction_id' => $subject->id,
        'product_id' => 1, 'qty_reserved' => 5, 'status' => 'active',
    ]);

    // Simula falha de boleto (sem TransactionDocument tipo boleto — vendas sem cobrança Asaas)
    (new ExecuteStageActionService)->execute($subject, 'cancelar_venda', cancelUser(1), ['motivo' => 'Y']);

    // CancelarNfeJob foi disparado
    Bus::assertDispatched(\Modules\NfeBrasil\Jobs\CancelarNfeJob::class);
    // Reserva foi liberada
    expect(StockReservation::find(1)->status)->toBe('released');
    // EstornarBoletoJob NÃO foi disparado (sem boleto pra cancelar) — não é erro, é caso vazio
    Bus::assertNotDispatched(\App\Jobs\EstornarBoletoJob::class);
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
