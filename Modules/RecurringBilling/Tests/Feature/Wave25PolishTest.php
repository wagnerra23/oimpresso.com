<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Wave 25 RecurringBilling POLISH — D5 README "como cliente usa".
 *
 * Cobre:
 *   - README.md presente em Modules/RecurringBilling/ (D5 documentação UX-narrativo)
 *   - README contém seções canônicas (lifecycle, drivers, multi-tenant, anti-patterns)
 *   - Preserva US-RB-044 NFe-de-boleto-pago como canônico irrevogável (sentry test)
 *
 * Smoke: garante que documentação não-vazia + estrutura mínima.
 *
 * @see Modules/RecurringBilling/README.md
 */
describe('Wave 25 RecurringBilling Polish', function () {
    it('D5 — README.md presente em Modules/RecurringBilling/', function () {
        $path = __DIR__ . '/../../README.md';
        expect(file_exists($path))->toBeTrue();

        $content = file_get_contents($path);
        expect(strlen($content))->toBeGreaterThan(2000); // README substancial, não placeholder
    });

    it('D5 — README contém seção "Para o cliente final"', function () {
        $content = file_get_contents(__DIR__ . '/../../README.md');

        expect($content)->toContain('Para o cliente final');
        expect($content)->toContain('Cadastra um plano');
        expect($content)->toContain('Assina cliente');
    });

    it('D5 — README descreve lifecycle Subscription canônico', function () {
        $content = file_get_contents(__DIR__ . '/../../README.md');

        expect($content)->toContain('Lifecycle Subscription canônico');
        expect($content)->toContain('active');
        expect($content)->toContain('paused');
        expect($content)->toContain('canceled');
        expect($content)->toContain('overdue');
    });

    it('D5 — README lista 3 drivers boleto (Inter, C6, Asaas)', function () {
        $content = file_get_contents(__DIR__ . '/../../README.md');

        expect($content)->toContain('InterDriver');
        expect($content)->toContain('C6Driver');
        expect($content)->toContain('AsaasDriver');
    });

    it('D5 — README cita Multi-tenant Tier 0 + OTel spans', function () {
        $content = file_get_contents(__DIR__ . '/../../README.md');

        expect($content)->toContain('Multi-tenant Tier 0');
        expect($content)->toContain('ADR 0093');
        expect($content)->toContain('OtelHelper::spanBiz');
        expect($content)->toContain('rb.assinatura.criar');
    });

    it('D5 — README documenta US-RB-044 NFe-de-boleto-pago como canônico irrevogável', function () {
        $content = file_get_contents(__DIR__ . '/../../README.md');

        // Sentry test — proteção contra remoção acidental do canônico
        expect($content)->toContain('US-RB-044');
        expect($content)->toContain('NFe-de-boleto-pago');
        expect($content)->toContain('canônico irrevogável');
    });

    it('D5 — README documenta health command com 10 sinais', function () {
        $content = file_get_contents(__DIR__ . '/../../README.md');

        expect($content)->toContain('recurring:health');
        expect($content)->toContain('--detail');
        expect($content)->toMatch('/10 sinais|--alert/');
    });
});
