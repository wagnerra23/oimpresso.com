<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Listeners\OnCobrancaPagaCreateFinanceiroTitulo;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloBaixa;
use Modules\PaymentGateway\Events\CobrancaPaga;
use Modules\PaymentGateway\Models\Cobranca;

uses(Tests\TestCase::class);

/**
 * Auditoria do Financeiro 2026-11-05 (lote 1.2) — o listener da cobrança paga grava no
 * business_id do EVENTO, nunca num 1 fixo.
 *
 * Contrato (docblock do listener + ADR 0093): a empresa que processa é a dona do SaaS
 * (`app.saas_owner_business_id`), e o título e a baixa nascem nela. Antes do conserto o
 * filtro de entrada lia a config, mas o `Titulo::create` e o `TituloBaixa::create` gravavam
 * `business_id => 1` — com a dona configurada como outra empresa, a receita ia para a 1.
 *
 * Tenant 98 (ADR 0358) fazendo o papel de dona; tudo dentro de transação desfeita no fim.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS + Financeiro + PaymentGateway.');
    }
    if (! Schema::hasTable('fin_titulos') || ! Schema::hasTable('cobrancas')) {
        $this->markTestSkipped('Schema Financeiro + PaymentGateway ausente.');
    }

    $this->bizId = (int) $this->seededTenant()->id;
    if ($this->bizId === 1) {
        // Com o tenant de teste igual a 1 o caso não distingue o conserto do 1 fixo.
        $this->markTestSkipped('Tenant de teste resolveu para 1; o caso precisa de outra empresa (seed biz=98).');
    }

    DB::beginTransaction();
    config(['app.saas_owner_business_id' => $this->bizId]);

    $accountId = DB::table('accounts')->insertGetId([
        'business_id' => $this->bizId,
        'name' => 'ZZ-COBPAGA Conta',
        'account_number' => '778',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $this->contaId = DB::table('fin_contas_bancarias')->insertGetId([
        'business_id' => $this->bizId,
        'account_id' => $accountId,
        'banco_codigo' => '077',
        'agencia' => '0001',
        'carteira' => '112',
        'beneficiario_documento' => '00.000.000/0000-00', // pii-allowlist — fixture de teste
        'beneficiario_razao_social' => 'Teste cobrança paga',
        'ativo_para_boleto' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('cobrança paga na empresa dona (98) gera título e baixa na 98, nunca na 1', function () {
    $cobranca = Cobranca::create([
        'business_id' => $this->bizId,
        'payment_gateway_credential_id' => null,
        'gateway_external_id' => 'TEST-'.uniqid(),
        'tipo' => 'pix_recv',
        'status' => 'paga',
        'valor_centavos' => 4590,
        'valor_pago_centavos' => 4590,
        'vencimento' => now()->toDateString(),
        'paga_em' => now(),
        'contact_id' => null,
        'payer_name' => 'Tenant Test',
        'descricao' => 'Cobrança paga · lote 1.2',
        'idempotency_key' => 'test-cobpaga-biz-'.uniqid(),
        'origem_type' => 'subscription_license',
        'origem_id' => 12345,
        'forma_pagamento' => 'pix',
    ]);

    (new OnCobrancaPagaCreateFinanceiroTitulo())->handle(new CobrancaPaga(
        cobrancaId: $cobranca->id,
        businessId: $this->bizId,
        valorPagoCentavos: 4590,
        pagaEm: new \DateTimeImmutable(),
        formaPagamento: 'pix',
        occurredAt: new \DateTimeImmutable(),
        payerCpfCnpj: null,
        origemType: 'subscription_license',
        origemId: 12345,
    ));

    $titulos = fn (int $biz) => Titulo::withoutGlobalScopes()
        ->where('business_id', $biz)->where('origem', 'manual')->where('origem_id', $cobranca->id);

    expect($titulos(1)->count())->toBe(0);
    $titulo = $titulos($this->bizId)->first();
    expect($titulo)->not->toBeNull();
    expect((float) $titulo->valor_total)->toBe(45.9);

    $baixa = TituloBaixa::withoutGlobalScopes()->where('titulo_id', $titulo->id)->first();
    expect($baixa)->not->toBeNull();
    expect((int) $baixa->business_id)->toBe($this->bizId);
    expect((int) $baixa->conta_bancaria_id)->toBe((int) $this->contaId);
    expect((int) DB::table('users')->where('id', $baixa->created_by)->value('business_id'))->toBe($this->bizId);
});
