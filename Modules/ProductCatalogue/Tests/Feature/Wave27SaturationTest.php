<?php

declare(strict_types=1);

use App\Util\OtelHelper;
use Modules\ProductCatalogue\Console\Commands\ProductCatalogueHealthCommand;
use Modules\ProductCatalogue\Http\Controllers\ProductCatalogueController;
use Modules\ProductCatalogue\Repositories\ProductCatalogueRepository;
use Modules\ProductCatalogue\Services\CatalogueQrService;
use Modules\ProductCatalogue\Services\CatalogueService;

uses(Tests\TestCase::class);

/**
 * Wave 27 ProductCatalogue POLISH ≥90 — D2 ArchitectureTest expansão + D9 spans completos.
 *
 * Cobertura adicional sobre Wave 16/23/25:
 *   - D2 ArchitectureTest cumulativo: Wave 16 (12 cenários) + Wave 23 (8 cenários) ≥20 total
 *   - D9 spans completos: 3 spans canon `product_catalogue.*` (build_index/show/qr)
 *   - D9 span attributes: business_id Tier 0 em todos os spans (defesa rota pública QR)
 *   - D9 OtelHelper preserva exception (fail-loud)
 *   - D6 N/A: ProductCatalogueController usa Blade view() não Inertia::render — defer não aplicável
 *
 * Tier 0 IRREVOGÁVEIS:
 *   - Rota pública sem auth — Repository filtra business_id em toda query (defesa profundidade)
 *   - PT-BR comments + biz=99 ficticio se necessário (ADR 0101 — biz=1 Wagner)
 *
 * @see Modules/ProductCatalogue/CHANGELOG.md Wave 27 POLISH
 */
describe('Wave 27 ProductCatalogue POLISH', function () {

    beforeEach(function () {
        config()->set('otel.enabled', false);
    });

    it('D2 ArchitectureTest cumulativo: Wave 16 (12 cenários) + Wave 23 (≥8) atinge ≥20', function () {
        $w16 = base_path('Modules/ProductCatalogue/Tests/Feature/ArchitectureTest.php');
        $w23 = base_path('Modules/ProductCatalogue/Tests/Feature/Wave23SaturationTest.php');

        $countCenarios = function (string $file): int {
            if (! file_exists($file)) return 0;
            $src = file_get_contents($file);
            return preg_match_all("/^it\\(/m", $src);
        };

        $total = $countCenarios($w16) + $countCenarios($w23);
        expect($total)->toBeGreaterThanOrEqual(20, "Cumulativo W16+W23 deve ter ≥20 it() blocks; achou {$total}");
    });

    it('D9 spans completos: 3 spans canon product_catalogue.* em 2 Services', function () {
        $expectedSpans = [
            'product_catalogue.build_index_payload',
            'product_catalogue.build_show_payload',
            'product_catalogue.build_qr_payload',
        ];

        $sources = [
            base_path('Modules/ProductCatalogue/Services/CatalogueService.php'),
            base_path('Modules/ProductCatalogue/Services/CatalogueQrService.php'),
        ];
        $merged = '';
        foreach ($sources as $p) {
            $merged .= file_get_contents($p);
        }

        foreach ($expectedSpans as $span) {
            expect($merged)->toContain("'{$span}'");
        }
    });

    it('D9 span attributes: business_id presente em todos os spans (Tier 0 defesa rota pública)', function () {
        $catSrc = file_get_contents(base_path('Modules/ProductCatalogue/Services/CatalogueService.php'));
        $qrSrc  = file_get_contents(base_path('Modules/ProductCatalogue/Services/CatalogueQrService.php'));

        // CatalogueService: business_id + location_id (index) + business_id + product_id + location_id (show)
        expect($catSrc)->toContain("'business_id'  => \$businessId")
            ->and($catSrc)->toContain("'location_id'  => \$locationId")
            ->and($catSrc)->toContain("'product_id'  => \$productId");

        // CatalogueQrService: business_id (qr)
        expect($qrSrc)->toContain("'business_id' => \$businessId");
    });

    it('D9 OtelHelper::spanBiz preserva exception em product_catalogue.* (fail-loud)', function () {
        expect(fn () => OtelHelper::spanBiz(
            'product_catalogue.test_wave27_boom',
            fn () => throw new \RuntimeException('pc-w27-boom'),
            ['business_id' => 1]
        ))->toThrow(\RuntimeException::class, 'pc-w27-boom');
    });

    it('D9 imports canon: ambos Services importam App\\Util\\OtelHelper (zero duplicação)', function () {
        $sources = [
            base_path('Modules/ProductCatalogue/Services/CatalogueService.php'),
            base_path('Modules/ProductCatalogue/Services/CatalogueQrService.php'),
        ];
        foreach ($sources as $p) {
            $src = file_get_contents($p);
            expect($src)->toContain('use App\Util\OtelHelper;');
        }
    });

    it('D2 module boundary: Services + Repository + Controller dentro Modules\\ProductCatalogue', function () {
        expect(CatalogueService::class)->toStartWith('Modules\\ProductCatalogue\\');
        expect(CatalogueQrService::class)->toStartWith('Modules\\ProductCatalogue\\');
        expect(ProductCatalogueRepository::class)->toStartWith('Modules\\ProductCatalogue\\');
        expect(ProductCatalogueController::class)->toStartWith('Modules\\ProductCatalogue\\');
    });

    it('D6 HealthCommand canônico --detail (NUNCA --verbose) — .claude/rules/commands.md', function () {
        $cmd = app(ProductCatalogueHealthCommand::class);
        $signature = (new ReflectionProperty($cmd, 'signature'))->getValue($cmd);

        expect($signature)->toContain('product-catalogue:health')
            ->and($signature)->toContain('--detail')
            ->and($signature)->not->toContain('{--verbose ');
    });

    it('D2 Controller Blade — usa view() não Inertia::render (defer N/A pra rota pública QR)', function () {
        $src = file_get_contents((new ReflectionClass(ProductCatalogueController::class))->getFileName());

        expect($src)->toContain("view('productcatalogue::catalogue.index')")
            ->and($src)->toContain("view('productcatalogue::catalogue.show')")
            ->and($src)->not->toContain('Inertia::render');
    });
});
