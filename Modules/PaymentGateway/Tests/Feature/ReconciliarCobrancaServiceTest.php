<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Models\Titulo;
use Modules\PaymentGateway\Events\CobrancaPaga;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Services\ReconciliarCobrancaService;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

/**
 * ReconciliarCobrancaService::marcarPaga() — lógica canônica "virou paga"
 * compartilhada entre o webhook (ProcessarWebhookPixInterJob) e o polling
 * (InterReconcilePixCommand).
 *
 * Schema mínimo montado em beforeEach (guardado por hasTable) pra rodar em
 * SQLite sem migrations — as migrations UltimatePOS são MySQL-only. Em MySQL
 * (suite local/CI real) as tabelas já existem e o build é pulado.
 *
 * Multi-tenant Tier 0: business_id=1 (ADR 0101).
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

function novoTitulo(array $attrs = []): Titulo
{
    return Titulo::query()->create(array_merge([
        'business_id'       => 1,
        'numero'            => 'T-' . random_int(1000, 999999),
        'tipo'              => 'receber',
        'status'            => 'aberto',
        'valor_total'       => 100,
        'valor_aberto'      => 100,
        'emissao'           => now()->toDateString(),
        'vencimento'        => now()->addDays(5)->toDateString(),
        'competencia_mes'   => now()->format('Y-m'),
        'origem'            => 'manual',
        'created_by'        => 1,
    ], $attrs));
}

function novaCobranca(array $attrs = []): Cobranca
{
    return Cobranca::query()->create(array_merge([
        'business_id'     => 1,
        'tipo'            => 'pix_cob',
        'status'          => 'emitida',
        'valor_centavos'  => 15000,
        'idempotency_key' => 'idem-' . random_int(1000, 999999),
        'descricao'       => 'Teste',
        'payload_gateway' => [],
    ], $attrs));
}

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }
    pgEnsureSchema();
});

it('marca cobrança paga: status + valor_pago + paga_em + forma', function () {
    $cobranca = novaCobranca();
    $pagaEm = new DateTimeImmutable('2026-06-01 10:00:00');

    app(ReconciliarCobrancaService::class)->marcarPaga($cobranca, 1, 15000, $pagaEm, 'pix');

    $cobranca->refresh();
    expect($cobranca->status)->toBe('paga');
    expect($cobranca->valor_pago_centavos)->toBe(15000);
    expect($cobranca->forma_pagamento)->toBe('pix');
    expect($cobranca->paga_em)->not->toBeNull();
});

it('dispara CobrancaPaga com dados corretos', function () {
    Event::fake([CobrancaPaga::class]);
    $cobranca = novaCobranca(['payer_cpf_cnpj' => '12345678900', 'origem_type' => 'sale', 'origem_id' => 77]);

    app(ReconciliarCobrancaService::class)->marcarPaga($cobranca, 1, 15000, new DateTimeImmutable(), 'pix');

    Event::assertDispatched(CobrancaPaga::class, function (CobrancaPaga $e) use ($cobranca) {
        return $e->cobrancaId === (int) $cobranca->id
            && $e->businessId === 1
            && $e->valorPagoCentavos === 15000
            && $e->formaPagamento === 'pix'
            && $e->payerCpfCnpj === '12345678900'
            && $e->origemType === 'sale'
            && $e->origemId === 77;
    });
});

it('quita título vinculado (origem manual + origem_id) status aberto → quitado, valor_aberto 0', function () {
    $cobranca = novaCobranca();
    $titulo = novoTitulo(['origem' => 'manual', 'origem_id' => (int) $cobranca->id, 'status' => 'aberto', 'valor_aberto' => 100]);

    app(ReconciliarCobrancaService::class)->marcarPaga($cobranca, 1, 15000, new DateTimeImmutable(), 'pix');

    $titulo->refresh();
    expect($titulo->status)->toBe('quitado');
    expect((float) $titulo->valor_aberto)->toBe(0.0);
});

it('quita também título com status parcial', function () {
    $cobranca = novaCobranca();
    $titulo = novoTitulo(['origem' => 'manual', 'origem_id' => (int) $cobranca->id, 'status' => 'parcial', 'valor_aberto' => 50]);

    app(ReconciliarCobrancaService::class)->marcarPaga($cobranca, 1, 15000, new DateTimeImmutable(), 'pix');

    expect($titulo->fresh()->status)->toBe('quitado');
});

it('NÃO toca título de outro origem_id', function () {
    $cobranca = novaCobranca();
    $outro = novoTitulo(['origem' => 'manual', 'origem_id' => 999999, 'status' => 'aberto']);

    app(ReconciliarCobrancaService::class)->marcarPaga($cobranca, 1, 15000, new DateTimeImmutable(), 'pix');

    expect($outro->fresh()->status)->toBe('aberto');
});

it('NÃO toca título já quitado', function () {
    $cobranca = novaCobranca();
    $jaQuitado = novoTitulo(['origem' => 'manual', 'origem_id' => (int) $cobranca->id, 'status' => 'quitado', 'valor_aberto' => 0]);

    app(ReconciliarCobrancaService::class)->marcarPaga($cobranca, 1, 15000, new DateTimeImmutable(), 'pix');

    expect($jaQuitado->fresh()->status)->toBe('quitado');
});

it('multi-tenant: título de outro business NÃO é tocado', function () {
    $cobranca = novaCobranca();
    $tituloBiz99 = novoTitulo(['business_id' => 99, 'origem' => 'manual', 'origem_id' => (int) $cobranca->id, 'status' => 'aberto']);

    app(ReconciliarCobrancaService::class)->marcarPaga($cobranca, 1, 15000, new DateTimeImmutable(), 'pix');

    expect($tituloBiz99->fresh()->status)->toBe('aberto');
});

it('idempotente: cobrança já paga não regride/duplica (status permanece paga)', function () {
    $cobranca = novaCobranca(['status' => 'paga', 'valor_pago_centavos' => 15000, 'forma_pagamento' => 'pix']);

    app(ReconciliarCobrancaService::class)->marcarPaga($cobranca, 1, 15000, new DateTimeImmutable(), 'pix');

    expect($cobranca->fresh()->status)->toBe('paga');
});
