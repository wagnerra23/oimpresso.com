<?php

declare(strict_types=1);

use Modules\RecurringBilling\Events\InvoicePaid;
use Modules\RecurringBilling\Services\AssinaturaCobrancaService;
use Modules\RecurringBilling\Services\AssinaturaService;

uses(Tests\TestCase::class);

/**
 * Wave 27 RecurringBilling POLISH FINAL ≥90 — D2 + D9 + US-RB-044 sentry.
 *
 * Estratégia: reflection + source-grep, ZERO hit DB (paralelização worktree
 * W27 com NFSe + Officeimpresso simultâneos).
 *
 * Cobre adicional sobre Waves 17-25:
 *   - D2 BoletoService: casos removidos em 2026-11-24 (classe apagada — emissão/cancelamento/estorno saem pelo PaymentGateway, ADR 0170 b1)
 *   - D2 AssinaturaService: 5 métodos públicos + helper calcularProximoVencimento
 *   - D2 AssinaturaCobrancaService: cancelInvoice + atualizarCobrancaAssinatura
 *   - D9 spans canônicos completos (4 AssinaturaService + 2 Cobranca; os 5 spans do BoletoService saíram com a classe em 2026-11-24)
 *   - US-RB-044 NFe-de-boleto-pago SENTRY (W25/W26 criou listener) — Event payload preservado
 *
 * Tier 0 IRREVOGÁVEIS preservados:
 *   - Multi-tenant Tier 0 (ADR 0093): toda assertion sobre `business_id` param
 *   - Tests biz=1 (ADR 0101) — NUNCA biz=4 ROTA LIVRE prod
 *   - US-RB-044 canônico irrevogável: Event::class shape + autoloader exists()
 *
 * @see memory/requisitos/RecurringBilling/CHANGELOG.md Wave 27
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */
describe('Wave 27 RecurringBilling POLISH FINAL', function () {

    it('D2: AssinaturaService expõe 5 métodos públicos (criar/pausar/retomar/cancelar/calcularProximoVencimento)', function () {
        $methods = collect((new ReflectionClass(AssinaturaService::class))->getMethods(ReflectionMethod::IS_PUBLIC))
            ->reject(fn ($m) => $m->isConstructor())
            ->pluck('name')
            ->toArray();

        foreach (['criar', 'pausar', 'retomar', 'cancelar', 'calcularProximoVencimento'] as $m) {
            expect($methods)->toContain($m);
        }
    });

    it('D2: AssinaturaService cross-tenant guard via businessId primeiro arg em todos métodos', function () {
        $ref = new ReflectionClass(AssinaturaService::class);

        foreach (['criar', 'pausar', 'retomar', 'cancelar'] as $methodName) {
            $params = $ref->getMethod($methodName)->getParameters();
            expect($params[0]->getName())->toBe('businessId',
                "AssinaturaService::{$methodName} deve ter \$businessId como primeiro parâmetro (Tier 0 ADR 0093)");
            expect((string) $params[0]->getType())->toBe('int');
        }
    });

    // ---- D2 AssinaturaCobrancaService API completa ----

    it('D2: AssinaturaCobrancaService expõe cancelInvoice + atualizarCobrancaAssinatura', function () {
        $methods = collect((new ReflectionClass(AssinaturaCobrancaService::class))->getMethods(ReflectionMethod::IS_PUBLIC))
            ->reject(fn ($m) => $m->isConstructor())
            ->pluck('name')
            ->toArray();

        expect($methods)->toContain('cancelInvoice')
            ->and($methods)->toContain('atualizarCobrancaAssinatura');
    });

    it('D2: AssinaturaCobrancaService::cancelInvoice retorna array com http_status convencional', function () {
        $file = (new ReflectionClass(AssinaturaCobrancaService::class))->getFileName();
        $src  = file_get_contents($file);

        // Retornos canônicos do contrato (Service não-HTTP, mas devolve dica de HTTP status)
        expect($src)->toContain("'http_status' => 422") // invoice já paga = estorno
            ->and($src)->toContain("'skipped' => 'already_canceled'")
            ->and($src)->toContain('requires_manual_action'); // C6 manual
    });

    // ---- D9 spans completos ----

    it('D9: AssinaturaService spans canônicos 4/4 (criar/pausar/retomar/cancelar)', function () {
        $src = file_get_contents((new ReflectionClass(AssinaturaService::class))->getFileName());

        foreach ([
            "OtelHelper::spanBiz('rb.assinatura.criar'",
            "OtelHelper::spanBiz('rb.assinatura.pausar'",
            "OtelHelper::spanBiz('rb.assinatura.retomar'",
            "OtelHelper::spanBiz('rb.assinatura.cancelar'",
        ] as $span) {
            expect($src)->toContain($span);
        }
    });

    it('D9: AssinaturaCobrancaService spans canônicos (invoice.cancel + subscription.update)', function () {
        $src = file_get_contents((new ReflectionClass(AssinaturaCobrancaService::class))->getFileName());

        expect($src)->toContain("OtelHelper::spanBiz('rb.invoice.cancel'")
            ->and($src)->toContain("OtelHelper::spanBiz('rb.subscription.update'");
    });

    it('D9: spans atributos canon contêm module=RecurringBilling + business_id', function () {
        $files = [
            (new ReflectionClass(AssinaturaService::class))->getFileName(),
            (new ReflectionClass(AssinaturaCobrancaService::class))->getFileName(),
        ];

        foreach ($files as $f) {
            $src = file_get_contents($f);
            expect($src)->toContain("'module'")
                ->and($src)->toContain("'RecurringBilling'")
                ->and($src)->toContain("'business_id'");
        }
    });

    // ---- US-RB-044 NFe-de-boleto-pago SENTRY (canônico irrevogável) ----

    it('US-RB-044 SENTRY: InvoicePaid Event class existe + immutable readonly', function () {
        expect(class_exists(InvoicePaid::class))->toBeTrue(
            'InvoicePaid Event NÃO PODE ser removido — diferencial vertical gráfica (US-RB-044 canônico irrevogável)'
        );

        $ref = new ReflectionClass(InvoicePaid::class);

        // Constructor com 4 props readonly canônicos
        $params = $ref->getConstructor()->getParameters();
        expect($params)->toHaveCount(4);

        $expected = [
            ['businessId', 'int'],
            ['invoiceRef', 'string'],
            ['valor', 'float'],
            ['paidAt', 'string'],
        ];

        foreach ($expected as $i => [$name, $type]) {
            expect($params[$i]->getName())->toBe($name, "Param #{$i} deve ser \${$name}");
            expect((string) $params[$i]->getType())->toBe($type, "Param \${$name} deve ser tipo {$type}");
        }
    });

    it('US-RB-044 SENTRY: InvoicePaid props readonly (imutabilidade contrato downstream)', function () {
        $ref = new ReflectionClass(InvoicePaid::class);

        foreach (['businessId', 'invoiceRef', 'valor', 'paidAt'] as $prop) {
            expect($ref->hasProperty($prop))->toBeTrue();
            expect($ref->getProperty($prop)->isReadonly())->toBeTrue(
                "InvoicePaid::\${$prop} DEVE ser readonly (downstream listeners NfeBrasil dependem)"
            );
        }
    });

    it('US-RB-044 SENTRY: InvoicePaid usa Dispatchable + SerializesModels (queue-safe)', function () {
        $traits = class_uses(InvoicePaid::class);

        expect($traits)->toHaveKey(\Illuminate\Foundation\Events\Dispatchable::class);
        expect($traits)->toHaveKey(\Illuminate\Queue\SerializesModels::class);
    });

    it('US-RB-044 SENTRY: NfeBrasil ouve InvoicePaid via EmitirNFeAoReceberPagamento', function () {
        // Sentry W25/W26: garante que listener cross-module continua wired.
        $listenerClass = \Modules\NfeBrasil\Listeners\EmitirNFeAoReceberPagamento::class;
        expect(class_exists($listenerClass))->toBeTrue(
            'Listener NfeBrasil EmitirNFeAoReceberPagamento NÃO PODE sumir (US-RB-044 fase 2 canônico irrevogável)'
        );

        $providerSrc = file_get_contents(
            (new ReflectionClass(\Modules\NfeBrasil\Providers\NfeBrasilServiceProvider::class))->getFileName()
        );

        // Provider deve registrar Event::listen(InvoicePaid, EmitirNFeAoReceberPagamento)
        expect($providerSrc)->toContain('InvoicePaid')
            ->and($providerSrc)->toContain('EmitirNFeAoReceberPagamento');
    });

    it('US-RB-044 SENTRY: AssinaturaCobrancaService dispara log canon rb.subscription.atualizada', function () {
        // Log estruturado é hook pro listener W26 — não remover.
        $src = file_get_contents((new ReflectionClass(AssinaturaCobrancaService::class))->getFileName());

        expect($src)->toContain("Log::info('rb.subscription.atualizada'");
    });

    it('D2: BoletoCredentialResolverInterface preservado (reuse cross-module)', function () {
        expect(interface_exists(\Modules\RecurringBilling\Contracts\BoletoCredentialResolverInterface::class))
            ->toBeTrue();

        $resolved = app(\Modules\RecurringBilling\Contracts\BoletoCredentialResolverInterface::class);
        expect($resolved)->toBeInstanceOf(\Modules\RecurringBilling\Services\Boleto\BoletoCredentialResolver::class);
    });
});
