<?php

declare(strict_types=1);

/*
 * Contrato — sync de saldo Inter PJ via Banking API v2 (SDD §5.3 F9 · CU-RB-13).
 * Fluxo de job, sem tela própria neste módulo (a tela de extrato mora no Financeiro).
 *
 * @covers-us US-RB-045
 */

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
 * Foco da suite: **o segredo vem do PaymentGateway** (decisão b1; onda 1 da saída de
 * `rb_boleto_credentials`, 2026-11-24). Desde a onda 4 a conta liga à credencial do PG pelo
 * `account_id` (`ContaBancaria::credencialGateway`); a linha do RB que a conta ainda aponta fica só
 * de isca. O `config_json` usado é o do PG, decifrado pelo cast `encrypted:array`. Se o PEM
 * chegasse cifrado, a mTLS receberia ciphertext.
 */
const FAKE_INTER_PEM = "-----BEGIN PRIVATE KEY-----\nFAKE\n-----END PRIVATE KEY-----\n";

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    Cache::flush();

    foreach (['fin_contas_bancarias', 'rb_boleto_credentials', 'payment_gateway_credentials', 'business'] as $tbl) {
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

    // Credencial do PaymentGateway — de onde o job tira o segredo (onda 1, 2026-11-24).
    Schema::create('payment_gateway_credentials', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->string('gateway_key', 30);
        $t->string('ambiente', 20)->default('production');
        $t->boolean('ativo')->default(true);
        $t->string('nome_display')->nullable();
        $t->longText('config_json')->nullable();
        $t->unsignedInteger('conta_bancaria_id')->nullable();
        $t->string('health_status', 20)->nullable();
        $t->timestamp('health_checked_at')->nullable();
        $t->timestamps();
    });

    Schema::create('fin_contas_bancarias', function ($t) {
        $t->increments('id');
        $t->unsignedInteger('business_id')->index();
        $t->unsignedInteger('account_id')->nullable();
        $t->char('banco_codigo', 3)->nullable();
        $t->boolean('ativo_para_boleto')->default(true);
        $t->unsignedBigInteger('rb_gateway_credential_id')->nullable();
        $t->unsignedBigInteger('payment_gateway_credential_id')->nullable();
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
        foreach (['fin_contas_bancarias', 'rb_boleto_credentials', 'payment_gateway_credentials', 'business'] as $tbl) {
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

function fakeInterBalanceCredential(int $businessId, bool $comCredencialPg = true): int
{
    DB::table('business')->insert(['id' => $businessId, 'name' => "Biz {$businessId}"]);

    // Isca: a conta ainda aponta para esta linha do RB, com config inútil. Se o job voltasse a
    // ler daqui, a mTLS e o OAuth quebrariam.
    $accountId = 900 + $businessId;
    $credId = DB::table('rb_boleto_credentials')->insertGetId([
        'business_id' => $businessId,
        'banco'       => 'inter',
        'ambiente'    => 'production',
        'ativo'       => true,
        'config_json' => json_encode(['client_id' => 'nao-usar-rb']),
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    if ($comCredencialPg) {
        // Insert cru: o model do PG tem LogsActivity (activity_log fora deste schema sintético).
        // config_json no formato do cast `encrypted:array` — o segredo fica em claro dentro dele.
        DB::table('payment_gateway_credentials')->insert([
            'business_id'  => $businessId,
            'gateway_key'  => 'inter',
            'ambiente'     => 'production',
            'ativo'        => true,
            'nome_display' => 'Inter Teste',
            'conta_bancaria_id' => $accountId,
            'config_json'  => Crypt::encryptString(json_encode([
                'client_id'           => 'cid',
                'client_secret'       => 'csec',
                'certificado_crt_b64' => base64_encode("-----BEGIN CERTIFICATE-----\nfake\n-----END CERTIFICATE-----\n"),
                'certificado_key_b64' => base64_encode(FAKE_INTER_PEM),
                'conta_corrente'      => '12345678',
            ])),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    return DB::table('fin_contas_bancarias')->insertGetId([
        'business_id'              => $businessId,
        'account_id'               => $accountId,
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

it('usa o certificado em claro da credencial do PaymentGateway, não a linha do RB (regressão crítica)', function () {
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

it('multi-tenant: o bypass do scope é EXPLÍCITO — varre todos os tenants mesmo COM sessão ativa', function () {
    $contaA = fakeInterBalanceCredential(businessId: 1);
    $contaB = fakeInterBalanceCredential(businessId: 2);
    fakeInterSaldoEndpoint(disponivel: 500);

    // Em produção este job roda em fila, SEM sessão — e aí o `BusinessScopeImpl`
    // sai cedo e nenhum filtro é aplicado. Isso torna "cross-tenant deliberado"
    // indistinguível de "esqueceram de filtrar".
    //
    // Com sessão ativa de biz=1, o scope PASSA a aplicar `where business_id = 1`.
    // Sem o `withoutGlobalScope(BusinessScopeImpl::class)` explícito no job, a
    // conta B ficaria com `saldo_cached` NULL EM SILÊNCIO. Este assert é o que
    // distingue as duas coisas — e morde se alguém remover o bypass explícito.
    session(['user.business_id' => 1]);

    (new SyncBankBalancesJob())->handle();

    $rowA = DB::table('fin_contas_bancarias')->where('id', $contaA)->first();
    $rowB = DB::table('fin_contas_bancarias')->where('id', $contaB)->first();

    expect((float) $rowA->saldo_cached)->toBe(500.0)
        ->and((float) $rowB->saldo_cached)->toBe(500.0)
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

it('sem credencial Inter no PaymentGateway, a conta é pulada e o saldo não muda', function () {
    $contaId = fakeInterBalanceCredential(businessId: 1, comCredencialPg: false);
    fakeInterSaldoEndpoint(disponivel: 777);

    (new SyncBankBalancesJob())->handle();

    expect(DB::table('fin_contas_bancarias')->where('id', $contaId)->value('saldo_cached'))->toBeNull();
    Http::assertNothingSent();
});
