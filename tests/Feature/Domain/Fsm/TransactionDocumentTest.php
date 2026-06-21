<?php

declare(strict_types=1);

use App\Domain\Fsm\Models\TransactionDocument;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Spatie\Permission\PermissionRegistrar;

/**
 * US-SELL-014 — Multi-documento por venda (transaction_documents poly N:1).
 *
 * Caso prático canônico: OS R$ [redacted Tier 0] = NFe55 (banner R$ [redacted Tier 0]) + NFSe56 (instalação
 * R$ [redacted Tier 0]) — memory/requisitos/Sells/CASO-PRATICO-OS-COMUNICACAO-VISUAL.md.
 *
 * Default biz=1 (Wagner WR2 SC); cross-tenant adversário = biz=99 (improvável existir).
 * NUNCA biz=4 — auto-mem feedback_test_business_id_1_nunca_4 + tests/Unit/BusinessIdGuardTest.
 */

/**
 * Stub Model (poly target) — simula NfeEmissao / NfseEmissao para testar morphTo.
 */
class FakeFiscalDoc extends Model
{
    protected $table = 'fake_fiscal_docs';

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

    Schema::create('fake_fiscal_docs', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id');
        $t->string('numero_documento', 20)->nullable();
    });

    // Spatie Permission tabelas — HasBusinessScope toca auth() que precisa setup.
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
        $t->primary(['permission_id', 'model_id', 'model_type'], 'mhp_pk');
    });
    Schema::create('model_has_roles', function (Blueprint $t) {
        $t->unsignedBigInteger('role_id');
        $t->string('model_type');
        $t->unsignedBigInteger('model_id');
        $t->primary(['role_id', 'model_id', 'model_type'], 'mhr_pk');
    });
    Schema::create('role_has_permissions', function (Blueprint $t) {
        $t->unsignedBigInteger('permission_id');
        $t->unsignedBigInteger('role_id');
        $t->primary(['permission_id', 'role_id']);
    });

    foreach (glob(database_path('migrations/2026_05_11_140001_create_transaction_documents_table.php')) ?: [] as $f) {
        (require $f)->up();
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        foreach (array_reverse(glob(database_path('migrations/2026_05_11_140001_create_transaction_documents_table.php')) ?: []) as $f) {
            (require $f)->down();
        }
        foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions', 'fake_fiscal_docs', 'users'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function tdCriarUser(int $bizId): User
{
    return User::forceCreate([
        'username' => 'tdu_biz' . $bizId . '_' . uniqid(),
        'password' => bcrypt('x'),
        'business_id' => $bizId,
    ]);
}

function tdCriarFakeDoc(int $bizId, string $numero = 'X-001'): FakeFiscalDoc
{
    $d = new FakeFiscalDoc;
    $d->business_id = $bizId;
    $d->numero_documento = $numero;
    $d->save();

    return $d;
}

function tdCriarDocumento(int $bizId, int $transactionId, string $docType, int $docId, string $value, string $status = TransactionDocument::STATUS_PENDING): TransactionDocument
{
    return TransactionDocument::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id' => $bizId,
        'transaction_id' => $transactionId,
        'doc_type' => $docType,
        'doc_class' => FakeFiscalDoc::class,
        'doc_id' => $docId,
        'value_total' => $value,
        'status' => $status,
    ]);
}

it('1. cria TransactionDocument com 1 NFe55 — forTransaction retorna 1', function () {
    $fake = tdCriarFakeDoc(1);
    tdCriarDocumento(1, 100, TransactionDocument::DOC_NFE55, $fake->id, '350.0000');

    $this->actingAs(tdCriarUser(1));
    session(['user.business_id' => 1]);

    $docs = TransactionDocument::forTransaction(100)->get();

    expect($docs)->toHaveCount(1);
    expect($docs->first()->doc_type)->toBe('nfe55');
    expect((string) $docs->first()->value_total)->toBe('350.0000');
    expect($docs->first()->status)->toBe(TransactionDocument::STATUS_PENDING);
});

it('2. transação com 2 docs (NFe55 + NFSe56) — forTransaction retorna 2 com status individual', function () {
    $banner = tdCriarFakeDoc(1, 'NFE-001');
    $instal = tdCriarFakeDoc(1, 'NFSE-001');

    tdCriarDocumento(1, 200, TransactionDocument::DOC_NFE55, $banner->id, '350.0000', TransactionDocument::STATUS_AUTHORIZED);
    tdCriarDocumento(1, 200, TransactionDocument::DOC_NFSE56, $instal->id, '200.0000', TransactionDocument::STATUS_PENDING);

    $this->actingAs(tdCriarUser(1));
    session(['user.business_id' => 1]);

    $docs = TransactionDocument::forTransaction(200)->orderBy('doc_type')->get();

    expect($docs)->toHaveCount(2);
    // ordem alfabética: nfe55 antes de nfse56
    expect($docs[0]->doc_type)->toBe('nfe55');
    expect($docs[0]->status)->toBe(TransactionDocument::STATUS_AUTHORIZED);
    expect($docs[1]->doc_type)->toBe('nfse56');
    expect($docs[1]->status)->toBe(TransactionDocument::STATUS_PENDING);

    // Caso prático: soma = 550
    $soma = $docs->sum(fn ($d) => (float) $d->value_total);
    expect($soma)->toBe(550.0);
});

it('3. UNIQUE constraint impede duplicação (mesmo transaction_id + doc_type + doc_id)', function () {
    $fake = tdCriarFakeDoc(1);
    tdCriarDocumento(1, 300, TransactionDocument::DOC_NFE55, $fake->id, '100.0000');

    // mesmo (transaction_id, doc_type, doc_id) deve falhar
    tdCriarDocumento(1, 300, TransactionDocument::DOC_NFE55, $fake->id, '100.0000');
})->throws(\Illuminate\Database\QueryException::class);

it('4. multi-tenant: biz=1 não vê doc biz=99 (HasBusinessScope)', function () {
    $fakeA = tdCriarFakeDoc(1, 'A');
    $fakeB = tdCriarFakeDoc(99, 'B');

    $docA = tdCriarDocumento(1, 401, TransactionDocument::DOC_NFE55, $fakeA->id, '100.0000');
    $docB = tdCriarDocumento(99, 499, TransactionDocument::DOC_NFE55, $fakeB->id, '999.0000');

    $this->actingAs(tdCriarUser(1));
    session(['user.business_id' => 1]);

    $ids = TransactionDocument::all()->pluck('id');

    expect($ids)->toContain($docA->id)->not->toContain($docB->id);

    // E o reverso
    $this->actingAs(tdCriarUser(99));
    session(['user.business_id' => 99]);

    $ids99 = TransactionDocument::all()->pluck('id');

    expect($ids99)->toContain($docB->id)->not->toContain($docA->id);
});

it('5. status individual independente — cancelar NFe55 não afeta NFSe56 da mesma transação', function () {
    $banner = tdCriarFakeDoc(1, 'NFE-CASO');
    $instal = tdCriarFakeDoc(1, 'NFSE-CASO');

    $nfe = tdCriarDocumento(1, 500, TransactionDocument::DOC_NFE55, $banner->id, '350.0000', TransactionDocument::STATUS_AUTHORIZED);
    $nfse = tdCriarDocumento(1, 500, TransactionDocument::DOC_NFSE56, $instal->id, '200.0000', TransactionDocument::STATUS_AUTHORIZED);

    $this->actingAs(tdCriarUser(1));
    session(['user.business_id' => 1]);

    // Cancelar SÓ a NFe55
    $nfe->update(['status' => TransactionDocument::STATUS_CANCELLED]);

    $reloaded = TransactionDocument::forTransaction(500)->orderBy('doc_type')->get();

    expect($reloaded[0]->doc_type)->toBe('nfe55');
    expect($reloaded[0]->status)->toBe(TransactionDocument::STATUS_CANCELLED);

    expect($reloaded[1]->doc_type)->toBe('nfse56');
    expect($reloaded[1]->status)->toBe(TransactionDocument::STATUS_AUTHORIZED); // intacta
});

it('6. relacionamento poly document() resolve pra Model arbitrário', function () {
    $fake = tdCriarFakeDoc(1, 'POLY-001');
    $td = tdCriarDocumento(1, 600, TransactionDocument::DOC_NFE55, $fake->id, '123.4500');

    $this->actingAs(tdCriarUser(1));
    session(['user.business_id' => 1]);

    $reload = TransactionDocument::with('document')->find($td->id);

    expect($reload->document)->toBeInstanceOf(FakeFiscalDoc::class);
    expect($reload->document->id)->toBe($fake->id);
    expect($reload->document->numero_documento)->toBe('POLY-001');
});
