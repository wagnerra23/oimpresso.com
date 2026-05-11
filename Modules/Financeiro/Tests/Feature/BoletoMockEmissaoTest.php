<?php

declare(strict_types=1);

use App\Account;
use App\Business;
use App\Transaction;
use App\User;
use Modules\Financeiro\Models\BoletoRemessa;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Services\TituloAutoService;
use Modules\Financeiro\Services\TituloService;

uses(Tests\TestCase::class);

/**
 * Smoke test do fluxo mock de emissão de boleto via CnabDirectStrategy.
 *
 * Cobre: Wagner 2026-05-12 "todos em paralelo" — caminho C (mock pra
 * testar fluxo end-to-end sem credenciais reais Inter/Asaas).
 *
 * O CnabDirectStrategy.php gera linha digitável + código de barras + PDF
 * 100% offline via lib eduardokum/laravel-boleto. Status persistido como
 * 'gerado_mock'. Não chama API banco — antes da ondas seguintes (CNAB 240
 * remessa + SFTP/API + parser retorno).
 *
 * Asserta:
 *  - emitirBoleto retorna BoletoRemessa válida pra banco Inter (077)
 *  - status = 'gerado_mock'
 *  - linha_digitavel + nosso_numero não vazios (geração local funcionou)
 *  - business_id scoped corretamente (Tier 0 ADR 0093)
 *  - idempotência: chamar 2x retorna mesma remessa (mesmo idempotency_key)
 *
 * Padrão de skip: igual outros testes do módulo Financeiro — pula gracioso
 * quando phpunit.xml força sqlite :memory: (config default). Roda contra
 * MySQL real via env override (DB_CONNECTION=mysql php vendor/bin/pest ...).
 */

function boletoMockBootstrap(): array
{
    try {
        $business = Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: ' . $e->getMessage());
    }

    if (! $business) {
        test()->markTestSkipped('Sem business no banco — rode seeder UltimatePOS antes.');
    }

    $user = User::where('business_id', $business->id)->first();
    if (! $user) {
        test()->markTestSkipped('Sem user no business.');
    }

    // ContaBancaria precisa de Account (App\Account) base do UltimatePOS.
    // Usa a primeira account do business — se não houver, skip.
    $account = Account::where('business_id', $business->id)->first();
    if (! $account) {
        test()->markTestSkipped('Sem Account UltimatePOS — rode seeder antes.');
    }

    return ['business' => $business, 'user' => $user, 'account' => $account];
}

/**
 * Cria ContaBancaria de teste com banco Inter (077) SEM credenciais API
 * — força CnabDirectStrategy a usar caminho mock-only.
 */
function boletoMockCriarContaBancariaInter(int $businessId, int $accountId): ContaBancaria
{
    return ContaBancaria::create([
        'business_id'                 => $businessId,
        'account_id'                  => $accountId,
        'banco_codigo'                => '077',
        'agencia'                     => '0001',
        'carteira'                    => '112',
        'beneficiario_documento'      => '12345678000199',
        'beneficiario_razao_social'   => 'TESTE EMISSAO BOLETO MOCK LTDA',
        'beneficiario_logradouro'     => 'Rua Teste, 100',
        'beneficiario_bairro'         => 'Centro',
        'beneficiario_cidade'         => 'São Paulo',
        'beneficiario_uf'             => 'SP',
        'beneficiario_cep'            => '01000000',
        'ativo_para_boleto'           => true,
    ]);
}

function boletoMockCriarTituloDeVenda(int $businessId, int $userId): array
{
    /** @var Transaction $tx */
    $tx = Transaction::create([
        'business_id'           => $businessId,
        'location_id'           => null,
        'type'                  => 'sell',
        'status'                => 'final',
        'payment_status'        => 'due',
        'contact_id'            => null,
        'invoice_no'            => 'TEST-BOLETO-' . uniqid(),
        'transaction_date'      => '2026-06-15 12:00:00',
        'pay_term_number'       => 30,
        'pay_term_type'         => 'days',
        'total_before_tax'      => 250.00,
        'final_total'           => 250.00,
        'total_remaining_amount' => 250.00,
        'created_by'            => $userId,
    ]);

    /** @var Titulo $titulo */
    $titulo = app(TituloAutoService::class)->sincronizarDeTransacao($tx);

    return ['tx' => $tx, 'titulo' => $titulo];
}

function boletoMockCleanup(Transaction $tx, ContaBancaria $conta): void
{
    // BoletoRemessa primeiro (FK)
    BoletoRemessa::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('business_id', $tx->business_id)
        ->where('conta_bancaria_id', $conta->id)
        ->forceDelete();

    // Titulo (FK pra Transaction)
    Titulo::query()
        ->withoutGlobalScope(BusinessScopeImpl::class)
        ->where('business_id', $tx->business_id)
        ->where('origem', 'venda')
        ->where('origem_id', $tx->id)
        ->forceDelete();

    \DB::table('transactions')->where('id', $tx->id)->delete();

    // ContaBancaria por último
    $conta->forceDelete();
}

it('CnabDirectStrategy gera BoletoRemessa mock pra Inter (077) sem API real', function () {
    ['business' => $business, 'user' => $user, 'account' => $account] = boletoMockBootstrap();

    config(['app.timezone' => 'America/Sao_Paulo']);

    $conta = boletoMockCriarContaBancariaInter($business->id, $account->id);
    ['tx' => $tx, 'titulo' => $titulo] = boletoMockCriarTituloDeVenda($business->id, $user->id);

    try {
        $service = app(TituloService::class);
        $remessa = $service->emitirBoleto($titulo, $conta->id);

        expect($remessa)->toBeInstanceOf(BoletoRemessa::class);
        expect($remessa->status)->toBe(BoletoRemessa::STATUS_GERADO_MOCK);
        expect($remessa->business_id)->toBe($business->id);
        expect($remessa->titulo_id)->toBe($titulo->id);
        expect($remessa->conta_bancaria_id)->toBe($conta->id);
    } finally {
        boletoMockCleanup($tx, $conta);
    }
})->group('boleto-mock');

it('linha digitável + nosso_numero + código de barras gerados localmente (lib eduardokum)', function () {
    ['business' => $business, 'user' => $user, 'account' => $account] = boletoMockBootstrap();

    $conta = boletoMockCriarContaBancariaInter($business->id, $account->id);
    ['tx' => $tx, 'titulo' => $titulo] = boletoMockCriarTituloDeVenda($business->id, $user->id);

    try {
        $remessa = app(TituloService::class)->emitirBoleto($titulo, $conta->id);

        // Linha digitável: 47 dígitos formatados com pontos/espaços
        expect($remessa->linha_digitavel)
            ->not->toBeEmpty()
            ->and(preg_replace('/\D/', '', $remessa->linha_digitavel))
            ->toHaveLength(47);

        // Nosso número: gerado pela lib (formato varia por banco)
        expect($remessa->nosso_numero)->not->toBeEmpty();

        // Código de barras: 44 dígitos (FEBRABAN)
        expect($remessa->codigo_barras)
            ->not->toBeEmpty()
            ->and(preg_replace('/\D/', '', $remessa->codigo_barras))
            ->toHaveLength(44);

        // Valor preserved
        expect((float) $remessa->valor_total)->toBe(250.00);
    } finally {
        boletoMockCleanup($tx, $conta);
    }
})->group('boleto-mock');

it('idempotência: emitir 2x mesma combinação (titulo, conta) não duplica', function () {
    ['business' => $business, 'user' => $user, 'account' => $account] = boletoMockBootstrap();

    $conta = boletoMockCriarContaBancariaInter($business->id, $account->id);
    ['tx' => $tx, 'titulo' => $titulo] = boletoMockCriarTituloDeVenda($business->id, $user->id);

    try {
        $service = app(TituloService::class);

        $r1 = $service->emitirBoleto($titulo, $conta->id);
        $r2 = $service->emitirBoleto($titulo, $conta->id);

        // Mesma remessa (ou re-uso por idempotency_key)
        expect($r1->id)->toBe($r2->id);

        $count = BoletoRemessa::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->where('business_id', $business->id)
            ->where('titulo_id', $titulo->id)
            ->where('conta_bancaria_id', $conta->id)
            ->count();
        expect($count)->toBe(1);
    } finally {
        boletoMockCleanup($tx, $conta);
    }
})->group('boleto-mock');
