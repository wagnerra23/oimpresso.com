<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloBaixa;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Drivers\InterDriver;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

/**
 * US-PG-008 — paymentgateway:inter-importar-recebimentos.
 * Importa boletos pagos no Inter como título recebido + baixa no Financeiro.
 *
 * Schema mínimo via beforeEach (SQLite sem migrations). Multi-tenant Tier 0 biz=1.
 */

if (! function_exists('pgImportEnsureSchema')) {
    function pgImportEnsureSchema(): void
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
                $t->string('ambiente')->default('production');
                $t->boolean('ativo')->default(true);
                $t->string('nome_display')->nullable();
                $t->longText('config_json')->nullable();
                $t->unsignedBigInteger('conta_bancaria_id')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('fin_contas_bancarias')) {
            Schema::create('fin_contas_bancarias', function ($t) {
                $t->increments('id');
                $t->integer('business_id')->unsigned()->index();
                $t->string('nome')->nullable();
                $t->boolean('ativo_para_boleto')->default(false);
                $t->integer('account_id')->unsigned()->nullable();
                $t->unsignedBigInteger('payment_gateway_credential_id')->nullable();
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
                $t->text('observacoes')->nullable();
                $t->json('metadata')->nullable();
                $t->integer('created_by')->unsigned()->nullable();
                $t->integer('updated_by')->unsigned()->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }
        if (! Schema::hasTable('fin_titulo_baixas')) {
            Schema::create('fin_titulo_baixas', function ($t) {
                $t->increments('id');
                $t->integer('business_id')->unsigned()->index();
                $t->integer('titulo_id')->unsigned();
                $t->integer('conta_bancaria_id')->unsigned()->nullable();
                $t->decimal('valor_baixa', 22, 4)->default(0);
                $t->decimal('juros', 22, 4)->default(0);
                $t->decimal('multa', 22, 4)->default(0);
                $t->decimal('desconto', 22, 4)->default(0);
                $t->date('data_baixa')->nullable();
                $t->string('meio_pagamento')->nullable();
                $t->string('idempotency_key')->nullable();
                $t->integer('transaction_payment_id')->unsigned()->nullable();
                $t->integer('estorno_de_id')->unsigned()->nullable();
                $t->text('observacoes')->nullable();
                $t->integer('created_by')->unsigned()->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }
    }
}

function fakeInterLista(array $cobrancas, int $totalPaginas = 1): void
{
    Http::fake([
        '*/oauth/v2/token'        => Http::response(['access_token' => 'tk', 'expires_in' => 3600], 200),
        '*/cobranca/v3/cobrancas*' => Http::response(['cobrancas' => $cobrancas, 'totalPaginas' => $totalPaginas], 200),
    ]);
}

function credInterImport(): PaymentGatewayCredential
{
    return PaymentGatewayCredential::query()->create([
        'business_id'  => 1,
        'gateway_key'  => 'inter',
        'ambiente'     => 'production',
        'ativo'        => true,
        'nome_display' => 'Inter WR2',
        'config_json'  => ['client_id' => 'x', 'client_secret' => 'y'],
    ]);
}

function itemCobranca(string $codigo, float $valor, string $seu = '', string $nosso = ''): array
{
    return [
        'cobranca' => [
            'codigoSolicitacao' => $codigo,
            'seuNumero'         => $seu,
            'situacao'          => 'RECEBIDO',
            'valorNominal'      => $valor,
            'dataSituacao'      => '2026-06-01',
            'pagador'           => ['nome' => 'Cliente ' . $codigo],
        ],
        'boleto' => ['nossoNumero' => $nosso],
    ];
}

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }
    session(['business.id' => 1]);
    pgImportEnsureSchema();
});

it('driver lista cobranças pagas com paginação', function () {
    Http::fake([
        '*/oauth/v2/token' => Http::response(['access_token' => 'tk'], 200),
        '*/cobranca/v3/cobrancas*' => Http::sequence()
            ->push(['cobrancas' => [itemCobranca('a', 10), itemCobranca('b', 20)], 'totalPaginas' => 2])
            ->push(['cobrancas' => [itemCobranca('c', 30)], 'totalPaginas' => 2]),
    ]);

    $cred = credInterImport();
    $itens = (new InterDriver())->listarCobrancasPagas($cred, new DateTimeImmutable('-30 days'), new DateTimeImmutable('today'));

    expect($itens)->toHaveCount(3);
});

it('dry-run não grava nada', function () {
    fakeInterLista([itemCobranca('dry-1', 150.00, 'SEU-1', 'NN-1')]);
    credInterImport();

    $this->artisan('paymentgateway:inter-importar-recebimentos', ['--business' => '1', '--dry-run' => true])
        ->assertExitCode(0);

    expect(Titulo::withoutGlobalScopes()->count())->toBe(0);
});

it('importa boleto pago: cria título receber/quitado + baixa', function () {
    fakeInterLista([itemCobranca('imp-1', 313.38, 'SEU-50', 'NN-9001')]);
    credInterImport();
    $contaId = \Modules\Financeiro\Models\ContaBancaria::query()->create([
        'business_id' => 1, 'nome' => 'Inter', 'ativo_para_boleto' => true,
    ])->id;

    $this->artisan('paymentgateway:inter-importar-recebimentos', ['--business' => '1', '--conta' => (string) $contaId])
        ->assertExitCode(0);

    $titulo = Titulo::withoutGlobalScopes()->where('business_id', 1)->first();
    expect($titulo)->not->toBeNull();
    expect($titulo->tipo)->toBe('receber');
    expect($titulo->status)->toBe('quitado');
    expect((float) $titulo->valor_total)->toBe(313.38);
    expect((float) $titulo->valor_aberto)->toBe(0.0);
    expect($titulo->cliente_descricao)->toBe('Cliente imp-1');
    expect($titulo->metadata['inter_ref'])->toBe('imp-1');

    $baixa = TituloBaixa::withoutGlobalScopes()->where('titulo_id', $titulo->id)->first();
    expect($baixa)->not->toBeNull();
    expect((float) $baixa->valor_baixa)->toBe(313.38);
    expect($baixa->meio_pagamento)->toBe('boleto');
    expect($baixa->conta_bancaria_id)->toBe($contaId);
});

it('idempotente: re-run não duplica', function () {
    fakeInterLista([itemCobranca('idem-1', 100.00, 'S', 'N')]);
    credInterImport();
    $contaId = \Modules\Financeiro\Models\ContaBancaria::query()->create([
        'business_id' => 1, 'ativo_para_boleto' => true,
    ])->id;

    $this->artisan('paymentgateway:inter-importar-recebimentos', ['--conta' => (string) $contaId])->assertExitCode(0);
    $this->artisan('paymentgateway:inter-importar-recebimentos', ['--conta' => (string) $contaId])->assertExitCode(0);

    expect(Titulo::withoutGlobalScopes()->where('business_id', 1)->count())->toBe(1);
    expect(TituloBaixa::withoutGlobalScopes()->count())->toBe(1);
});

it('sem conta: cria título aberto sem baixa', function () {
    fakeInterLista([itemCobranca('semconta-1', 50.00)]);
    credInterImport();

    $this->artisan('paymentgateway:inter-importar-recebimentos', ['--business' => '1'])->assertExitCode(0);

    $titulo = Titulo::withoutGlobalScopes()->where('business_id', 1)->first();
    expect($titulo)->not->toBeNull();
    expect($titulo->status)->toBe('aberto');
    expect(TituloBaixa::withoutGlobalScopes()->count())->toBe(0);
});

it('multi-tenant: sem credencial Inter pra biz → FAILURE', function () {
    $this->artisan('paymentgateway:inter-importar-recebimentos', ['--business' => '7'])->assertExitCode(1);
});
