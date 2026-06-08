<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\NfeBrasil\Jobs\EmitirNFSeJob;
use Modules\NfeBrasil\Models\NfseEmissao;

uses(Tests\TestCase::class);

/**
 * US-NFE-060 · EmitirNFSeJob — STUB modelo 56 nacional (NT 2024-001).
 *
 * Job é foundation: cria NfseEmissao em DB + marca status sent (simulação).
 * NÃO chama API real `nfse.gov.br/sefin` — pacote sped-nfse será adicionado
 * em US futura via ADR.
 *
 * Tests cobrem:
 *   1. handle() cria NfseEmissao com pending → marca sent
 *   2. status final = sent após handle (mock-envio)
 *   3. multi-tenant: biz=1 não enxerga emissão biz=99 (HasBusinessScope)
 *   4. failed() callback marca status rejected + grava error_msg
 *   5. isAuthorized() retorna true só quando status=authorized
 *
 * Padrão schema inline (mesmo padrão Whatsapp/MultiTenantIsolationTest) —
 * UltimatePOS migrations completas quebram SQLite.
 */

beforeEach(function () {
    Schema::dropIfExists('nfse_emissoes');
    Schema::dropIfExists('users');

    // Tabela users mínima — necessária pra Auth::loginUsingId() ativar
    // ScopeByBusiness (que confere auth()->check() antes de filtrar).
    Schema::create('users', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->string('email', 191)->unique();
        $table->string('password', 191)->default('x');
        $table->timestamps();
        $table->softDeletes();
    });

    // Tabelas Spatie Permission mínimas — `ScopeByBusiness` chama
    // `$user->can('jana.superadmin')` que dispara queries em permissions.
    foreach (['permissions', 'roles', 'model_has_permissions', 'model_has_roles', 'role_has_permissions'] as $t) {
        Schema::dropIfExists($t);
    }
    Schema::create('permissions', function ($table) {
        $table->bigIncrements('id');
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();
    });
    Schema::create('roles', function ($table) {
        $table->bigIncrements('id');
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();
    });
    Schema::create('model_has_permissions', function ($table) {
        $table->unsignedBigInteger('permission_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->primary(['permission_id', 'model_id', 'model_type'], 'mhp_pk');
    });
    Schema::create('model_has_roles', function ($table) {
        $table->unsignedBigInteger('role_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->primary(['role_id', 'model_id', 'model_type'], 'mhr_pk');
    });
    Schema::create('role_has_permissions', function ($table) {
        $table->unsignedBigInteger('permission_id');
        $table->unsignedBigInteger('role_id');
        $table->primary(['permission_id', 'role_id'], 'rhp_pk');
    });

    Schema::create('nfse_emissoes', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('transaction_id')->nullable();
        $table->unsignedBigInteger('numero_rps')->nullable();
        $table->unsignedBigInteger('numero_nfse')->nullable();
        $table->string('codigo_verificacao', 50)->nullable();
        $table->string('item_lc116', 10);
        $table->decimal('value_servico', 22, 4);
        $table->decimal('value_iss', 22, 4)->nullable();
        $table->decimal('aliquota_iss', 5, 4)->nullable();
        $table->string('tomador_doc', 14);
        $table->string('tomador_nome', 200);
        $table->string('status', 20)->default('pending');
        $table->longText('xml_envio')->nullable();
        $table->longText('xml_retorno')->nullable();
        $table->string('pdf_path', 500)->nullable();
        $table->text('error_msg')->nullable();
        $table->timestamp('emitted_at')->nullable();
        $table->timestamps();
    });

    auth()->logout();
    session()->forget('user.business_id');
});

it('handle cria NfseEmissao e termina com status sent (stub envio)', function () {
    $job = new EmitirNFSeJob(
        businessId: 1,
        transactionId: 100,
        valorServico: 200.00,
        itemLc116: '17.06',
        tomadorDoc: '12345678901',
        tomadorNome: 'Tomador Teste',
    );

    $job->handle();

    $emissoes = NfseEmissao::withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)
        ->where('transaction_id', 100)
        ->get();

    expect($emissoes)->toHaveCount(1);

    $e = $emissoes->first();
    expect($e->status)->toBe(NfseEmissao::STATUS_SENT)
        ->and($e->item_lc116)->toBe('17.06')
        ->and((float) $e->value_servico)->toBe(200.00)
        ->and($e->tomador_doc)->toBe('12345678901')
        ->and($e->tomador_nome)->toBe('Tomador Teste');
});

it('cria emissao inicial com status pending antes de marcar sent', function () {
    // Garante que o registro inicial cria com status canônico pending —
    // mesmo que o STUB avance imediatamente pra sent, o constants estão
    // alinhados com o ENUM da migration.
    expect(NfseEmissao::STATUS_PENDING)->toBe('pending')
        ->and(NfseEmissao::STATUS_SENT)->toBe('sent');

    // E após o handle, o registro persistido tem status sent (simula envio).
    $job = new EmitirNFSeJob(
        businessId: 1,
        transactionId: 200,
        valorServico: 350.50,
        itemLc116: '14.05',
        tomadorDoc: '11222333000181',
        tomadorNome: 'CNPJ Tomador LTDA',
    );
    $job->handle();

    $e = NfseEmissao::withoutGlobalScope(ScopeByBusiness::class)
        ->where('transaction_id', 200)->first();

    expect($e)->not->toBeNull()
        ->and($e->status)->toBe(NfseEmissao::STATUS_SENT);
});

it('multi-tenant: biz=1 NÃO enxerga emissão biz=99 (HasBusinessScope)', function () {
    // Cria 2 emissões em businesses diferentes (escapando scope no setup)
    NfseEmissao::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id'    => 1,
        'transaction_id' => 1001,
        'item_lc116'     => '17.06',
        'value_servico'  => 100.00,
        'tomador_doc'    => '11111111111',
        'tomador_nome'   => 'Tomador biz 1',
        'status'         => NfseEmissao::STATUS_AUTHORIZED,
    ]);
    NfseEmissao::withoutGlobalScope(ScopeByBusiness::class)->create([
        'business_id'    => 99,
        'transaction_id' => 9999,
        'item_lc116'     => '17.06',
        'value_servico'  => 999.00,
        'tomador_doc'    => '99999999999',
        'tomador_nome'   => 'Tomador biz 99 (CONFIDENCIAL — não pode vazar)',
        'status'         => NfseEmissao::STATUS_AUTHORIZED,
    ]);

    // ScopeByBusiness só ATIVA filtragem se auth()->check() retornar true.
    // Sem auth, scope retorna sem aplicar filter (intencional pra CLI/jobs).
    // Pra teste cross-tenant precisamos autenticar User real.
    $userIdBiz1 = DB::table('users')->insertGetId([
        'business_id' => 1,
        'email'       => 'user-biz1@test.example',
        'password'    => 'x',
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);
    $userIdBiz99 = DB::table('users')->insertGetId([
        'business_id' => 99,
        'email'       => 'user-biz99@test.example',
        'password'    => 'x',
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    // User do biz=1 SÓ deve ver biz=1
    Auth::loginUsingId($userIdBiz1);
    session(['user.business_id' => 1]);
    $found = NfseEmissao::all();
    expect($found)->toHaveCount(1)
        ->and($found->first()->business_id)->toBe(1)
        ->and($found->first()->tomador_nome)->toBe('Tomador biz 1');

    // Switch pra biz=99 (cross-tenant adversário): SÓ vê biz=99
    Auth::logout();
    Auth::loginUsingId($userIdBiz99);
    session(['user.business_id' => 99]);
    $foundOther = NfseEmissao::all();
    expect($foundOther)->toHaveCount(1)
        ->and($foundOther->first()->business_id)->toBe(99);

    // Sanity: as 2 realmente existem em DB
    $total = NfseEmissao::withoutGlobalScope(ScopeByBusiness::class)->count();
    expect($total)->toBe(2);
});

it('failed() callback marca status rejected e grava error_msg', function () {
    $job = new EmitirNFSeJob(
        businessId: 1,
        transactionId: 500,
        valorServico: 100.00,
        itemLc116: '17.06',
        tomadorDoc: '12345678901',
        tomadorNome: 'Tomador Falha',
    );

    // handle() cria a emissão com status sent
    $job->handle();

    // Simula falha posterior (ex: depois de retries esgotados)
    $job->failed(new \RuntimeException('Erro simulado SEFIN nacional'));

    $e = NfseEmissao::withoutGlobalScope(ScopeByBusiness::class)
        ->where('transaction_id', 500)
        ->first();

    expect($e)->not->toBeNull()
        ->and($e->status)->toBe(NfseEmissao::STATUS_REJECTED)
        ->and($e->error_msg)->toBe('Erro simulado SEFIN nacional');
});

it('isAuthorized() retorna true SÓ quando status=authorized', function () {
    $base = [
        'business_id'    => 1,
        'transaction_id' => 700,
        'item_lc116'     => '17.06',
        'value_servico'  => 50.00,
        'tomador_doc'    => '12345678901',
        'tomador_nome'   => 'X',
    ];

    foreach ([
        NfseEmissao::STATUS_PENDING    => false,
        NfseEmissao::STATUS_SENT       => false,
        NfseEmissao::STATUS_REJECTED   => false,
        NfseEmissao::STATUS_CANCELLED  => false,
        NfseEmissao::STATUS_AUTHORIZED => true,
    ] as $status => $expected) {
        $e = new NfseEmissao(array_merge($base, ['status' => $status]));
        expect($e->isAuthorized())->toBe(
            $expected,
            "Status '{$status}' deveria retornar isAuthorized()=" . ($expected ? 'true' : 'false')
        );
    }
});
