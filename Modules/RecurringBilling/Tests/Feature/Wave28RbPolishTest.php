<?php

declare(strict_types=1);

use Modules\NfeBrasil\Listeners\EmitirNFeAoReceberPagamento;
use Modules\NfeBrasil\Providers\NfeBrasilServiceProvider;
use Modules\RecurringBilling\Events\InvoicePaid;
use Modules\RecurringBilling\Services\AssinaturaService;

uses(Tests\TestCase::class);

/**
 * Wave 28 RecurringBilling POLISH ≥92 — D2 +3 AssinaturaService + US-RB-044 sentry expand.
 *
 * Estratégia: reflection + source-grep, ZERO hit DB (paralelização worktree W28).
 *
 * Cobre adicional sobre Waves 17+18+25+27:
 *   - D2 AssinaturaService: 4 spans rb.assinatura.* presença (consistência observability)
 *   - D2 AssinaturaService: businessId sempre 1º param + cross-tenant guard
 *     (defesa profundidade — Service NÃO confia em session em jobs assíncronos)
 *   - D2 AssinaturaService: calcularProximoVencimento helper canon documentado
 *   - US-RB-044 SENTRY EXPAND: InvoicePaid Event 4 props readonly canon shape
 *     (sentry W27 só checava 4 props, W28 reforça types exatos pra blindar
 *     downstream NfeBrasil EmitirNFeAoReceberPagamento listener)
 *   - US-RB-044 SENTRY EXPAND: namespace canon Event + Listener (anti-rename)
 *
 * Tier 0 IRREVOGÁVEIS preservados:
 *   - US-RB-044 NFe-de-boleto-pago canônico irrevogável (diferencial vertical gráfica)
 *   - Multi-tenant Tier 0 (ADR 0093) — businessId 1º param em todos métodos
 *   - Tests biz=1 (ADR 0101) — NUNCA biz=4 ROTA LIVRE prod
 *
 * @see Modules/RecurringBilling/CHANGELOG.md Wave 28
 * @see memory/requisitos/RecurringBilling/SPEC.md US-RB-044
 */
describe('Wave 28 RecurringBilling POLISH', function () {

    // ---- D2 AssinaturaService consistency ----

    it('D2: AssinaturaService 4 spans canon rb.assinatura.* (criar/pausar/retomar/cancelar)', function () {
        $src = file_get_contents((new ReflectionClass(AssinaturaService::class))->getFileName());

        foreach (['criar', 'pausar', 'retomar', 'cancelar'] as $action) {
            expect($src)->toContain("OtelHelper::spanBiz('rb.assinatura.{$action}'");
        }
    });

    it('D2: AssinaturaService businessId é 1º param int em todos métodos públicos (ADR 0093 Tier 0)', function () {
        $ref = new ReflectionClass(AssinaturaService::class);

        foreach (['criar', 'pausar', 'retomar', 'cancelar'] as $method) {
            $params = $ref->getMethod($method)->getParameters();
            expect($params[0]->getName())->toBe('businessId');
            expect((string) $params[0]->getType())->toBe('int');
        }
    });

    it('D2: AssinaturaService::calcularProximoVencimento existe como método público (helper canon)', function () {
        $ref = new ReflectionClass(AssinaturaService::class);
        expect($ref->hasMethod('calcularProximoVencimento'))->toBeTrue();

        $method = $ref->getMethod('calcularProximoVencimento');
        expect($method->isPublic())->toBeTrue();
    });

    // ---- US-RB-044 SENTRY EXPAND (W28 reforço irrevogável) ----

    it('US-RB-044 SENTRY EXPAND: InvoicePaid Event 4 props readonly + types exatos (downstream lock-in)', function () {
        expect(class_exists(InvoicePaid::class))->toBeTrue(
            'InvoicePaid Event canônico irrevogável — diferencial vertical gráfica (US-RB-044)'
        );

        $ref = new ReflectionClass(InvoicePaid::class);
        $params = $ref->getConstructor()->getParameters();

        // Sentry: shape exato — qualquer rename quebra listener NfeBrasil
        $expected = [
            ['businessId', 'int'],
            ['invoiceRef', 'string'],
            ['valor', 'float'],
            ['paidAt', 'string'],
        ];

        foreach ($expected as $i => [$name, $type]) {
            expect($params[$i]->getName())->toBe($name);
            expect((string) $params[$i]->getType())->toBe($type);
            expect($ref->getProperty($name)->isReadonly())->toBeTrue(
                "InvoicePaid::\${$name} DEVE ser readonly (immutable contract downstream)"
            );
        }
    });

    it('US-RB-044 SENTRY EXPAND: namespace canon InvoicePaid + EmitirNFeAoReceberPagamento (anti-rename)', function () {
        // Sentry blindando paths exatos — rename quebra Event::listen
        expect(InvoicePaid::class)->toBe('Modules\\RecurringBilling\\Events\\InvoicePaid');
        expect(EmitirNFeAoReceberPagamento::class)
            ->toBe('Modules\\NfeBrasil\\Listeners\\EmitirNFeAoReceberPagamento');
    });

    it('US-RB-044 SENTRY EXPAND: NfeBrasilServiceProvider wire Event::listen InvoicePaid → EmitirNFeAoReceberPagamento', function () {
        $providerFile = (new ReflectionClass(NfeBrasilServiceProvider::class))->getFileName();
        $src = file_get_contents($providerFile);

        // Wire cross-module canonical (W25/W26/W27 sentry — agora W28 expand reforça)
        expect($src)->toContain('InvoicePaid')
            ->and($src)->toContain('EmitirNFeAoReceberPagamento');
    });

    it('US-RB-044 SENTRY EXPAND: InvoicePaid usa Dispatchable + SerializesModels (queue-safe)', function () {
        $traits = class_uses(InvoicePaid::class);

        // Queue-safe pra processar emissão NFe assíncrona via fila
        expect($traits)->toHaveKey(\Illuminate\Foundation\Events\Dispatchable::class);
        expect($traits)->toHaveKey(\Illuminate\Queue\SerializesModels::class);
    });
});
