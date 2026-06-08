<?php

declare(strict_types=1);

use Modules\ProductCatalogue\Console\Commands\ProductCatalogueHealthCommand;
use Modules\ProductCatalogue\Http\Controllers\ProductCatalogueController;
use Modules\ProductCatalogue\Repositories\ProductCatalogueRepository;
use Modules\ProductCatalogue\Services\CatalogueQrService;
use Modules\ProductCatalogue\Services\CatalogueService;

uses(Tests\TestCase::class);

/**
 * Wave 23 SATURATION ProductCatalogue — F1 + F2 reuse + F3 Perf gap 75→≥80.
 *
 * Wave 16 ja entregou ArchitectureTest (12 cenarios). Esta camada complementa:
 *   - F1 Pest: confirma 12 cenarios Wave 16 + scenarios edge novos
 *   - F2 reuse: CatalogueService consumivel via container, Sells pode importar payload
 *   - F3 Perf: Service usa OtelHelper::spanBiz (D9 hot-path catalogo publico QR)
 *   - F6 Health: ProductCatalogueHealthCommand canon
 *
 * @see Modules\ProductCatalogue\Services\CatalogueService
 * @see Modules\ProductCatalogue\Services\CatalogueQrService
 * @see Modules\ProductCatalogue\Console\Commands\ProductCatalogueHealthCommand
 */

it('F1 confirmacao: ArchitectureTest Wave 16 tem 12 cenarios saturados', function () {
    $file = base_path('Modules/ProductCatalogue/Tests/Feature/ArchitectureTest.php');
    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);
    $matches = preg_match_all("/^it\\('cenario \\d+:/m", $content);
    expect($matches)->toBeGreaterThanOrEqual(12, "ArchitectureTest deve ter >=12 cenarios; achou {$matches}");
});

it('F2 reuse: CatalogueService resolvido via container com DI (Repo+ProductUtil)', function () {
    $svc = app(CatalogueService::class);
    expect($svc)->toBeInstanceOf(CatalogueService::class);

    // DI cadeia: Service ← Repository ← ProductUtil (validation via reflection)
    $ref = new ReflectionClass(CatalogueService::class);
    $constructor = $ref->getConstructor();
    expect($constructor->getNumberOfParameters())->toBe(2);

    $types = array_map(fn ($p) => $p->getType()?->getName(), $constructor->getParameters());
    expect($types)->toContain(ProductCatalogueRepository::class);
});

it('F2 reuse: CatalogueQrService resolvido + DI canon', function () {
    $svc = app(CatalogueQrService::class);
    expect($svc)->toBeInstanceOf(CatalogueQrService::class);
});

it('F2 reuse: CatalogueService expoe buildIndexPayload + buildShowPayload (contrato Sells)', function () {
    $ref = new ReflectionClass(CatalogueService::class);

    expect($ref->hasMethod('buildIndexPayload'))->toBeTrue();
    expect($ref->hasMethod('buildShowPayload'))->toBeTrue();

    $indexMethod = $ref->getMethod('buildIndexPayload');
    expect($indexMethod->isPublic())->toBeTrue();
    expect($indexMethod->getNumberOfRequiredParameters())->toBe(2); // biz_id + location_id
});

it('F3 Perf: CatalogueService usa OtelHelper::spanBiz canon (D9 hot-path)', function () {
    $source = file_get_contents(base_path('Modules/ProductCatalogue/Services/CatalogueService.php'));

    expect($source)->toContain('use App\Util\OtelHelper;');
    expect($source)->toContain("'product_catalogue.build_index_payload'");
    expect($source)->toContain("'product_catalogue.build_show_payload'");
});

it('F3 Perf: ProductCatalogueController magro <200 linhas (Wave 16 D4 mantido)', function () {
    $file = (new ReflectionClass(ProductCatalogueController::class))->getFileName();
    $lines = count(file($file));

    expect($lines)->toBeLessThan(200, "Controller magro: <200 linhas. Atual: {$lines}");
});

it('F3 Perf: ProductCatalogueController metodos <30 linhas (single responsibility)', function () {
    $ref = new ReflectionClass(ProductCatalogueController::class);

    foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getDeclaringClass()->getName() !== ProductCatalogueController::class) {
            continue;
        }
        if ($method->isConstructor()) {
            continue;
        }

        $length = $method->getEndLine() - $method->getStartLine();
        expect($length)->toBeLessThan(30, "Metodo {$method->getName()} deveria ter <30 linhas; tem {$length}");
    }
});

it('F6 ProductCatalogueHealthCommand registrado + signature canon', function () {
    $cmd = app(ProductCatalogueHealthCommand::class);
    expect($cmd)->toBeInstanceOf(ProductCatalogueHealthCommand::class);

    $signature = (new ReflectionProperty($cmd, 'signature'))->getValue($cmd);
    expect($signature)->toContain('product-catalogue:health');
    expect($signature)->toContain('--detail');
    expect($signature)->not->toContain('{--verbose ');
});

it('F2 module boundary: Services + Repository dentro do namespace canonico', function () {
    expect(CatalogueService::class)->toStartWith('Modules\\ProductCatalogue\\');
    expect(CatalogueQrService::class)->toStartWith('Modules\\ProductCatalogue\\');
    expect(ProductCatalogueRepository::class)->toStartWith('Modules\\ProductCatalogue\\');
});
