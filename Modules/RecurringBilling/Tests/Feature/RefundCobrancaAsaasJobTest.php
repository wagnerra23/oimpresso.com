<?php

declare(strict_types=1);

use App\Domain\Fsm\Models\TransactionDocument;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\RecurringBilling\Jobs\RefundCobrancaAsaasJob;
use Modules\RecurringBilling\Models\BoletoCredential;
use Modules\RecurringBilling\Services\Boleto\BoletoService;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class);

/**
 * US-CASCADE-BOLETO-005 — RefundCobrancaAsaasJob (refund Asaas — charge paga).
 *
 * Cobre 5 cenários canônicos:
 *   1. Happy-path: POST /payments/{id}/refund executado (flag ON + Http::fake)
 *   2. Idempotência: charge já REFUNDED (fetch retorna status) → no-op
 *   3. Flag ASAAS_REFUND_ENABLED=false → NÃO chama refund, só loga TODO
 *   4. Cross-tenant: businessId != document.business_id → RuntimeException
 *   5. doc_type != boleto_asaas → RuntimeException
 *
 * Default biz=1; cross-tenant adversário biz=99 (convenção ADR 0101).
 * Pattern espelha CancelarCobrancaAsaasJobTest.
 */
class FakeAsaasRefundChargeStub extends Model
{
    protected $table = 'fake_asaas_refund_charges';

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

    Schema::create('fake_asaas_refund_charges', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id');
        $t->string('gateway_ref', 60)->nullable(); // pay_xxx Asaas
        $t->string('status', 20)->nullable();
    });

    Schema::create('rb_boleto_credentials', function ($table) {
        $table->id();
        $table->unsignedInteger('business_id')->index();
        $table->unsignedInteger('conta_bancaria_id')->nullable();
        $table->string('banco', 30);
        $table->string('ambiente', 20)->default('production');
        $table->boolean('ativo')->default(true);
        $table->string('nome_display')->nullable();
        $table->json('config_json');
        $table->timestamps();
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
        $t->primary(['permission_id', 'model_id', 'model_type'], 'mhp_pk_rcaj');
    });
    Schema::create('model_has_roles', function (Blueprint $t) {
        $t->unsignedBigInteger('role_id');
        $t->string('model_type');
        $t->unsignedBigInteger('model_id');
        $t->primary(['role_id', 'model_id', 'model_type'], 'mhr_pk_rcaj');
    });
    Schema::create('role_has_permissions', function (Blueprint $t) {
        $t->unsignedBigInteger('permission_id');
        $t->unsignedBigInteger('role_id');
        $t->primary(['permission_id', 'role_id']);
    });

    // Wave 10 D7 LGPD: BoletoCredential dispara LogsActivity (Spatie) ao create.
    Schema::create('activity_log', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('log_name')->nullable()->index();
        $t->text('description')->nullable();
        $t->nullableMorphs('subject', 'subject');
        $t->string('event')->nullable();
        $t->nullableMorphs('causer', 'causer');
        $t->longText('properties')->nullable();
        $t->uuid('batch_uuid')->nullable();
        $t->timestamps();
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
    foreach (['transaction_documents', 'activity_log', 'role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions', 'fake_asaas_refund_charges', 'rb_boleto_credentials', 'users'] as $tbl) {
        Schema::dropIfExists($tbl);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function rcajCriarCredencialAsaas(int $bizId): void
{
    BoletoCredential::create([
        'business_id' => $bizId,
        'banco' => 'asaas',
        'ambiente' => 'sandbox',
        'ativo' => true,
        'nome_display' => 'Asaas Teste',
        'config_json' => [
            'api_key' => Crypt::encryptString('$aact_refund_token'),
            'ambiente' => 'sandbox',
        ],
    ]);
}

function rcajCriarUser(int $bizId): User
{
    return User::forceCreate([
        'username' => 'rcaj_biz' . $bizId . '_' . uniqid(),
        'password' => bcrypt('x'),
        'business_id' => $bizId,
    ]);
}

function rcajCriarCharge(int $bizId, string $gatewayRef = 'pay_paid_charge', ?string $status = null): FakeAsaasRefundChargeStub
{
    $c = new FakeAsaasRefundChargeStub;
    $c->business_id = $bizId;
    $c->gateway_ref = $gatewayRef;
    $c->status = $status;
    $c->save();

    return $c;
}

function rcajCriarDocumento(int $bizId, int $txId, string $docType, int $docId, string $value = '150.0000', string $status = TransactionDocument::STATUS_PENDING): TransactionDocument
{
    return TransactionDocument::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $bizId,
        'transaction_id' => $txId,
        'doc_type' => $docType,
        'doc_class' => FakeAsaasRefundChargeStub::class,
        'doc_id' => $docId,
        'value_total' => $value,
        'status' => $status,
    ]);
}

it('1. refund Asaas paid charge via POST /payments/{id}/refund (mock Http)', function () {
    Config::set('services.asaas.refund_enabled', true);
    rcajCriarCredencialAsaas(1);
    $charge = rcajCriarCharge(1, 'pay_to_refund', 'paid');
    $doc = rcajCriarDocumento(1, 3001, TransactionDocument::DOC_BOLETO_ASAAS, $charge->id);

    $this->actingAs(rcajCriarUser(1));
    session(['user.business_id' => 1]);

    Http::fake([
        // fetchPayment (GET) — Asaas retorna status RECEIVED (paid, ainda não refunded)
        'sandbox.asaas.com/api/v3/payments/pay_to_refund' => Http::sequence()
            ->push(['id' => 'pay_to_refund', 'status' => 'RECEIVED', 'value' => 150.0], 200)
            ->push(['id' => 'pay_to_refund', 'status' => 'REFUNDED', 'value' => 150.0], 200),
        // refund (POST) — endpoint específico /refund
        'sandbox.asaas.com/api/v3/payments/pay_to_refund/refund' => Http::response([
            'id' => 'pay_to_refund',
            'status' => 'REFUNDED',
            'value' => 150.0,
            'refundedDate' => '2026-05-12',
        ], 200),
    ]);

    $job = new RefundCobrancaAsaasJob(
        businessId: 1,
        transactionDocumentId: $doc->id,
        motivo: 'venda cancelada — refund teste',
    );
    $job->handle(app(BoletoService::class));

    // POST /refund foi disparado com body contendo description
    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_ends_with($request->url(), '/payments/pay_to_refund/refund')
            && $request->hasHeader('access_token', '$aact_refund_token')
            && str_contains((string) ($request['description'] ?? ''), 'venda cancelada');
    });
});

it('2. idempotência: charge Asaas já REFUNDED → no-op (POST refund NÃO chamado)', function () {
    Config::set('services.asaas.refund_enabled', true);
    rcajCriarCredencialAsaas(1);
    $charge = rcajCriarCharge(1, 'pay_already_refunded', 'paid');
    $doc = rcajCriarDocumento(1, 3002, TransactionDocument::DOC_BOLETO_ASAAS, $charge->id);

    $this->actingAs(rcajCriarUser(1));
    session(['user.business_id' => 1]);

    Http::fake([
        // GET — Asaas já retorna REFUNDED
        'sandbox.asaas.com/api/v3/payments/pay_already_refunded' => Http::response([
            'id' => 'pay_already_refunded', 'status' => 'REFUNDED', 'value' => 99.99,
        ], 200),
    ]);

    Log::spy();

    $job = new RefundCobrancaAsaasJob(
        businessId: 1,
        transactionDocumentId: $doc->id,
        motivo: 'rerun idempotente',
    );
    $job->handle(app(BoletoService::class));

    // GET aconteceu, mas POST /refund NÃO
    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && str_ends_with($request->url(), '/payments/pay_already_refunded'));

    Http::assertNotSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), '/refund'));

    Log::shouldHaveReceived('info')
        ->withArgs(fn ($message) => str_contains((string) $message, 'já REFUNDED'))
        ->atLeast()
        ->once();
});

it('3. flag ASAAS_REFUND_ENABLED=false → NÃO chama API, só loga TODO', function () {
    Config::set('services.asaas.refund_enabled', false);
    rcajCriarCredencialAsaas(1);
    $charge = rcajCriarCharge(1, 'pay_flag_off', 'paid');
    $doc = rcajCriarDocumento(1, 3003, TransactionDocument::DOC_BOLETO_ASAAS, $charge->id);

    $this->actingAs(rcajCriarUser(1));
    session(['user.business_id' => 1]);

    Http::fake([
        // GET pode ser chamado pra checar status remoto antes do guard
        'sandbox.asaas.com/api/v3/payments/pay_flag_off' => Http::response([
            'id' => 'pay_flag_off', 'status' => 'RECEIVED', 'value' => 50.0,
        ], 200),
    ]);

    Log::spy();

    $job = new RefundCobrancaAsaasJob(
        businessId: 1,
        transactionDocumentId: $doc->id,
        motivo: 'teste flag off',
    );
    $job->handle(app(BoletoService::class));

    // POST /refund NÃO foi chamado — flag desligada
    Http::assertNotSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), '/refund'));

    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($message) => str_contains((string) $message, 'ASAAS_REFUND_ENABLED=false'))
        ->atLeast()
        ->once();
});

it('4. cross-tenant: businessId != doc.business_id → RuntimeException', function () {
    Config::set('services.asaas.refund_enabled', true);
    rcajCriarCredencialAsaas(1);
    $charge = rcajCriarCharge(99, 'pay_other_tenant', 'paid');
    $doc = rcajCriarDocumento(99, 3004, TransactionDocument::DOC_BOLETO_ASAAS, $charge->id);

    $this->actingAs(rcajCriarUser(1));
    session(['user.business_id' => 1]);

    Http::fake();

    $job = new RefundCobrancaAsaasJob(
        businessId: 1, // claim biz=1 mas doc=biz=99
        transactionDocumentId: $doc->id,
        motivo: 'cross-tenant ataque refund',
    );

    expect(fn () => $job->handle(app(BoletoService::class)))
        ->toThrow(RuntimeException::class, 'Cross-tenant violation');

    Http::assertNothingSent();
});

it('5. doc_type != boleto_asaas → RuntimeException', function () {
    Config::set('services.asaas.refund_enabled', true);
    rcajCriarCredencialAsaas(1);
    $charge = rcajCriarCharge(1, 'pay_wrong_type', 'paid');
    // boleto_inter — esse Job só aceita boleto_asaas
    $doc = rcajCriarDocumento(1, 3005, TransactionDocument::DOC_BOLETO_INTER, $charge->id);

    $this->actingAs(rcajCriarUser(1));
    session(['user.business_id' => 1]);

    Http::fake();

    $job = new RefundCobrancaAsaasJob(
        businessId: 1,
        transactionDocumentId: $doc->id,
        motivo: 'tipo errado refund',
    );

    expect(fn () => $job->handle(app(BoletoService::class)))
        ->toThrow(RuntimeException::class, "doc_type inválido pra RefundCobrancaAsaasJob: 'boleto_inter'");

    Http::assertNothingSent();
});
