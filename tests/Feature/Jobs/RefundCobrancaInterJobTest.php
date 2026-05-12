<?php

declare(strict_types=1);

use App\Domain\Fsm\Models\TransactionDocument;
use App\Jobs\RefundCobrancaInterJob;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Spatie\Permission\PermissionRegistrar;

/**
 * US-CASCADE-BOLETO-006 — RefundCobrancaInterJob (stub honesto Inter PJ).
 *
 * Inter PJ NÃO tem endpoint nativo de refund — Job só loga + (best-effort)
 * registra em manual_actions_queue pra UI admin processar TED/PIX manual.
 *
 * Cobre 3 cenários:
 *   1. handle() loga "ação manual necessária"
 *   2. Cross-tenant guard
 *   3. doc_type != boleto_inter → RuntimeException
 */
class FakeInterRefundCharge extends Model
{
    protected $table = 'fake_inter_refund_charges';

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

    Schema::create('fake_inter_refund_charges', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id');
        $t->string('nosso_numero', 60)->nullable();
        $t->string('status', 20)->nullable();
    });

    Schema::create('permissions', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('name');
        $t->string('guard_name');
        $t->timestamps();
        $t->unique(['name', 'guard_name']);
    });
    Schema::create('roles', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('name');
        $t->string('guard_name');
        $t->timestamps();
        $t->unique(['name', 'guard_name']);
    });
    Schema::create('model_has_permissions', function (Blueprint $t) {
        $t->unsignedBigInteger('permission_id');
        $t->string('model_type');
        $t->unsignedBigInteger('model_id');
        $t->primary(['permission_id', 'model_id', 'model_type'], 'mhp_pk_rcij');
    });
    Schema::create('model_has_roles', function (Blueprint $t) {
        $t->unsignedBigInteger('role_id');
        $t->string('model_type');
        $t->unsignedBigInteger('model_id');
        $t->primary(['role_id', 'model_id', 'model_type'], 'mhr_pk_rcij');
    });
    Schema::create('role_has_permissions', function (Blueprint $t) {
        $t->unsignedBigInteger('permission_id');
        $t->unsignedBigInteger('role_id');
        $t->primary(['permission_id', 'role_id']);
    });

    Schema::create('transaction_documents', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id');
        $t->unsignedBigInteger('transaction_id');
        $t->string('doc_type', 20);
        $t->string('doc_class', 255);
        $t->unsignedBigInteger('doc_id');
        $t->decimal('value_total', 22, 4);
        $t->timestamp('emitted_at')->nullable();
        $t->string('status', 20)->default('pending');
        $t->timestamps();

        $t->unique(['transaction_id', 'doc_type', 'doc_id'], 'tx_docs_tx_type_doc_uq');
        $t->index(['business_id', 'transaction_id'], 'tx_docs_biz_tx_idx');
    });

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function () {
    foreach (['transaction_documents', 'role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions', 'fake_inter_refund_charges', 'users'] as $tbl) {
        Schema::dropIfExists($tbl);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function rcijCriarUser(int $bizId): User
{
    return User::forceCreate([
        'username' => 'rcij_biz' . $bizId . '_' . uniqid(),
        'password' => bcrypt('x'),
        'business_id' => $bizId,
    ]);
}

function rcijCriarCharge(int $bizId, string $nossoNumero = '00012345', ?string $status = 'paid'): FakeInterRefundCharge
{
    $c = new FakeInterRefundCharge;
    $c->business_id = $bizId;
    $c->nosso_numero = $nossoNumero;
    $c->status = $status;
    $c->save();

    return $c;
}

function rcijCriarDocumento(int $bizId, int $txId, string $docType, int $docId, string $value = '150.0000', string $status = TransactionDocument::STATUS_PENDING): TransactionDocument
{
    return TransactionDocument::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $bizId,
        'transaction_id' => $txId,
        'doc_type' => $docType,
        'doc_class' => FakeInterRefundCharge::class,
        'doc_id' => $docId,
        'value_total' => $value,
        'status' => $status,
    ]);
}

it('1. handle() loga warning "ação manual necessária" (Inter sem endpoint refund)', function () {
    $charge = rcijCriarCharge(1, '00099887766', 'paid');
    $doc = rcijCriarDocumento(1, 4001, TransactionDocument::DOC_BOLETO_INTER, $charge->id);

    $this->actingAs(rcijCriarUser(1));
    session(['user.business_id' => 1]);

    Log::spy();

    $job = new RefundCobrancaInterJob(
        businessId: 1,
        transactionDocumentId: $doc->id,
        motivo: 'venda cancelada — refund Inter',
    );
    $job->handle();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($message) => str_contains((string) $message, 'requer ação manual'))
        ->atLeast()
        ->once();
});

it('2. cross-tenant guard: biz=1 Job tentando documento biz=99 → RuntimeException', function () {
    $charge = rcijCriarCharge(99, '00077777777', 'paid');
    $doc = rcijCriarDocumento(99, 4002, TransactionDocument::DOC_BOLETO_INTER, $charge->id);

    $this->actingAs(rcijCriarUser(1));
    session(['user.business_id' => 1]);

    $job = new RefundCobrancaInterJob(
        businessId: 1, // Job claim biz=1 mas doc é de biz=99
        transactionDocumentId: $doc->id,
        motivo: 'cross-tenant ataque refund inter',
    );

    expect(fn () => $job->handle())
        ->toThrow(RuntimeException::class, 'Cross-tenant violation');
});

it('3. doc_type != boleto_inter → RuntimeException', function () {
    $charge = rcijCriarCharge(1, '00055555555', 'paid');
    // Tipo errado pra esse Job — usa boleto_asaas
    $doc = rcijCriarDocumento(1, 4003, TransactionDocument::DOC_BOLETO_ASAAS, $charge->id);

    $this->actingAs(rcijCriarUser(1));
    session(['user.business_id' => 1]);

    $job = new RefundCobrancaInterJob(
        businessId: 1,
        transactionDocumentId: $doc->id,
        motivo: 'tipo errado refund inter',
    );

    expect(fn () => $job->handle())
        ->toThrow(RuntimeException::class, "doc_type inválido pra RefundCobrancaInterJob: 'boleto_asaas'");
});
