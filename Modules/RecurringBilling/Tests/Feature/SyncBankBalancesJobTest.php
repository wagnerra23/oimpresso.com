<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\RecurringBilling\Jobs\SyncBankBalancesJob;

uses(Tests\TestCase::class);

/**
 * US-RB-045 · SyncBankBalancesJob — sync saldo Inter via Banking API v2.
 *
 * Foco da suite: **regressão de descriptografia** — BoletoService grava
 * `certificado_key_b64` (e `client_secret`) com Crypt::encryptString. O Sync
 * precisa descifrar antes de passar pro InterBankingClient, senão a mTLS
 * recebe ciphertext em vez de PEM.
 */
const FAKE_INTER_PEM = "-----BEGIN PRIVATE KEY-----\nFAKE\n-----END PRIVATE KEY-----\n";

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    Cache::flush();

    foreach (['fin_contas_bancarias', 'rb_boleto_credentials', 'business'] as $tbl) {
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
});

afterEach(function () {
    // business/rb_boleto_credentials/fin_contas_bancarias são reais-migradas; o afterEach
    // roda mesmo em teste pulado (PHPUnit 12: tearDown gated só por hasMetRequirements),
    // então dropá-las no MySQL persistente corromperia testes irmãos do módulo. DDL só em sqlite.
    if (DB::connection()->getDriverName() === 'sqlite') {
        foreach (['fin_contas_bancarias', 'rb_boleto_credentials', 'business'] as $tbl) {
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

function fakeInterBalanceCredential(int $businessId): int
{
    DB::table('business')->insert(['id' => $businessId, 'name' => "Biz {$businessId}"]);

    // Replica o formato gravado por BoletoService::encryptConfig:
    //   client_secret + certificado_key_b64 sob Crypt::encryptString;
    //   certificado_crt_b64 em claro (cert público).
    $config = [
        'client_id'           => 'cid',
        'client_secret'       => Crypt::encryptString('csec'),
        'certificado_crt_b64' => base64_encode("-----BEGIN CERTIFICATE-----\nfake\n-----END CERTIFICATE-----\n"),
        'certificado_key_b64' => Crypt::encryptString(base64_encode(FAKE_INTER_PEM)),
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

function fakeInterSaldoEndpoint(float $disponivel = 1234.56): void
{
    Http::fake([
        '*/oauth/v2/token'    => Http::response(['access_token' => 'tk_abc']),
        '*/banking/v2/saldo'  => Http::response([
            'disponivel'              => $disponivel,
            'bloqueadoCheque'         => 0,
            'bloqueadoJudicialmente'  => 0,
            'bloqueadoAdministrativo' => 0,
            'limite'                  => 0,
        ]),
    ]);
}

it('descriptografa certificado_key_b64 antes de chamar InterBankingClient (regressão crítica)', function () {
    fakeInterBalanceCredential(businessId: 1);
    fakeInterSaldoEndpoint();

    (new SyncBankBalancesJob())->handle();

    // Smoking gun: cert key em /tmp deve ser o PEM em claro, NÃO o ciphertext.
    $keys = glob(sys_get_temp_dir().'/inter_key_*.pem');
    expect($keys)->toHaveCount(1);

    $content = file_get_contents($keys[0]);
    expect($content)->toBe(FAKE_INTER_PEM)
        ->and($content)->not->toContain('eyJpdi'); // assinatura envelope Crypt
});

it('saldo Inter persiste em fin_contas_bancarias.saldo_cached', function () {
    $contaId = fakeInterBalanceCredential(businessId: 1);
    fakeInterSaldoEndpoint(disponivel: 9876.54);

    (new SyncBankBalancesJob())->handle();

    $row = DB::table('fin_contas_bancarias')->where('id', $contaId)->first();
    expect((float) $row->saldo_cached)->toBe(9876.54)
        ->and($row->saldo_atualizado_em)->not->toBeNull();
});

it('multi-tenant: business 1 e business 2 sincronizam isolados', function () {
    $contaA = fakeInterBalanceCredential(businessId: 1);
    $contaB = fakeInterBalanceCredential(businessId: 2);
    fakeInterSaldoEndpoint(disponivel: 500);

    (new SyncBankBalancesJob())->handle();

    $rowA = DB::table('fin_contas_bancarias')->where('id', $contaA)->first();
    $rowB = DB::table('fin_contas_bancarias')->where('id', $contaB)->first();

    expect((float) $rowA->saldo_cached)->toBe(500.0)
        ->and((float) $rowB->saldo_cached)->toBe(500.0)
        ->and($rowA->business_id)->toBe(1)
        ->and($rowB->business_id)->toBe(2);
});

it('falha em uma conta não derruba o batch (warn-and-continue)', function () {
    fakeInterBalanceCredential(businessId: 1);
    fakeInterBalanceCredential(businessId: 2);

    // 401 em /token — todas falham, mas job não throwa.
    Http::fake([
        '*/oauth/v2/token' => Http::response(['error' => 'invalid_client'], 401),
    ]);

    (new SyncBankBalancesJob())->handle();

    expect(DB::table('fin_contas_bancarias')->whereNotNull('saldo_cached')->count())->toBe(0);
});

it('on-demand: contaBancariaId sincroniza só a conta especificada', function () {
    $contaA = fakeInterBalanceCredential(businessId: 1);
    $contaB = fakeInterBalanceCredential(businessId: 2);
    fakeInterSaldoEndpoint(disponivel: 100);

    (new SyncBankBalancesJob(contaBancariaId: $contaA))->handle();

    $rowA = DB::table('fin_contas_bancarias')->where('id', $contaA)->first();
    $rowB = DB::table('fin_contas_bancarias')->where('id', $contaB)->first();
    expect((float) $rowA->saldo_cached)->toBe(100.0)
        ->and($rowB->saldo_cached)->toBeNull();
});
