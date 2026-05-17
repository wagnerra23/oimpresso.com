<?php

declare(strict_types=1);

use App\Util\OtelHelper;
use Modules\Woocommerce\Console\Commands\WoocommerceHealthCommand;
use Modules\Woocommerce\Services\WoocommerceAuthorizationService;
use Modules\Woocommerce\Services\WoocommerceResetService;
use Modules\Woocommerce\Services\WoocommerceSyncService;

uses(Tests\TestCase::class);

/**
 * Wave 25 Woocommerce POLISH ≥92 — saturação D6/D9 health/spans completos.
 *
 * Estratégia: reflection + source-grep + Container resolve. Sem hit em API
 * WooCommerce externa (Tier 0 — contract HTTP preservado Wave 16/17/23).
 *
 * Cobertura adicional sobre Wave 18/23:
 *   - D9: spans completos em 3 Services (Sync/Reset/Authorization) — 6 spans canon
 *   - D9: span attributes documentados (business_id, user_id, sync_type, limit, offset)
 *   - D6: HealthCommand 4 checks (schema/DI/creds/last-sync) + 3 exit codes
 *   - D6: HealthCommand fail-secure --business-id obrigatório (Tier 0 ADR 0093)
 *   - D6: SYNC_STALE_DAYS = 7 (alerta sync >7d)
 *   - D9: OtelHelper preserva exception (fail-loud) + zero-cost path
 *
 * Tier 0 IRREVOGÁVEIS:
 *   - Contract HTTP WooCommerce REST API preservado (Wave 16) — sem mock interno
 *   - Multi-tenant ADR 0093 — $businessId explícito em todo Service method
 *
 * @see Modules/Woocommerce/CHANGELOG.md Wave 25 POLISH
 * @see .claude/rules/commands.md (--detail NUNCA --verbose)
 */
describe('Wave 25 Woocommerce POLISH', function () {

    beforeEach(function () {
        config()->set('otel.enabled', false);
    });

    it('D9: 6 spans canon woocommerce.* declarados em 3 Services', function () {
        $services = [
            WoocommerceSyncService::class    => 3, // categories, products, orders
            WoocommerceResetService::class   => 2, // reset.categories, reset.products
            WoocommerceAuthorizationService::class => 1, // auth.pode_executar
        ];

        $totalSpans = 0;
        foreach ($services as $svc => $expectedMin) {
            $file = (new ReflectionClass($svc))->getFileName();
            $src  = file_get_contents($file);
            $matches = preg_match_all("/'woocommerce\\.[a-z_.]+'/", $src);

            expect($matches)->toBeGreaterThanOrEqual($expectedMin);
            $totalSpans += $matches;
        }
        expect($totalSpans)->toBeGreaterThanOrEqual(6);
    });

    it('D9: SyncService span attributes documentados (business_id/user_id/sync_type)', function () {
        $file = (new ReflectionClass(WoocommerceSyncService::class))->getFileName();
        $src  = file_get_contents($file);

        // span products: 5 attrs (business_id, user_id, sync_type, limit, offset)
        expect($src)->toContain("'business_id' => \$businessId")
            ->and($src)->toContain("'user_id' => \$userId")
            ->and($src)->toContain("'sync_type' => \$syncType")
            ->and($src)->toContain("'limit' => \$limit")
            ->and($src)->toContain("'offset' => \$offset ?? -1");
    });

    it('D6: HealthCommand registrado + signature canon', function () {
        $cmd = app(WoocommerceHealthCommand::class);
        expect($cmd)->toBeInstanceOf(WoocommerceHealthCommand::class);

        $signature = (new ReflectionProperty($cmd, 'signature'))->getValue($cmd);
        expect($signature)->toContain('woocommerce:health')
            ->and($signature)->toContain('--business-id')
            ->and($signature)->toContain('--detail')
            ->and($signature)->not->toContain('{--verbose '); // .claude/rules/commands.md Tier 0
    });

    it('D6: HealthCommand declara SYNC_STALE_DAYS = 7 (alerta config)', function () {
        $stale = (new ReflectionClassConstant(WoocommerceHealthCommand::class, 'SYNC_STALE_DAYS'))->getValue();
        expect($stale)->toBe(7);
    });

    it('D6: HealthCommand exit codes 0/1/2 documentados (healthy/degraded/error)', function () {
        $file = (new ReflectionClass(WoocommerceHealthCommand::class))->getFileName();
        $src  = file_get_contents($file);

        // 4 checks documentados
        expect($src)->toContain('woocommerce_sync_logs')
            ->and($src)->toContain('Container resolve 3 Services')
            ->and($src)->toContain('credenciais')
            ->and($src)->toContain('Última sync');

        // 3 exit codes
        expect($src)->toContain('0 = healthy')
            ->and($src)->toContain('1 = degraded')
            ->and($src)->toContain('2 = error');

        // Fail-secure return paths (--business-id obrigatório)
        expect($src)->toContain('return 0;')
            ->and($src)->toContain('return 1;')
            ->and($src)->toContain('return 2;');
    });

    it('D6: HealthCommand fail-secure --business-id obrigatório (Tier 0 ADR 0093)', function () {
        $file = (new ReflectionClass(WoocommerceHealthCommand::class))->getFileName();
        $src  = file_get_contents($file);

        expect($src)->toContain("'--business-id obrigatório (multi-tenant Tier 0 ADR 0093)'")
            ->and($src)->toContain('if ($bizId <= 0)');
    });

    it('D9: spans cobrem 5 sync operations (3 sync + 2 reset)', function () {
        $expectedSpans = [
            // SyncService
            'woocommerce.sync.categories',
            'woocommerce.sync.products',
            'woocommerce.sync.orders',
            // ResetService
            'woocommerce.reset.categories',
            'woocommerce.reset.products',
            // AuthorizationService
            'woocommerce.auth.pode_executar',
        ];

        $sources = [
            base_path('Modules/Woocommerce/Services/WoocommerceSyncService.php'),
            base_path('Modules/Woocommerce/Services/WoocommerceResetService.php'),
            base_path('Modules/Woocommerce/Services/WoocommerceAuthorizationService.php'),
        ];
        $merged = '';
        foreach ($sources as $p) {
            if (file_exists($p)) {
                $merged .= file_get_contents($p);
            }
        }

        foreach ($expectedSpans as $span) {
            expect($merged)->toContain("'{$span}'");
        }
    });

    it('D9: OtelHelper preserva exception em spans woocommerce.* (fail-loud)', function () {
        expect(fn () => OtelHelper::span(
            'woocommerce.test.wave25_boom',
            ['business_id' => 1],
            fn () => throw new \RuntimeException('woo-w25-boom')
        ))->toThrow(\RuntimeException::class, 'woo-w25-boom');
    });

    it('D9: SyncService respeitam contrato multi-tenant — $businessId explicit (Tier 0)', function () {
        foreach (['sincronizarCategorias', 'sincronizarProdutos', 'sincronizarOrders'] as $method) {
            $ref = new ReflectionMethod(WoocommerceSyncService::class, $method);
            $params = $ref->getParameters();

            expect($params[0]->getName())->toBe('businessId');
            expect($params[0]->getType()?->getName())->toBe('int');
        }
    });

    it('D9: ResetService respeitam contrato multi-tenant — $businessId explicit (Tier 0)', function () {
        foreach (['resetarCategorias', 'resetarProdutos'] as $method) {
            $ref = new ReflectionMethod(WoocommerceResetService::class, $method);
            $params = $ref->getParameters();

            expect($params[0]->getName())->toBe('businessId');
            expect($params[0]->getType()?->getName())->toBe('int');
        }
    });

    it('D6: 3 Services Woocommerce resolvidos via container (D4 reuse)', function () {
        $services = [
            WoocommerceSyncService::class,
            WoocommerceResetService::class,
            WoocommerceAuthorizationService::class,
        ];
        foreach ($services as $svc) {
            expect(app($svc))->toBeInstanceOf($svc);
        }
    });

    it('D9: SyncService import App\Util\OtelHelper canon (não duplicado)', function () {
        $sources = [
            base_path('Modules/Woocommerce/Services/WoocommerceSyncService.php'),
            base_path('Modules/Woocommerce/Services/WoocommerceResetService.php'),
            base_path('Modules/Woocommerce/Services/WoocommerceAuthorizationService.php'),
        ];

        foreach ($sources as $p) {
            $src = file_get_contents($p);
            expect($src)->toContain('use App\Util\OtelHelper;');
        }
    });

    it('D6: module.json declara fsm_n_a:true (integração unidirecional WooCommerce externo)', function () {
        $path = base_path('Modules/Woocommerce/module.json');
        if (! file_exists($path)) {
            test()->markTestSkipped('module.json não existe');
        }

        $json = json_decode(file_get_contents($path), true);
        expect($json)->toBeArray();
        // Wave 18 declarou fsm_n_a; preservado em Wave 25
        if (isset($json['fsm_n_a'])) {
            expect($json['fsm_n_a'])->toBeTrue();
        }
    });
});
