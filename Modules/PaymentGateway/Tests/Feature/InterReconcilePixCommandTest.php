<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\PaymentGateway\Events\CobrancaPaga;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

/**
 * paymentgateway:inter-reconcile-pix — polling de reconciliação (fallback do
 * webhook). Consulta o Inter (GET /pix/v2/cob|cobv/{txid}) pelas cobranças PIX
 * emitidas e marca as pagas.
 *
 * Schema mínimo via beforeEach (guardado por hasTable) pra rodar em SQLite sem
 * migrations. Em MySQL (suite real) as tabelas já existem e o build é pulado.
 *
 * Multi-tenant Tier 0: biz=1 (ADR 0101 — nunca cliente real).
 */

if (! function_exists('pgEnsureSchema')) {
    function pgEnsureSchema(): void
    {
        if (! Schema::hasTable('activity_log')) {
            Schema::create('activity_log', function ($t) {
                $t->id();
                $t->string('log_name')->nullable();
                $t->text('description')->nullable();
                $t->unsignedBigInteger('subject_id')->nullable();
                $t->string('subject_type')->nullable();
                $t->unsignedBigInteger('causer_id')->nullable();
                $t->string('causer_type')->nullable();
                $t->json('properties')->nullable();
                $t->string('event')->nullable();
                $t->uuid('batch_uuid')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('payment_gateway_credentials')) {
            Schema::create('payment_gateway_credentials', function ($t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->string('gateway_key');
                $t->string('ambiente')->default('sandbox');
                $t->boolean('ativo')->default(true);
                $t->string('nome_display')->nullable();
                $t->text('config_json')->nullable();
                $t->unsignedBigInteger('conta_bancaria_id')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('cobrancas')) {
            Schema::create('cobrancas', function ($t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->unsignedBigInteger('payment_gateway_credential_id')->nullable();
                $t->string('gateway_external_id')->nullable();
                $t->string('tipo')->nullable();
                $t->string('status')->default('pending');
                $t->unsignedInteger('valor_centavos')->default(0);
                $t->unsignedInteger('valor_pago_centavos')->nullable();
                $t->date('vencimento')->nullable();
                $t->timestamp('paga_em')->nullable();
                $t->unsignedBigInteger('contact_id')->nullable();
                $t->string('payer_cpf_cnpj')->nullable();
                $t->string('payer_name')->nullable();
                $t->string('payer_email')->nullable();
                $t->text('descricao')->nullable();
                $t->string('idempotency_key')->nullable();
                $t->string('origem_type')->nullable();
                $t->unsignedBigInteger('origem_id')->nullable();
                $t->string('forma_pagamento')->nullable();
                $t->json('payload_gateway')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('fin_titulos')) {
            Schema::create('fin_titulos', function ($t) {
                $t->increments('id');
                $t->integer('business_id')->unsigned()->index();
                $t->string('numero', 20)->nullable();
                $t->string('tipo')->nullable();
                $t->string('status')->default('aberto');
                $t->integer('cliente_id')->unsigned()->nullable();
                $t->string('cliente_descricao')->nullable();
                $t->decimal('valor_total', 22, 4)->default(0);
                $t->decimal('valor_aberto', 22, 4)->default(0);
                $t->char('moeda', 3)->default('BRL');
                $t->date('emissao')->nullable();
                $t->date('vencimento')->nullable();
                $t->char('competencia_mes', 7)->nullable();
                $t->string('origem')->nullable();
                $t->integer('origem_id')->unsigned()->nullable();
                $t->json('metadata')->nullable();
                $t->integer('created_by')->unsigned()->nullable();
                $t->integer('updated_by')->unsigned()->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }
    }
}

/**
 * Http::fake do Inter — OAuth + GET /pix/v2/cob|cobv/{txid}.
 * Decide a resposta pelo txid: "paga"→CONCLUIDA, "removida"→REMOVIDA,
 * "erro"→HTTP 500, senão→ATIVA. Valor pago fixo 150,00 (15000 centavos).
 */
function fakeInterPixConsulta(): void
{
    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, '/oauth/v2/token')) {
            return Http::response(['access_token' => 'tk', 'expires_in' => 3600], 200);
        }

        if (str_contains($url, '/pix/v2/cob/') || str_contains($url, '/pix/v2/cobv/')) {
            if (str_contains($url, 'erro')) {
                return Http::response(['erro' => 'interno'], 500);
            }
            if (str_contains($url, 'removida')) {
                return Http::response(['status' => 'REMOVIDA_PELO_USUARIO_RECEBEDOR'], 200);
            }
            if (str_contains($url, 'paga')) {
                return Http::response([
                    'status' => 'CONCLUIDA',
                    'pix'    => [['txid' => 'x', 'valor' => '150.00', 'horario' => '2026-06-01T10:00:00Z']],
                ], 200);
            }

            return Http::response(['status' => 'ATIVA', 'valor' => ['original' => '150.00']], 200);
        }

        return Http::response([], 404);
    });
}

function credInter(array $attrs = []): PaymentGatewayCredential
{
    return PaymentGatewayCredential::query()->create(array_merge([
        'business_id'  => 1,
        'gateway_key'  => 'inter',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'Inter Test',
        'config_json'  => ['client_id' => 'cli', 'client_secret' => 'sec'],
    ], $attrs));
}

function cobEmitida(PaymentGatewayCredential $cred, string $txid, array $attrs = []): Cobranca
{
    return Cobranca::query()->create(array_merge([
        'business_id'                   => $cred->business_id,
        'payment_gateway_credential_id' => $cred->id,
        'gateway_external_id'           => $txid,
        'tipo'                          => 'pix_cob',
        'status'                        => 'emitida',
        'valor_centavos'                => 99999,
        'idempotency_key'               => 'idem-' . $txid,
        'descricao'                     => 'Teste',
        'payload_gateway'               => [],
    ], $attrs));
}

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }
    pgEnsureSchema();
});

it('marca cobrança PIX paga quando Inter retorna CONCLUIDA + dispara CobrancaPaga', function () {
    Event::fake([CobrancaPaga::class]);
    fakeInterPixConsulta();
    $cred = credInter();
    $cobranca = cobEmitida($cred, 'txid-paga-001');

    $this->artisan('paymentgateway:inter-reconcile-pix', ['--business' => '1'])->assertExitCode(0);

    $cobranca->refresh();
    expect($cobranca->status)->toBe('paga');
    expect($cobranca->forma_pagamento)->toBe('pix');
    expect($cobranca->paga_em)->not->toBeNull();

    Event::assertDispatched(CobrancaPaga::class, fn (CobrancaPaga $e) => $e->cobrancaId === (int) $cobranca->id && $e->businessId === 1);
});

it('valor pago vem do Inter (pix.valor), não do valor_centavos local', function () {
    fakeInterPixConsulta();
    $cred = credInter();
    $cobranca = cobEmitida($cred, 'txid-paga-valor', ['valor_centavos' => 99999]);

    $this->artisan('paymentgateway:inter-reconcile-pix', ['--business' => '1'])->assertExitCode(0);

    expect($cobranca->fresh()->valor_pago_centavos)->toBe(15000);
});

it('NÃO marca cobrança ainda ATIVA', function () {
    Event::fake([CobrancaPaga::class]);
    fakeInterPixConsulta();
    $cred = credInter();
    $cobranca = cobEmitida($cred, 'txid-ativa-002');

    $this->artisan('paymentgateway:inter-reconcile-pix', ['--business' => '1'])->assertExitCode(0);

    expect($cobranca->fresh()->status)->toBe('emitida');
    Event::assertNotDispatched(CobrancaPaga::class);
});

it('NÃO marca cobrança REMOVIDA (cancelada no Inter)', function () {
    Event::fake([CobrancaPaga::class]);
    fakeInterPixConsulta();
    $cred = credInter();
    $cobranca = cobEmitida($cred, 'txid-removida-003');

    $this->artisan('paymentgateway:inter-reconcile-pix', ['--business' => '1'])->assertExitCode(0);

    expect($cobranca->fresh()->status)->toBe('emitida');
    Event::assertNotDispatched(CobrancaPaga::class);
});

it('erro de consulta no Inter (HTTP 500) → conta erro, exit FAILURE, cobrança intacta', function () {
    fakeInterPixConsulta();
    $cred = credInter();
    $cobranca = cobEmitida($cred, 'txid-erro-004');

    $this->artisan('paymentgateway:inter-reconcile-pix', ['--business' => '1'])->assertExitCode(1);

    expect($cobranca->fresh()->status)->toBe('emitida');
});

it('dry-run não altera nada nem dispara evento', function () {
    Event::fake([CobrancaPaga::class]);
    fakeInterPixConsulta();
    $cred = credInter();
    $cobranca = cobEmitida($cred, 'txid-paga-005');

    $this->artisan('paymentgateway:inter-reconcile-pix', ['--business' => '1', '--dry-run' => true])->assertExitCode(0);

    expect($cobranca->fresh()->status)->toBe('emitida');
    Event::assertNotDispatched(CobrancaPaga::class);
});

it('pix_cobv usa o endpoint cobv e marca paga', function () {
    fakeInterPixConsulta();
    $cred = credInter();
    $cobranca = cobEmitida($cred, 'txid-paga-cobv', ['tipo' => 'pix_cobv']);

    $this->artisan('paymentgateway:inter-reconcile-pix', ['--business' => '1'])->assertExitCode(0);

    expect($cobranca->fresh()->status)->toBe('paga');
    Http::assertSent(fn ($req) => str_contains($req->url(), '/pix/v2/cobv/txid-paga-cobv'));
});

it('quita o título vinculado à cobrança ao reconciliar', function () {
    fakeInterPixConsulta();
    $cred = credInter();
    $cobranca = cobEmitida($cred, 'txid-paga-titulo');
    $titulo = \Modules\Financeiro\Models\Titulo::query()->create([
        'business_id'     => 1,
        'numero'          => 'T-RC-1',
        'tipo'            => 'receber',
        'status'          => 'aberto',
        'valor_total'     => 150,
        'valor_aberto'    => 150,
        'emissao'         => now()->toDateString(),
        'vencimento'      => now()->addDay()->toDateString(),
        'competencia_mes' => now()->format('Y-m'),
        'origem'          => 'manual',
        'origem_id'       => (int) $cobranca->id,
        'created_by'      => 1,
    ]);

    $this->artisan('paymentgateway:inter-reconcile-pix', ['--business' => '1'])->assertExitCode(0);

    expect($titulo->fresh()->status)->toBe('quitado');
});

it('processa lote misto: paga a CONCLUIDA, deixa a ATIVA', function () {
    Event::fake([CobrancaPaga::class]);
    fakeInterPixConsulta();
    $cred = credInter();
    $paga = cobEmitida($cred, 'txid-paga-010');
    $ativa = cobEmitida($cred, 'txid-ativa-011');

    $this->artisan('paymentgateway:inter-reconcile-pix')->assertExitCode(0);

    expect($paga->fresh()->status)->toBe('paga');
    expect($ativa->fresh()->status)->toBe('emitida');
    Event::assertDispatchedTimes(CobrancaPaga::class, 1);
});

it('credencial inativa (ativo=false) é ignorada', function () {
    fakeInterPixConsulta();
    $cred = credInter(['ativo' => false]);
    $cobranca = cobEmitida($cred, 'txid-paga-inativa');

    $this->artisan('paymentgateway:inter-reconcile-pix', ['--business' => '1'])->assertExitCode(0);

    expect($cobranca->fresh()->status)->toBe('emitida');
});

it('sem credencial Inter ativa → SUCCESS no-op', function () {
    fakeInterPixConsulta();

    $this->artisan('paymentgateway:inter-reconcile-pix', ['--business' => '1'])->assertExitCode(0);

    expect(true)->toBeTrue();
});

it('cobrança fora da janela --days não é consultada', function () {
    fakeInterPixConsulta();
    $cred = credInter();
    $cobranca = cobEmitida($cred, 'txid-paga-velha');
    DB::table('cobrancas')->where('id', $cobranca->id)->update(['created_at' => now()->subDays(30)]);

    $this->artisan('paymentgateway:inter-reconcile-pix', ['--business' => '1', '--days' => '7'])->assertExitCode(0);

    expect($cobranca->fresh()->status)->toBe('emitida');
});

it('cobrança tipo boleto (não-pix) não é consultada', function () {
    fakeInterPixConsulta();
    $cred = credInter();
    $cobranca = cobEmitida($cred, 'txid-paga-boleto', ['tipo' => 'boleto']);

    $this->artisan('paymentgateway:inter-reconcile-pix', ['--business' => '1'])->assertExitCode(0);

    expect($cobranca->fresh()->status)->toBe('emitida');
});

it('multi-tenant: --business=1 não toca cobrança de outro tenant', function () {
    Event::fake([CobrancaPaga::class]);
    fakeInterPixConsulta();
    credInter();
    $cred99 = credInter(['business_id' => 99]);
    $cobranca99 = cobEmitida($cred99, 'txid-paga-biz99');

    $this->artisan('paymentgateway:inter-reconcile-pix', ['--business' => '1'])->assertExitCode(0);

    expect($cobranca99->fresh()->status)->toBe('emitida');
    Event::assertNotDispatched(CobrancaPaga::class);
});

it('sem --business: processa TODAS as credenciais Inter ativas (2 tenants)', function () {
    fakeInterPixConsulta();
    $cred1 = credInter(['business_id' => 1]);
    $cred7 = credInter(['business_id' => 7]);
    $c1 = cobEmitida($cred1, 'txid-paga-t1');
    $c7 = cobEmitida($cred7, 'txid-paga-t7');

    $this->artisan('paymentgateway:inter-reconcile-pix')->assertExitCode(0);

    expect($c1->fresh()->status)->toBe('paga');
    expect($c7->fresh()->status)->toBe('paga');
});

it('idempotente: cobrança já paga não é re-consultada nem re-dispara evento', function () {
    Event::fake([CobrancaPaga::class]);
    fakeInterPixConsulta();
    $cred = credInter();
    cobEmitida($cred, 'txid-paga-020', ['status' => 'paga', 'valor_pago_centavos' => 15000, 'forma_pagamento' => 'pix']);

    $this->artisan('paymentgateway:inter-reconcile-pix', ['--business' => '1'])->assertExitCode(0);

    Event::assertNotDispatched(CobrancaPaga::class);
});
