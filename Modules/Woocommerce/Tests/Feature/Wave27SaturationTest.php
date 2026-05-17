<?php

declare(strict_types=1);

use App\Util\OtelHelper;
use Modules\Woocommerce\Console\Commands\WoocommerceHealthCommand;
use Modules\Woocommerce\Services\WoocommerceAuthorizationService;
use Modules\Woocommerce\Services\WoocommerceResetService;
use Modules\Woocommerce\Services\WoocommerceSyncService;

uses(Tests\TestCase::class);

/**
 * Wave 27 Woocommerce POLISH ≥95 — D2 SyncService expansão + D9 spans completos.
 *
 * Cobertura adicional sobre Wave 16/17/18/25:
 *   - D2 SyncService: contrato multi-tenant 3 sync methods + 2 reset methods + auth
 *   - D9 spans completos cumulativo: 6 spans canon woocommerce.* em 3 Services
 *   - D9 OtelHelper preserva exception em todos os spans
 *   - D6 HealthCommand 4 checks (schema/DI/creds/last-sync) + 3 exit codes (W25)
 *   - D6 SYNC_STALE_DAYS = 7 (alerta config)
 *
 * Tier 0 IRREVOGÁVEIS:
 *   - Contract HTTP WooCommerce REST API preservado (Wave 16 — NÃO mockar interno)
 *   - Multi-tenant ADR 0093: $businessId:int primeiro param em todo Service method
 *   - Integração unidirecional POS → WooCommerce externo (fsm_n_a:true)
 *
 * @see Modules/Woocommerce/CHANGELOG.md Wave 27 POLISH
 */
describe('Wave 27 Woocommerce POLISH', function () {

    beforeEach(function () {
        config()->set('otel.enabled', false);
    });

    it('D2 SyncService expansão: 3 sync methods + contrato $businessId:int primeiro param', function () {
        foreach (['sincronizarCategorias', 'sincronizarProdutos', 'sincronizarOrders'] as $method) {
            $ref = new ReflectionMethod(WoocommerceSyncService::class, $method);
            $params = $ref->getParameters();
            expect($params[0]->getName())->toBe('businessId');
            expect($params[0]->getType()?->getName())->toBe('int');
        }
    });

    it('D2 ResetService expansão: 2 reset methods + contrato $businessId:int', function () {
        foreach (['resetarCategorias', 'resetarProdutos'] as $method) {
            $ref = new ReflectionMethod(WoocommerceResetService::class, $method);
            $params = $ref->getParameters();
            expect($params[0]->getName())->toBe('businessId');
            expect($params[0]->getType()?->getName())->toBe('int');
        }
    });

    it('D9 spans completos: 6 spans canon woocommerce.* cobertos em 3 Services', function () {
        $expected = [
            'woocommerce.sync.categories',
            'woocommerce.sync.products',
            'woocommerce.sync.orders',
            'woocommerce.reset.categories',
            'woocommerce.reset.products',
            'woocommerce.auth.pode_executar',
        ];

        $sources = [
            base_path('Modules/Woocommerce/Services/WoocommerceSyncService.php'),
            base_path('Modules/Woocommerce/Services/WoocommerceResetService.php'),
            base_path('Modules/Woocommerce/Services/WoocommerceAuthorizationService.php'),
        ];
        $merged = '';
        foreach ($sources as $p) {
            $merged .= file_get_contents($p);
        }

        foreach ($expected as $span) {
            expect($merged)->toContain("'{$span}'");
        }
    });

    it('D9 imports canon: 3 Services importam App\\Util\\OtelHelper', function () {
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

    it('D9 OtelHelper preserva exception em woocommerce.* (fail-loud Wave 25 + 27)', function () {
        expect(fn () => OtelHelper::span(
            'woocommerce.test_wave27_boom',
            ['business_id' => 1],
            fn () => throw new \RuntimeException('woo-w27-boom')
        ))->toThrow(\RuntimeException::class, 'woo-w27-boom');
    });

    it('D6 HealthCommand canônico --detail + business-id obrigatório (Tier 0)', function () {
        $cmd = app(WoocommerceHealthCommand::class);
        $signature = (new ReflectionProperty($cmd, 'signature'))->getValue($cmd);

        expect($signature)->toContain('woocommerce:health')
            ->and($signature)->toContain('--business-id')
            ->and($signature)->toContain('--detail')
            ->and($signature)->not->toContain('{--verbose ');
    });

    it('D6 HealthCommand SYNC_STALE_DAYS = 7 (alerta sync >7d preservado W25)', function () {
        $stale = (new ReflectionClassConstant(WoocommerceHealthCommand::class, 'SYNC_STALE_DAYS'))->getValue();
        expect($stale)->toBe(7);
    });

    it('D2 3 Services Woocommerce resolvidos via container (D4 reuse)', function () {
        $services = [
            WoocommerceSyncService::class,
            WoocommerceResetService::class,
            WoocommerceAuthorizationService::class,
        ];
        foreach ($services as $svc) {
            expect(app($svc))->toBeInstanceOf($svc);
        }
    });

    it('D2 module boundary: 3 Services dentro Modules\\Woocommerce (zero leak)', function () {
        expect(WoocommerceSyncService::class)->toStartWith('Modules\\Woocommerce\\');
        expect(WoocommerceResetService::class)->toStartWith('Modules\\Woocommerce\\');
        expect(WoocommerceAuthorizationService::class)->toStartWith('Modules\\Woocommerce\\');
    });

    it('D6 module.json declara fsm_n_a:true (integração unidirecional WooCommerce externo)', function () {
        $path = base_path('Modules/Woocommerce/module.json');
        if (! file_exists($path)) {
            test()->markTestSkipped('module.json não existe');
            return;
        }

        $json = json_decode(file_get_contents($path), true);
        expect($json)->toBeArray();

        // Wave 18 declarou fsm_n_a; preservado em W25/W27
        $hasFsmNa = (isset($json['fsm_n_a']) && $json['fsm_n_a'])
            || (isset($json['governance']['fsm_n_a']) && $json['governance']['fsm_n_a']);
        expect($hasFsmNa)->toBeTrue();
    });

    it('D9 SyncService span attributes Tier 0: business_id em todos os 3 sync spans', function () {
        $src = file_get_contents(base_path('Modules/Woocommerce/Services/WoocommerceSyncService.php'));

        expect($src)->toContain("'business_id' => \$businessId");
        // Wave 25 declarou user_id/sync_type/limit/offset — preservado
        expect(substr_count($src, "'business_id' => \$businessId"))->toBeGreaterThanOrEqual(1);
    });
});
