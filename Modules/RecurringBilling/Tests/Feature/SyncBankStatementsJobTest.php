<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\RecurringBilling\Jobs\SyncBankStatementsJob;

uses(Tests\TestCase::class);

/**
 * US-RB-046 · SyncBankStatementsJob — sync extrato Inter idempotente.
 *
 * Mesmo pattern de `AsaasWebhookIdempotencyTest`: schema mínimo criado
 * manualmente (migrations UltimatePOS legadas não rodam em SQLite).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    Cache::flush();

    foreach (['fin_extrato_lancamentos', 'fin_contas_bancarias', 'rb_boleto_credentials', 'business'] as $tbl) {
        Schema::dropIfExists($tbl);
    }

    Schema::create('business', function ($t) {
        $t->id();
        $t->string('name')->nullable();
        $t->timestamps();
    });

    Schema::create('rb_boleto_credentials', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->string('banco', 10);
        $t->string('ambiente', 20)->default('production');
        $t->boolean('ativo')->default(true);
        $t->json('config_json');
        $t->timestamps();
    });

    Schema::create('fin_contas_bancarias', function ($t) {
        $t->increments('id');
        $t->unsignedInteger('business_id')->index();
        $t->unsignedInteger('account_id')->nullable();
        $t->char('banco_codigo', 3)->nullable();
        $t->boolean('ativo_para_boleto')->default(true);
        $t->unsignedBigInteger('rb_gateway_credential_id')->nullable();
        $t->decimal('saldo_cached', 15, 2)->nullable();
        $t->timestamp('saldo_atualizado_em')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('fin_extrato_lancamentos', function ($t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id');
        $t->unsignedInteger('conta_bancaria_id');
        $t->date('data');
        $t->decimal('valor', 15, 2);
        $t->char('tipo', 1);
        $t->string('descricao', 500);
        $t->string('contraparte_documento', 20)->nullable();
        $t->string('contraparte_nome', 255)->nullable();
        $t->string('idempotency_key', 100);
        $t->json('raw_payload');
        $t->timestamps();
        $t->unique(['conta_bancaria_id', 'idempotency_key'], 'fin_extrato_idem_unique');
    });
});

afterEach(function () {
    // business/rb_boleto_credentials/fin_* são reais-migradas; o afterEach roda mesmo em
    // teste pulado (PHPUnit 12: tearDown gated só por hasMetRequirements), então dropá-las
    // no MySQL persistente corromperia testes irmãos do módulo. DDL só em sqlite.
    if (DB::connection()->getDriverName() === 'sqlite') {
        foreach (['fin_extrato_lancamentos', 'fin_contas_bancarias', 'rb_boleto_credentials', 'business'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
    foreach (glob(sys_get_temp_dir().'/inter_crt_*.pem') as $f) {
        @unlink($f);
    }
    foreach (glob(sys_get_temp_dir().'/inter_key_*.pem') as $f) {
        @unlink($f);
    }
});

function fakeInterCredential(int $businessId): int
{
    DB::table('business')->insert(['id' => $businessId, 'name' => "Biz {$businessId}"]);

    $config = [
        'client_id'           => 'cid',
        'client_secret'       => Crypt::encryptString('csec'),
        'certificado_crt_b64' => base64_encode("-----BEGIN CERTIFICATE-----\nfake\n-----END CERTIFICATE-----\n"),
        'certificado_key_b64' => Crypt::encryptString(base64_encode("-----BEGIN PRIVATE KEY-----\nfake\n-----END PRIVATE KEY-----\n")),
        'conta_corrente'      => '12345678',
    ];

    $credId = DB::table('rb_boleto_credentials')->insertGetId([
        'business_id' => $businessId,
        'banco'       => 'inter',
        'ambiente'    => 'production',
        'ativo'       => true,
        'config_json' => json_encode($config),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    return DB::table('fin_contas_bancarias')->insertGetId([
        'business_id'              => $businessId,
        'banco_codigo'             => '077',
        'ativo_para_boleto'        => true,
        'rb_gateway_credential_id' => $credId,
        'created_at'               => now(),
        'updated_at'               => now(),
    ]);
}

function fakeInterExtrato(array $transacoes): void
{
    Http::fake([
        '*/oauth/v2/token'               => Http::response(['access_token' => 'tk']),
        '*/banking/v2/extrato/completo*' => Http::response([
            'ultimaPagina' => true,
            'transacoes'   => $transacoes,
        ]),
    ]);
}

it('sincroniza lançamentos novos pra conta Inter', function () {
    $contaId = fakeInterCredential(businessId: 1);
    fakeInterExtrato([
        ['idTransacao' => 'tx-001', 'dataInclusao' => '2026-05-07', 'tipoOperacao' => 'C', 'valor' => '100.00', 'titulo' => 'PIX', 'descricao' => 'in'],
        ['idTransacao' => 'tx-002', 'dataInclusao' => '2026-05-07', 'tipoOperacao' => 'D', 'valor' => '50.00',  'titulo' => 'BOLETO', 'descricao' => 'out'],
    ]);

    (new SyncBankStatementsJob())->handle();

    expect(DB::table('fin_extrato_lancamentos')->where('conta_bancaria_id', $contaId)->count())->toBe(2);
});

it('idempotência: rodar 2× com mesmas transações grava 2 registros (não 4)', function () {
    $contaId = fakeInterCredential(businessId: 1);
    fakeInterExtrato([
        ['idTransacao' => 'tx-001', 'dataInclusao' => '2026-05-07', 'tipoOperacao' => 'C', 'valor' => '100.00', 'titulo' => 'PIX', 'descricao' => 'in'],
        ['idTransacao' => 'tx-002', 'dataInclusao' => '2026-05-07', 'tipoOperacao' => 'D', 'valor' => '50.00',  'titulo' => 'BOLETO', 'descricao' => 'out'],
    ]);

    (new SyncBankStatementsJob())->handle();
    (new SyncBankStatementsJob())->handle();

    expect(DB::table('fin_extrato_lancamentos')->where('conta_bancaria_id', $contaId)->count())->toBe(2);
});

it('multi-tenant: lançamento de business 1 não vaza pra business 2', function () {
    $contaA = fakeInterCredential(businessId: 1);
    $contaB = fakeInterCredential(businessId: 2);

    fakeInterExtrato([
        ['idTransacao' => 'tx-001', 'dataInclusao' => '2026-05-07', 'tipoOperacao' => 'C', 'valor' => '100', 'titulo' => 'PIX', 'descricao' => 'a'],
    ]);

    (new SyncBankStatementsJob())->handle();

    $rowsA = DB::table('fin_extrato_lancamentos')->where('business_id', 1)->where('conta_bancaria_id', $contaA)->count();
    $rowsB = DB::table('fin_extrato_lancamentos')->where('business_id', 2)->where('conta_bancaria_id', $contaB)->count();

    expect($rowsA)->toBe(1)
        ->and($rowsB)->toBe(1);

    // Cross-check: row do business 1 não veio com business_id 2
    $vazou = DB::table('fin_extrato_lancamentos')
        ->where('business_id', 2)
        ->where('conta_bancaria_id', $contaA)
        ->count();
    expect($vazou)->toBe(0);
});

it('skip conta sem credencial Inter (e.g. asaas, c6)', function () {
    DB::table('business')->insert(['id' => 4, 'name' => 'Biz']);

    $credId = DB::table('rb_boleto_credentials')->insertGetId([
        'business_id' => 1,
        'banco'       => 'asaas',
        'ativo'       => true,
        'config_json' => json_encode(['api_key' => 'k']),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    DB::table('fin_contas_bancarias')->insert([
        'business_id'              => 1,
        'banco_codigo'             => '274',
        'rb_gateway_credential_id' => $credId,
        'created_at'               => now(),
        'updated_at'               => now(),
    ]);

    Http::fake();

    (new SyncBankStatementsJob())->handle();

    Http::assertNothingSent();
    expect(DB::table('fin_extrato_lancamentos')->count())->toBe(0);
});

it('on-demand: passa contaBancariaId pra sincar só uma conta', function () {
    $contaA = fakeInterCredential(businessId: 1);
    $contaB = fakeInterCredential(businessId: 2);

    fakeInterExtrato([
        ['idTransacao' => 'tx-only-a', 'dataInclusao' => '2026-05-07', 'tipoOperacao' => 'C', 'valor' => '10', 'titulo' => 'PIX', 'descricao' => 'a'],
    ]);

    (new SyncBankStatementsJob(contaBancariaId: $contaA))->handle();

    expect(DB::table('fin_extrato_lancamentos')->where('conta_bancaria_id', $contaA)->count())->toBe(1)
        ->and(DB::table('fin_extrato_lancamentos')->where('conta_bancaria_id', $contaB)->count())->toBe(0);
});
