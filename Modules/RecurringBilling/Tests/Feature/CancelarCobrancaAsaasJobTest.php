<?php

declare(strict_types=1);

use App\Domain\Fsm\Models\TransactionDocument;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use App\Scopes\ScopeByBusiness;
use Modules\RecurringBilling\Jobs\CancelarCobrancaAsaasJob;
use Modules\RecurringBilling\Services\CobrancaNoGateway;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class);

/**
 * US-CASCADE-BOLETO-003 — CancelarCobrancaAsaasJob.
 *
 * Cobre os 5 cenários canônicos:
 *   1. Happy-path: DELETE /payments/{id} (mock Http::fake) → log sucesso
 *   2. Idempotência: charge já cancelled retorna sem chamar API
 *   3. Cross-tenant: businessId != document.business_id, RuntimeException
 *   4. doc_type != boleto_asaas falha
 *   5. Erro 4xx Asaas → RuntimeException pra fila reagendar
 *
 * Tenant 98; cross-tenant adversário biz=99 (ADR 0358). Credencial em payment_gateway_credentials
 * desde a decisão b1 (2026-11-23): o cancelamento sai pelo driver do PaymentGateway.
 *
 * Pattern espelha AsaasDriverTest (Http::fake) + EstornarBoletoJobTest
 * (schema SQLite manual + FakeBoletoCharge MorphTo).
 */

/**
 * Stub poly target — simula a cobrança Asaas como Eloquent model
 * referenciada via doc_class+doc_id no TransactionDocument.
 */
class FakeAsaasChargeStub extends Model
{
    protected $table = 'fake_asaas_charges';

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

    Schema::create('fake_asaas_charges', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id');
        $t->string('gateway_ref', 60)->nullable();  // pay_xxx Asaas
    });

    // Credencial do PaymentGateway — CobrancaNoGateway lê daqui (decisão b1, 2026-11-23)
    Schema::create('payment_gateway_credentials', function ($table) {
        $table->id();
        $table->unsignedInteger('business_id')->index();
        $table->string('gateway_key', 30);
        $table->string('ambiente', 20)->default('production');
        $table->boolean('ativo')->default(true);
        $table->string('nome_display')->nullable();
        $table->longText('config_json')->nullable();
        $table->unsignedInteger('conta_bancaria_id')->nullable();
        $table->string('health_status', 20)->nullable();
        $table->timestamp('health_checked_at')->nullable();
        $table->timestamps();
    });

    // Spatie Permission (HasBusinessScope toca auth())
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
        $t->primary(['permission_id', 'model_id', 'model_type'], 'mhp_pk_ccaj');
    });
    Schema::create('model_has_roles', function (Blueprint $t) {
        $t->unsignedBigInteger('role_id');
        $t->string('model_type');
        $t->unsignedBigInteger('model_id');
        $t->primary(['role_id', 'model_id', 'model_type'], 'mhr_pk_ccaj');
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
    // Tabelas CORE reais-migradas (users/permissions/roles/transaction_documents) +
    // payment_gateway_credentials; o afterEach roda mesmo em teste pulado (PHPUnit 12:
    // tearDown gated só por hasMetRequirements), então dropá-las no MySQL persistente
    // corromperia testes irmãos do módulo. DDL só em sqlite.
    if (DB::connection()->getDriverName() === 'sqlite') {
        foreach (['transaction_documents', 'role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions', 'fake_asaas_charges', 'payment_gateway_credentials', 'users'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function ccajCriarCredencialAsaas(int $bizId): void
{
    // Insert cru: o model do PG tem LogsActivity (activity_log fora deste schema sintético).
    // config_json no formato do cast `encrypted:array` — o segredo fica em claro dentro dele.
    DB::table('payment_gateway_credentials')->insert([
        'business_id' => $bizId,
        'gateway_key' => 'asaas',
        'ambiente' => 'sandbox',
        'ativo' => true,
        'nome_display' => 'Asaas Teste',
        'config_json' => Crypt::encryptString(json_encode(['api_key' => '$aact_test_token'])),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function ccajCriarUser(int $bizId): User
{
    return User::forceCreate([
        'username' => 'ccaj_biz' . $bizId . '_' . uniqid(),
        'password' => bcrypt('x'),
        'business_id' => $bizId,
    ]);
}

function ccajCriarCharge(int $bizId, string $gatewayRef = 'pay_xpto'): FakeAsaasChargeStub
{
    $c = new FakeAsaasChargeStub;
    $c->business_id = $bizId;
    $c->gateway_ref = $gatewayRef;
    $c->save();

    return $c;
}

function ccajCriarDocumento(int $bizId, int $txId, string $docType, int $docId, string $value, string $status = TransactionDocument::STATUS_PENDING): TransactionDocument
{
    return TransactionDocument::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $bizId,
        'transaction_id' => $txId,
        'doc_type' => $docType,
        'doc_class' => FakeAsaasChargeStub::class,
        'doc_id' => $docId,
        'value_total' => $value,
        'status' => $status,
    ]);
}

it('1. cancela cobrança Asaas pending via DELETE /payments/{id} (mock Http)', function () {
    ccajCriarCredencialAsaas(98);
    $charge = ccajCriarCharge(98, 'pay_to_cancel');
    $doc = ccajCriarDocumento(98, 2001, TransactionDocument::DOC_BOLETO_ASAAS, $charge->id, '150.0000');

    $this->actingAs(ccajCriarUser(98));
    session(['user.business_id' => 98]);

    Http::fake([
        'sandbox.asaas.com/api/v3/payments/pay_to_cancel' => Http::response([
            'id' => 'pay_to_cancel', 'deleted' => true,
        ], 200),
    ]);

    $job = new CancelarCobrancaAsaasJob(
        businessId: 98,
        transactionDocumentId: $doc->id,
        motivo: 'venda cancelada — teste',
    );
    $job->handle(app(CobrancaNoGateway::class));

    // DELETE foi enviado ao endpoint correto com access_token
    Http::assertSent(function ($request) {
        return $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/payments/pay_to_cancel')
            && $request->hasHeader('access_token', '$aact_test_token');
    });
});

it('2. idempotência: charge já cancelled retorna sem chamar API', function () {
    ccajCriarCredencialAsaas(98);
    $charge = ccajCriarCharge(98, 'pay_idempotente');
    $doc = ccajCriarDocumento(
        98,
        2002,
        TransactionDocument::DOC_BOLETO_ASAAS,
        $charge->id,
        '99.9900',
        TransactionDocument::STATUS_CANCELLED,
    );

    $this->actingAs(ccajCriarUser(98));
    session(['user.business_id' => 98]);

    Http::fake(); // qualquer chamada cai aqui → assertNothingSent valida

    $job = new CancelarCobrancaAsaasJob(
        businessId: 98,
        transactionDocumentId: $doc->id,
        motivo: 'rerun idempotente',
    );
    $job->handle(app(CobrancaNoGateway::class));

    // Nenhuma chamada HTTP foi feita — short-circuit por idempotência
    Http::assertNothingSent();
});

it('3. cross-tenant: businessId != doc.business_id falha', function () {
    ccajCriarCredencialAsaas(98);
    $charge = ccajCriarCharge(99, 'pay_other_tenant');
    $doc = ccajCriarDocumento(99, 2003, TransactionDocument::DOC_BOLETO_ASAAS, $charge->id, '200.0000');

    $this->actingAs(ccajCriarUser(98));
    session(['user.business_id' => 98]);

    Http::fake();

    // Job claim biz=98 mas o documento é de biz=99
    $job = new CancelarCobrancaAsaasJob(
        businessId: 98,
        transactionDocumentId: $doc->id,
        motivo: 'cross-tenant ataque',
    );

    expect(fn () => $job->handle(app(CobrancaNoGateway::class)))
        ->toThrow(RuntimeException::class, 'Cross-tenant violation');

    // E nenhuma chamada Asaas foi disparada
    Http::assertNothingSent();
});

it('4. doc_type != boleto_asaas falha', function () {
    ccajCriarCredencialAsaas(98);
    $charge = ccajCriarCharge(98, 'pay_wrong_type');
    // boleto_inter — esse Job só aceita boleto_asaas
    $doc = ccajCriarDocumento(98, 2004, TransactionDocument::DOC_BOLETO_INTER, $charge->id, '500.0000');

    $this->actingAs(ccajCriarUser(98));
    session(['user.business_id' => 98]);

    Http::fake();

    $job = new CancelarCobrancaAsaasJob(
        businessId: 98,
        transactionDocumentId: $doc->id,
        motivo: 'tipo errado',
    );

    expect(fn () => $job->handle(app(CobrancaNoGateway::class)))
        ->toThrow(RuntimeException::class, "doc_type inválido pra CancelarCobrancaAsaasJob: 'boleto_inter'");

    Http::assertNothingSent();
});

it('5. erro 4xx Asaas dispara retry (RuntimeException relancada)', function () {
    ccajCriarCredencialAsaas(98);
    $charge = ccajCriarCharge(98, 'pay_500_error');
    $doc = ccajCriarDocumento(98, 2005, TransactionDocument::DOC_BOLETO_ASAAS, $charge->id, '777.0000');

    $this->actingAs(ccajCriarUser(98));
    session(['user.business_id' => 98]);

    // Asaas devolve 401 (token inválido / expirado) — caso real em prod
    Http::fake([
        'sandbox.asaas.com/api/v3/payments/pay_500_error' => Http::response([
            'errors' => [['code' => 'invalid_token', 'description' => 'API key inválida']],
        ], 401),
    ]);

    $job = new CancelarCobrancaAsaasJob(
        businessId: 98,
        transactionDocumentId: $doc->id,
        motivo: 'erro gateway',
    );

    expect(fn () => $job->handle(app(CobrancaNoGateway::class)))
        ->toThrow(RuntimeException::class, 'Asaas API retornou erro');

    // E a chamada HTTP foi efetivamente tentada (não foi short-circuit)
    Http::assertSent(function ($request) {
        return $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/payments/pay_500_error');
    });
});

it('6. b1: credencial só no rb_boleto_credentials (sem payment_gateway_credentials) não cancela e falha visível', function () {
    // Sem ccajCriarCredencialAsaas: a empresa não tem credencial asaas ativa no PaymentGateway.
    $charge = ccajCriarCharge(98, 'pay_sem_credencial_pg');
    $doc = ccajCriarDocumento(98, 2006, TransactionDocument::DOC_BOLETO_ASAAS, $charge->id, '10.0000');

    $this->actingAs(ccajCriarUser(98));
    session(['user.business_id' => 98]);
    Http::fake();

    $job = new CancelarCobrancaAsaasJob(
        businessId: 98,
        transactionDocumentId: $doc->id,
        motivo: 'sem credencial no PG',
    );

    expect(fn () => $job->handle(app(CobrancaNoGateway::class)))
        ->toThrow(\Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException::class, "sem credencial 'asaas' ativa");
    Http::assertNothingSent();
});
