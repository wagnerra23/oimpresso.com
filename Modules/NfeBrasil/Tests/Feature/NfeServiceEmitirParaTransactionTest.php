<?php

declare(strict_types=1);

use App\Transaction;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Services\NfeService;
use Tests\Builders\TransactionBuilder;

uses(Tests\TestCase::class);

/**
 * US-NFE-002 fase 2A · NfeService::emitirParaTransaction validações early.
 *
 * Tests cobrem **pré-validações que falham fast antes de tocar SEFAZ**.
 * Submissão SEFAZ real (signNFe + sefazEnviaLote) é integration test
 * separado contra ambiente homologação (não rodam em CI).
 *
 * Cobertura:
 *   1. Transaction com final_total <= 0 → RuntimeException
 *   2. Business não encontrado → RuntimeException (validação 2ª etapa)
 *
 * Outras validações (NCM ausente, cert vencido) já são cobertas pelo
 * `emitirParaInvoice` test existente — pattern compartilhado via classe base
 * `NfeService` privates.
 */

beforeEach(function () {
    if (! Schema::hasTable('nfe_business_configs')) {
        $this->markTestSkipped('nfe_business_configs migration não rodou');
    }
});

it('lança RuntimeException quando final_total <= 0', function () {
    $service = app(NfeService::class);

    $tx = TransactionBuilder::venda()
        ->business(1)
        ->id(1)
        ->finalTotal(0.00)
        ->build();

    $service->emitirParaTransaction($tx);
})->throws(RuntimeException::class, 'sem valor positivo');

it('lança RuntimeException quando final_total é negativo', function () {
    $service = app(NfeService::class);

    $tx = TransactionBuilder::venda()
        ->business(1)
        ->id(2)
        ->finalTotal(-50.00)
        ->build();

    $service->emitirParaTransaction($tx);
})->throws(RuntimeException::class, 'sem valor positivo');

it('lança RuntimeException quando business não existe', function () {
    $service = app(NfeService::class);

    $tx = TransactionBuilder::venda()
        ->business(99999) // business inexistente
        ->id(3)
        ->finalTotal(100.00)
        ->build();

    $service->emitirParaTransaction($tx);
})->throws(RuntimeException::class);
