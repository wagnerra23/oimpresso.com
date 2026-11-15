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
 *   - D6 N/A: a vitrine pública (index/show) é Blade, defer não se aplica; a tela admin QR é Inertia
 *
 * Tier 0 IRREVOGÁVEIS:
 *   - Rota pública sem auth — Repository filtra business_id em toda query (defesa profundidade)
 *   - PT-BR comments + tenant 98 contra 99 quando precisar de empresa (ADR 0358)
 *
 * @see memory/requisitos/ProductCatalogue/CHANGELOG.md Wave 27 POLISH
 */
describe('Wave 27 ProductCatalogue POLISH', function () {

    beforeEach(function () {
        config()->set('otel.enabled', false);
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
            ['business_id' => 98]
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


    it('D6 HealthCommand canônico --detail (NUNCA --verbose) — .claude/rules/commands.md', function () {
        $cmd = app(ProductCatalogueHealthCommand::class);
        $signature = (new ReflectionProperty($cmd, 'signature'))->getValue($cmd);

        expect($signature)->toContain('product-catalogue:health')
            ->and($signature)->toContain('--detail')
            ->and($signature)->not->toContain('{--verbose ');
    });

    // Contrato corrigido em 2026-11. A versão anterior exigia que o Controller inteiro não contivesse
    // 'Inertia::render', mas generateQr (tela admin) renderiza Inertia desde o cutover F5 de
    // 2026-10-27: o teste estaria vermelho se alguma lane o rodasse. O contrato real é por rota:
    // a vitrine pública responde Blade; a tela admin responde Inertia (provado em
    // tests/Feature/Sells/CatalogueQrContratoTest.php).
    it('D2 vitrine pública responde Blade, não Inertia (defer N/A pra rota pública QR)', function () {
        if (\Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('Vitrine pública depende do schema MySQL UltimatePOS.');
        }
        $empresa = $this->seededTenant();
        $local = \Illuminate\Support\Facades\DB::table('business_locations')->where('business_id', $empresa->id)->value('id');
        if (! $local) {
            $this->markTestSkipped('Tenant de teste sem local seedado (pest-mysql-setup cria "Matriz CI").');
        }

        $r = $this->get("/catalogue/{$empresa->id}/{$local}");

        $r->assertOk();
        $r->assertViewIs('productcatalogue::catalogue.index');
        $r->assertHeaderMissing('X-Inertia');
        expect((string) $r->getContent())->not->toContain('data-page=');
    });
});
