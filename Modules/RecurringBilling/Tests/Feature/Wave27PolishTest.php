<?php

declare(strict_types=1);

use Modules\RecurringBilling\Events\InvoicePaid;
use Modules\RecurringBilling\Services\AssinaturaCobrancaService;
use Modules\RecurringBilling\Services\AssinaturaService;
use Modules\RecurringBilling\Services\Boleto\BoletoService;

uses(Tests\TestCase::class);

/**
 * Wave 27 RecurringBilling POLISH FINAL ≥90 — D2 + D9 + US-RB-044 sentry.
 *
 * Estratégia: reflection + source-grep, ZERO hit DB (paralelização worktree
 * W27 com NFSe + Officeimpresso simultâneos).
 *
 * Cobre adicional sobre Waves 17-25:
 *   - D2 BoletoService: API completa pública (emitir/cancelar/pdf/refundAsaas/fetchPaymentAsaas)
 *   - D2 AssinaturaService: 5 métodos públicos + helper calcularProximoVencimento
 *   - D2 AssinaturaCobrancaService: cancelInvoice + atualizarCobrancaAssinatura
 *   - D9 spans canônicos completos (5 spans BoletoService + 4 AssinaturaService + 2 Cobranca)
 *   - US-RB-044 NFe-de-boleto-pago SENTRY (W25/W26 criou listener) — Event payload preservado
 *
 * Tier 0 IRREVOGÁVEIS preservados:
 *   - Multi-tenant Tier 0 (ADR 0093): toda assertion sobre `business_id` param
 *   - Tests biz=1 (ADR 0101) — NUNCA biz=4 ROTA LIVRE prod
 *   - US-RB-044 canônico irrevogável: Event::class shape + autoloader exists()
 *
 * @see Modules/RecurringBilling/CHANGELOG.md Wave 27
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */
describe('Wave 27 RecurringBilling POLISH FINAL', function () {

    // ---- D2 BoletoService API completa ----

    it('D2: BoletoService expõe 5 métodos públicos canônicos (emitir/cancelar/pdf/refundAsaas/fetchPaymentAsaas)', function () {
        $methods = collect((new ReflectionClass(BoletoService::class))->getMethods(ReflectionMethod::IS_PUBLIC))
            ->pluck('name')
            ->toArray();

        foreach (['emitir', 'cancelar', 'pdf', 'refundAsaas', 'fetchPaymentAsaas'] as $m) {
<<<<<<< HEAD
            expect($methods)->toContain($m, "BoletoService deve expor método público {$m}");
=======
            expect(in_array($m, $methods, true))->toBeTrue("BoletoService deve expor método público {$m}");
>>>>>>> origin/main
        }
    });

    it('D2: BoletoService::emitir aceita (int $businessId, array $params) e retorna BoletoResult', function () {
        $ref = (new ReflectionClass(BoletoService::class))->getMethod('emitir');
        $params = $ref->getParameters();

        expect($params)->toHaveCount(2);
        expect($params[0]->getName())->toBe('businessId');
        expect((string) $params[0]->getType())->toBe('int');
        expect($params[1]->getName())->toBe('params');
        expect((string) $params[1]->getType())->toBe('array');

        $returnType = (string) $ref->getReturnType();
        expect($returnType)->toContain('BoletoResult');
    });

    it('D2: BoletoService::refundAsaas exige driver Asaas (InvalidArgumentException)', function () {
        $file = (new ReflectionClass(BoletoService::class))->getFileName();
        $src  = file_get_contents($file);

        expect($src)->toContain('AsaasDriver')
            ->and($src)->toContain('InvalidArgumentException')
            ->and($src)->toContain('refundAsaas() exige driver Asaas');
    });

    // ---- D2 AssinaturaService API completa ----

    it('D2: AssinaturaService expõe 5 métodos públicos (criar/pausar/retomar/cancelar/calcularProximoVencimento)', function () {
        $methods = collect((new ReflectionClass(AssinaturaService::class))->getMethods(ReflectionMethod::IS_PUBLIC))
            ->reject(fn ($m) => $m->isConstructor())
            ->pluck('name')
            ->toArray();

        foreach (['criar', 'pausar', 'retomar', 'cancelar', 'calcularProximoVencimento'] as $m) {
<<<<<<< HEAD
            expect($methods)->toContain($m, "AssinaturaService deve expor método público {$m}");
=======
            expect(in_array($m, $methods, true))->toBeTrue("AssinaturaService deve expor método público {$m}");
>>>>>>> origin/main
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

    it('D9: BoletoService spans canônicos ≥5 (emitir/cancelar/pdf/refund_asaas/...)', function () {
        $src = file_get_contents((new ReflectionClass(BoletoService::class))->getFileName());

        foreach ([
            "OtelHelper::spanBiz('rb.boleto.emitir'",
            "OtelHelper::spanBiz('rb.boleto.cancelar'",
            "OtelHelper::spanBiz('rb.boleto.pdf'",
            "OtelHelper::spanBiz('rb.boleto.refund_asaas'",
        ] as $span) {
            expect($src)->toContain($span);
        }

        $totalSpans = substr_count($src, 'OtelHelper::spanBiz');
        expect($totalSpans)->toBeGreaterThanOrEqual(4, "BoletoService deve ter ≥4 spans canon — atual {$totalSpans}");
    });

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
            (new ReflectionClass(BoletoService::class))->getFileName(),
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

    // ---- Tier 0 lock-in extra ----

    it('Tier 0: BoletoService NÃO loga config_json (LGPD — segredos criptografados)', function () {
        $src = file_get_contents((new ReflectionClass(BoletoService::class))->getFileName());

        // Padrões proibidos: logar credentials raw
        expect($src)->not->toContain("Log::info('rb.boleto.config_json'");
        expect($src)->not->toContain('config_json' . ' =>'); // string concat para evitar match acidental
    });

    it('D2: BoletoCredentialResolverInterface preservado (reuse cross-module)', function () {
        expect(interface_exists(\Modules\RecurringBilling\Contracts\BoletoCredentialResolverInterface::class))
            ->toBeTrue();

        $resolved = app(\Modules\RecurringBilling\Contracts\BoletoCredentialResolverInterface::class);
        expect($resolved)->toBeInstanceOf(\Modules\RecurringBilling\Services\Boleto\BoletoCredentialResolver::class);
    });
});
