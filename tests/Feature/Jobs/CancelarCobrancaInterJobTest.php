<?php

declare(strict_types=1);

use App\Domain\Fsm\Models\TransactionDocument;
use App\Jobs\CancelarCobrancaInterJob;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\RecurringBilling\Services\Boleto\BoletoService;
use Spatie\Permission\PermissionRegistrar;

/**
 * US-CASCADE-BOLETO-004 — CancelarCobrancaInterJob (chamada Inter PJ real).
 *
 * Cobre 5 cenários canônicos:
 *   1. Happy-path: cancela via BoletoService mock, atualiza charge local
 *   2. Idempotência: charge já cancelled → no-op (service NÃO chamado)
 *   3. Cross-tenant: businessId != document.business_id → RuntimeException
 *   4. doc_type != boleto_inter → RuntimeException
 *   5. Erro Inter (service throw) → re-lança pra retry policy do Queue
 *
 * Default biz=1; cross-tenant adversário biz=99 (convenção ADR 0101).
 */

/**
 * Stub poly target Inter — simula uma charge real (rb_inter_charges futura).
 * Schema mínimo: business_id + nosso_numero + status + cancelled_at + cancellation_reason.
 */
class FakeInterCharge extends Model
{
    protected $table = 'fake_inter_charges';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = [
        'cancelled_at' => 'datetime',
    ];
}

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 3 SDD floor; burn-down converte depois.');
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

    Schema::create('fake_inter_charges', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id');
        $t->string('nosso_numero', 60)->nullable();
        $t->string('status', 20)->default('pending');
        $t->timestamp('cancelled_at')->nullable();
        $t->string('cancellation_reason', 255)->nullable();
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
        $t->primary(['permission_id', 'model_id', 'model_type'], 'mhp_pk_ccij');
    });
    Schema::create('model_has_roles', function (Blueprint $t) {
        $t->unsignedBigInteger('role_id');
        $t->string('model_type');
        $t->unsignedBigInteger('model_id');
        $t->primary(['role_id', 'model_id', 'model_type'], 'mhr_pk_ccij');
    });
    Schema::create('role_has_permissions', function (Blueprint $t) {
        $t->unsignedBigInteger('permission_id');
        $t->unsignedBigInteger('role_id');
        $t->primary(['permission_id', 'role_id']);
    });

    // transaction_documents schema — replica produção (SQLite-compatible VARCHAR)
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
    if (DB::connection()->getDriverName() !== 'sqlite') {
        return;
    }

    foreach (
        [
            'transaction_documents',
            'role_has_permissions',
            'model_has_roles',
            'model_has_permissions',
            'roles',
            'permissions',
            'fake_inter_charges',
            'users',
        ] as $tbl
    ) {
        Schema::dropIfExists($tbl);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    \Mockery::close();
});

function ccijCriarUser(int $bizId): User
{
    return User::forceCreate([
        'username' => 'ccij_biz' . $bizId . '_' . uniqid(),
        'password' => bcrypt('x'),
        'business_id' => $bizId,
    ]);
}

function ccijCriarCharge(
    int $bizId,
    string $nossoNumero = '00012345678901234567890',
    string $status = 'pending',
): FakeInterCharge {
    $c = new FakeInterCharge;
    $c->business_id = $bizId;
    $c->nosso_numero = $nossoNumero;
    $c->status = $status;
    $c->save();

    return $c;
}

function ccijCriarDocumento(
    int $bizId,
    int $transactionId,
    string $docType,
    int $docId,
    string $value = '150.0000',
    string $status = TransactionDocument::STATUS_PENDING,
): TransactionDocument {
    return TransactionDocument::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $bizId,
        'transaction_id' => $transactionId,
        'doc_type' => $docType,
        'doc_class' => FakeInterCharge::class,
        'doc_id' => $docId,
        'value_total' => $value,
        'status' => $status,
    ]);
}

it('1. cancela cobrança Inter via service (mock) e atualiza charge local', function () {
    $charge = ccijCriarCharge(1, '00099887766554433221100');
    $doc = ccijCriarDocumento(1, 2001, TransactionDocument::DOC_BOLETO_INTER, $charge->id);

    $this->actingAs(ccijCriarUser(1));
    session(['user.business_id' => 1]);

    // Mock BoletoService — espera 1 chamada cancelar com motivo Inter
    $service = \Mockery::mock(BoletoService::class);
    $service->shouldReceive('cancelar')
        ->once()
        ->with(1, '00099887766554433221100', 'APEDIDODOCLIENTE')
        ->andReturn(true);

    $job = new CancelarCobrancaInterJob(
        businessId: 1,
        transactionDocumentId: $doc->id,
        motivo: 'venda cancelada — teste',
    );
    $job->handle($service);

    // Charge atualizada localmente
    $reload = FakeInterCharge::find($charge->id);
    expect($reload->status)->toBe('cancelled')
        ->and($reload->cancellation_reason)->toBe('venda cancelada — teste')
        ->and($reload->cancelled_at)->not->toBeNull();
});

it('2. idempotência: charge já cancelled NÃO chama Inter (no-op)', function () {
    $charge = ccijCriarCharge(1, '00011112222333344445555', 'cancelled');
    $doc = ccijCriarDocumento(1, 2002, TransactionDocument::DOC_BOLETO_INTER, $charge->id);

    $this->actingAs(ccijCriarUser(1));
    session(['user.business_id' => 1]);

    // Service NÃO deve ser chamado
    $service = \Mockery::mock(BoletoService::class);
    $service->shouldNotReceive('cancelar');

    Log::spy();

    $job = new CancelarCobrancaInterJob(
        businessId: 1,
        transactionDocumentId: $doc->id,
        motivo: 'rerun idempotente',
    );
    $job->handle($service);

    Log::shouldHaveReceived('info')
        ->withArgs(fn ($message) => str_contains((string) $message, 'já cancelada'))
        ->atLeast()
        ->once();
});

it('3. cross-tenant guard: biz=1 Job tentando documento biz=99 lança exception', function () {
    $charge = ccijCriarCharge(99, '00077777777777777777777');
    $doc = ccijCriarDocumento(99, 2003, TransactionDocument::DOC_BOLETO_INTER, $charge->id);

    $this->actingAs(ccijCriarUser(1));
    session(['user.business_id' => 1]);

    $service = \Mockery::mock(BoletoService::class);
    $service->shouldNotReceive('cancelar');

    $job = new CancelarCobrancaInterJob(
        businessId: 1, // Job claim biz=1 mas doc é de biz=99
        transactionDocumentId: $doc->id,
        motivo: 'cross-tenant ataque',
    );

    expect(fn () => $job->handle($service))
        ->toThrow(RuntimeException::class, 'Cross-tenant violation');

    // Charge NÃO foi tocada
    $reload = FakeInterCharge::find($charge->id);
    expect($reload->status)->toBe('pending');
});

it('4. doc_type != boleto_inter lança RuntimeException', function () {
    $charge = ccijCriarCharge(1, '00055555555555555555555');
    // Tipo errado pra esse Job — usa boleto_asaas
    $doc = ccijCriarDocumento(1, 2004, TransactionDocument::DOC_BOLETO_ASAAS, $charge->id);

    $this->actingAs(ccijCriarUser(1));
    session(['user.business_id' => 1]);

    $service = \Mockery::mock(BoletoService::class);
    $service->shouldNotReceive('cancelar');

    $job = new CancelarCobrancaInterJob(
        businessId: 1,
        transactionDocumentId: $doc->id,
        motivo: 'teste tipo errado',
    );

    expect(fn () => $job->handle($service))
        ->toThrow(RuntimeException::class, "doc_type inválido pra CancelarCobrancaInterJob: 'boleto_asaas'");
});

it('5. erro Inter no service dispara retry (re-lança exception)', function () {
    $charge = ccijCriarCharge(1, '00033333333333333333333');
    $doc = ccijCriarDocumento(1, 2005, TransactionDocument::DOC_BOLETO_INTER, $charge->id);

    $this->actingAs(ccijCriarUser(1));
    session(['user.business_id' => 1]);

    // Service simula erro HTTP do Inter (rede / 5xx / mTLS)
    $service = \Mockery::mock(BoletoService::class);
    $service->shouldReceive('cancelar')
        ->once()
        ->andThrow(new RuntimeException('Inter PJ HTTP 503 — gateway temporariamente indisponível'));

    Log::spy();

    $job = new CancelarCobrancaInterJob(
        businessId: 1,
        transactionDocumentId: $doc->id,
        motivo: 'teste retry',
    );

    expect(fn () => $job->handle($service))
        ->toThrow(RuntimeException::class, 'Inter PJ HTTP 503');

    // Log de erro foi registrado
    Log::shouldHaveReceived('error')
        ->withArgs(fn ($message) => str_contains((string) $message, 'chamada Inter PJ falhou'))
        ->atLeast()
        ->once();

    // Charge NÃO foi marcada cancelled (erro impede atualização local)
    $reload = FakeInterCharge::find($charge->id);
    expect($reload->status)->toBe('pending')
        ->and($reload->cancelled_at)->toBeNull();
});
