<?php

declare(strict_types=1);

use App\Util\OtelHelper;
use Modules\RecurringBilling\Console\Commands\RecurringHealthCommand;

uses(Tests\TestCase::class);

/**
 * Wave 16 governance — D9 OTel observability Modules/RecurringBilling.
 *
 * Cenarios cobertos:
 *  1. BoletoService.emitir/cancelar/refundAsaas wrap em OtelHelper::spanBiz (D9.a)
 *  2. AssinaturaCobrancaService.cancelInvoice/atualizarCobrancaAssinatura wrap (D9.a)
 *  3. Logs estruturados: rb.boleto.emitido / rb.boleto.refund_asaas.executed / rb.subscription.atualizada (D9.b)
 *  4. RecurringHealthCommand instancia + signature canonica (D9.c)
 *
 * Tier 0: NUNCA biz=4 ROTA LIVRE prod (ADR 0101).
 *
 * @see memory/decisions/0155-module-grade-v3.md D9.a + D9.b + D9.c
 * @see app/Util/OtelHelper.php
 */

beforeEach(function () {
    config()->set('otel.enabled', false);
});

it('D9.a — OtelHelper::spanBiz envolve callback RB sem alterar retorno', function () {
    $resultado = OtelHelper::spanBiz('rb.test_smoke', function () {
        return ['ok' => true, 'modulo' => 'RecurringBilling'];
    }, ['module' => 'RecurringBilling', 'op' => 'test_smoke']);

    expect($resultado)->toBe(['ok' => true, 'modulo' => 'RecurringBilling']);
});

it('D9.a — BoletoService source contem spanBiz nos metodos canon (emitir/cancelar/pdf/refund)', function () {
    $source = file_get_contents(__DIR__ . '/../../Services/Boleto/BoletoService.php');

    expect($source)->toContain('use App\Util\OtelHelper');
    expect($source)->toContain("OtelHelper::spanBiz('rb.boleto.emitir'");
    expect($source)->toContain("OtelHelper::spanBiz('rb.boleto.cancelar'");
    expect($source)->toContain("OtelHelper::spanBiz('rb.boleto.pdf'");
    expect($source)->toContain("OtelHelper::spanBiz('rb.boleto.refund_asaas'");
});

it('D9.a — AssinaturaCobrancaService source contem spanBiz nos metodos canon', function () {
    $source = file_get_contents(__DIR__ . '/../../Services/AssinaturaCobrancaService.php');

    expect($source)->toContain('use App\Util\OtelHelper');
    expect($source)->toContain("OtelHelper::spanBiz('rb.invoice.cancel'");
    expect($source)->toContain("OtelHelper::spanBiz('rb.subscription.update'");
});

it('D9.b — BoletoService emite log estruturado rb.boleto.emitido + rb.boleto.refund_asaas.executed', function () {
    $source = file_get_contents(__DIR__ . '/../../Services/Boleto/BoletoService.php');
    expect($source)->toContain("Log::info('rb.boleto.emitido'");
    expect($source)->toContain("Log::info('rb.boleto.refund_asaas.executed'");
});

it('D9.b — AssinaturaCobrancaService emite log estruturado rb.subscription.atualizada (US-RB-044 NFe)', function () {
    $source = file_get_contents(__DIR__ . '/../../Services/AssinaturaCobrancaService.php');
    expect($source)->toContain("Log::info('rb.subscription.atualizada'");
});

it('D9.c — RecurringHealthCommand instancia e tem signature canonica', function () {
    $cmd = new RecurringHealthCommand();
    $ref = new ReflectionClass($cmd);
    $prop = $ref->getProperty('signature');
    $prop->setAccessible(true);
    $sig = $prop->getValue($cmd);

    expect($sig)->toContain('rb:health');
    expect($sig)->toContain('--business=');
    expect($sig)->toContain('--alert');
    expect($sig)->toContain('--json');
});

it('D9.c — rb:health registrado em Artisan via ServiceProvider', function () {
    $output = \Illuminate\Support\Facades\Artisan::all();
    expect($output)->toHaveKey('rb:health');
});
