<?php

declare(strict_types=1);

use App\Domain\Fsm\Models\TransactionDocument;
use App\Jobs\EstornarBoletoJob;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Spatie\Permission\PermissionRegistrar;

/**
 * US-CASCADE-BOLETO-001 — EstornarBoletoJob hub de cancelamento de cobrança.
 *
 * Cobre os 5 cenários canônicos:
 *   1. Happy-path Asaas: documento marcado cancelled
 *   2. Idempotência: já cancelado é no-op + log info
 *   3. doc_type inválido (não-boleto): RuntimeException
 *   4. Cross-tenant: businessId != document.business_id, RuntimeException
 *   5. Dispatch gateway: Cancelar*Job real despachado quando classe existe
 *
 * Default biz=1; cross-tenant adversário biz=99 (convenção ADR 0101).
 */

/**
 * Stub poly target (NfeEmissao / NfseEmissao / Charge equivalent).
 */
class FakeBoletoCharge extends Model
{
    protected $table = 'fake_boleto_charges';

    protected $guarded = ['id'];

    public $timestamps = false;
}

/**
 * Stub Job que simula CancelarCobrancaAsaasJob existindo na app.
 *
 * Definido como classe top-level via class_alias() abaixo (não dentro do
 * teste pra evitar 'class already declared' em reruns).
 */
class FakeCancelarCobrancaAsaasJobStub
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $documentId,
        public readonly string $motivo,
    ) {}
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

    Schema::create('fake_boleto_charges', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id');
        $t->string('gateway_id', 60)->nullable();
    });

    // Spatie Permission tabelas (HasBusinessScope toca auth())
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
        $t->primary(['permission_id', 'model_id', 'model_type'], 'mhp_pk_ebj');
    });
    Schema::create('model_has_roles', function (Blueprint $t) {
        $t->unsignedBigInteger('role_id');
        $t->string('model_type');
        $t->unsignedBigInteger('model_id');
        $t->primary(['role_id', 'model_id', 'model_type'], 'mhr_pk_ebj');
    });
    Schema::create('role_has_permissions', function (Blueprint $t) {
        $t->unsignedBigInteger('permission_id');
        $t->unsignedBigInteger('role_id');
        $t->primary(['permission_id', 'role_id']);
    });

    // Schema da tabela transaction_documents — replica criação + ALTER do boleto.
    // Como SQLite não suporta ENUM ALTER, criamos já com VARCHAR equivalente
    // (Pest dual-mode: SQLite local, MySQL CI — schema é o mesmo a nível Eloquent).
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
    foreach (['transaction_documents', 'role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions', 'fake_boleto_charges', 'users'] as $tbl) {
        Schema::dropIfExists($tbl);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function ebjCriarUser(int $bizId): User
{
    return User::forceCreate([
        'username' => 'ebj_biz' . $bizId . '_' . uniqid(),
        'password' => bcrypt('x'),
        'business_id' => $bizId,
    ]);
}

function ebjCriarCharge(int $bizId, string $gatewayId = 'pay_xpto'): FakeBoletoCharge
{
    $c = new FakeBoletoCharge;
    $c->business_id = $bizId;
    $c->gateway_id = $gatewayId;
    $c->save();

    return $c;
}

function ebjCriarDocumento(int $bizId, int $transactionId, string $docType, int $docId, string $value, string $status = TransactionDocument::STATUS_PENDING): TransactionDocument
{
    return TransactionDocument::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $bizId,
        'transaction_id' => $transactionId,
        'doc_type' => $docType,
        'doc_class' => FakeBoletoCharge::class,
        'doc_id' => $docId,
        'value_total' => $value,
        'status' => $status,
    ]);
}

it('1. handler atualiza TransactionDocument.status=cancelled para doc_type=boleto_asaas', function () {
    $charge = ebjCriarCharge(1, 'pay_abc');
    $doc = ebjCriarDocumento(1, 1001, TransactionDocument::DOC_BOLETO_ASAAS, $charge->id, '150.0000');

    $this->actingAs(ebjCriarUser(1));
    session(['user.business_id' => 1]);

    Bus::fake(); // evita despachar Cancelar*Job real (a classe nem existe ainda)

    $job = new EstornarBoletoJob(
        businessId: 1,
        transactionDocumentId: $doc->id,
        motivo: 'venda cancelada — teste',
    );
    $job->handle();

    $reload = TransactionDocument::withoutGlobalScope(ScopeByBusiness::class)->find($doc->id);

    expect($reload->status)->toBe(TransactionDocument::STATUS_CANCELLED);
    expect($reload->doc_type)->toBe('boleto_asaas');
});

it('2. idempotência: documento já cancelled loga info e não chama gateway', function () {
    $charge = ebjCriarCharge(1, 'pay_idem');
    $doc = ebjCriarDocumento(
        1,
        1002,
        TransactionDocument::DOC_BOLETO_ASAAS,
        $charge->id,
        '99.9900',
        TransactionDocument::STATUS_CANCELLED, // já cancelado
    );

    $this->actingAs(ebjCriarUser(1));
    session(['user.business_id' => 1]);

    Bus::fake();
    Log::spy();

    $job = new EstornarBoletoJob(
        businessId: 1,
        transactionDocumentId: $doc->id,
        motivo: 'rerun idempotente',
    );
    $job->handle();

    // Nada despachado — gateway não foi chamado
    Bus::assertNothingDispatched();

    // Log.info chamado com payload de no-op
    Log::shouldHaveReceived('info')
        ->withArgs(fn ($message) => str_contains((string) $message, 'já cancelado'))
        ->atLeast()
        ->once();
});

it('3. doc_type inválido (não boleto_*) lança exception', function () {
    $charge = ebjCriarCharge(1, 'pay_inv');
    // Documento NFe55 — não é boleto, EstornarBoletoJob deve rejeitar
    $doc = ebjCriarDocumento(1, 1003, TransactionDocument::DOC_NFE55, $charge->id, '500.0000');

    $this->actingAs(ebjCriarUser(1));
    session(['user.business_id' => 1]);

    Bus::fake();

    $job = new EstornarBoletoJob(
        businessId: 1,
        transactionDocumentId: $doc->id,
        motivo: 'teste tipo inválido',
    );

    expect(fn () => $job->handle())
        ->toThrow(RuntimeException::class, "doc_type inválido pra estorno: 'nfe55'");
});

it('4. cross-tenant: businessId != document.business_id lança exception', function () {
    $charge = ebjCriarCharge(99, 'pay_other_tenant');
    $doc = ebjCriarDocumento(99, 1004, TransactionDocument::DOC_BOLETO_INTER, $charge->id, '200.0000');

    $this->actingAs(ebjCriarUser(1));
    session(['user.business_id' => 1]);

    Bus::fake();

    // Job claim biz=1 mas o documento é de biz=99 → guard dispara
    $job = new EstornarBoletoJob(
        businessId: 1,
        transactionDocumentId: $doc->id,
        motivo: 'cross-tenant ataque',
    );

    expect(fn () => $job->handle())
        ->toThrow(RuntimeException::class, 'Cross-tenant violation');

    // Documento NÃO foi atualizado
    $reload = TransactionDocument::withoutGlobalScope(ScopeByBusiness::class)->find($doc->id);
    expect($reload->status)->toBe(TransactionDocument::STATUS_PENDING);
});

it('5. dispatch CancelarCobrancaAsaasJob se classe existir (mock via class_alias)', function () {
    // Cria alias do Stub pro FQCN real esperado — class_exists() retorna true
    // e EstornarBoletoJob despacha como se Job real existisse.
    $expectedFqcn = '\\Modules\\RecurringBilling\\Jobs\\CancelarCobrancaAsaasJob';
    $expectedClass = ltrim($expectedFqcn, '\\');

    if (! class_exists($expectedClass)) {
        class_alias(FakeCancelarCobrancaAsaasJobStub::class, $expectedClass);
    }

    $charge = ebjCriarCharge(1, 'pay_dispatch');
    $doc = ebjCriarDocumento(1, 1005, TransactionDocument::DOC_BOLETO_ASAAS, $charge->id, '777.0000');

    $this->actingAs(ebjCriarUser(1));
    session(['user.business_id' => 1]);

    Bus::fake();

    $job = new EstornarBoletoJob(
        businessId: 1,
        transactionDocumentId: $doc->id,
        motivo: 'venda cancelada — dispatch real',
    );
    $job->handle();

    // Cancelar*Job deve ter sido despachado pelo Bus
    Bus::assertDispatched(
        $expectedClass,
        function ($dispatched) use ($doc) {
            return $dispatched->businessId === 1
                && $dispatched->documentId === $doc->id
                && $dispatched->motivo === 'venda cancelada — dispatch real';
        }
    );

    // E o documento foi marcado cancelled
    $reload = TransactionDocument::withoutGlobalScope(ScopeByBusiness::class)->find($doc->id);
    expect($reload->status)->toBe(TransactionDocument::STATUS_CANCELLED);
});
