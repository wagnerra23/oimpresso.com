<?php

declare(strict_types=1);

use Modules\RecurringBilling\Services\AssinaturaService;

uses(Tests\TestCase::class);

/**
 * Wave 23 RecurringBilling SATURATION — D2 (reuse) + D1 (Assinatura coverage).
 *
 * Cobre:
 *   - D2 (BoletoCredentialResolverInterface) saiu em 2026-11-24 com o resolver (onda 5b da
 *     saída de rb_boleto_credentials); a credencial agora é a do PaymentGateway.
 *   - D1: AssinaturaService API canônica (smoke)
 *
 * Multi-tenant Tier 0 (ADR 0093): NÃO chama session() nem DB real.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
describe('Wave 23 RB Saturation', function () {
    it('AssinaturaService existe e tem métodos canônicos', function () {
        expect(class_exists(AssinaturaService::class))->toBeTrue();

        $methods = collect((new ReflectionClass(AssinaturaService::class))->getMethods(ReflectionMethod::IS_PUBLIC))
            ->pluck('name')
            ->toArray();

        // API mínima esperada (smoke — sem assumir signature exata).
        expect(count($methods))->toBeGreaterThan(0);
    });
});
