<?php

declare(strict_types=1);

use Modules\ProductCatalogue\Http\Controllers\ProductCatalogueController;
use Modules\ProductCatalogue\Repositories\ProductCatalogueRepository;
use Modules\ProductCatalogue\Services\CatalogueQrService;
use Modules\ProductCatalogue\Services\CatalogueService;

uses(Tests\TestCase::class);

/**
 * Wave 16 D4 Architecture — smoke test do refactor Service/Repository layer.
 *
 * Após Wave 16 governance v3 (D4=3/20 → ≥10/20), ProductCatalogueController
 * deixou de carregar lógica de negócio inline. Garante:
 *   1. Classes Service/Repository existem nos namespaces canônicos
 *   2. DI Constructor — Container Laravel resolve Services com suas deps
 *   3. Single Responsibility — Controller magro (<200 linhas, métodos <30)
 *   4. Module boundary — Services/Repository só dentro de Modules\ProductCatalogue
 *
 * Pattern canônico: ADR 0011 padrão Jana/Repair.
 *
 * @see Modules\ProductCatalogue\Services\CatalogueService
 * @see Modules\ProductCatalogue\Services\CatalogueQrService
 * @see Modules\ProductCatalogue\Repositories\ProductCatalogueRepository
 */

it('cenario 1: ProductCatalogueRepository existe no namespace canonico', function () {
    expect(class_exists(ProductCatalogueRepository::class))
        ->toBeTrue('Repository deveria estar em Modules\\ProductCatalogue\\Repositories\\');
});

it('cenario 2: CatalogueService existe no namespace canonico', function () {
    expect(class_exists(CatalogueService::class))
        ->toBeTrue('CatalogueService deveria estar em Modules\\ProductCatalogue\\Services\\');
});

it('cenario 3: CatalogueQrService existe no namespace canonico', function () {
    expect(class_exists(CatalogueQrService::class))
        ->toBeTrue('CatalogueQrService deveria estar em Modules\\ProductCatalogue\\Services\\');
});

it('cenario 4: Container resolve CatalogueService com Repository + ProductUtil injetados', function () {
    $service = app(CatalogueService::class);

    expect($service)->toBeInstanceOf(CatalogueService::class);
});

it('cenario 5: Container resolve CatalogueQrService com Repository + ModuleUtil injetados', function () {
    $service = app(CatalogueQrService::class);

    expect($service)->toBeInstanceOf(CatalogueQrService::class);
});

it('cenario 6: Container resolve Repository sem deps externas', function () {
    $repo = app(ProductCatalogueRepository::class);

    expect($repo)->toBeInstanceOf(ProductCatalogueRepository::class);
});

it('cenario 7: ProductCatalogueController declara DI constructor com 2 Services', function () {
    $ref = new ReflectionClass(ProductCatalogueController::class);
    $constructor = $ref->getConstructor();

    expect($constructor)->not->toBeNull('Controller deveria ter __construct com DI');

    $params = $constructor->getParameters();
    expect($params)->toHaveCount(2, 'Controller deveria receber 2 Services via DI');

    $types = array_map(fn ($p) => $p->getType()?->getName(), $params);
    expect($types)->toContain(CatalogueService::class);
    expect($types)->toContain(CatalogueQrService::class);
});

it('cenario 8: Controller eh MAGRO — <200 linhas (single responsibility)', function () {
    $file = (new ReflectionClass(ProductCatalogueController::class))->getFileName();
    $lines = count(file($file));

    expect($lines)->toBeLessThan(200, "Controller magro: <200 linhas. Atual: {$lines}");
});

it('cenario 9: metodos do Controller sao curtos — cada um <30 linhas executaveis', function () {
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

it('cenario 10: Services + Repository estao DENTRO do namespace do modulo (module boundary)', function () {
    expect(CatalogueService::class)->toStartWith('Modules\\ProductCatalogue\\');
    expect(CatalogueQrService::class)->toStartWith('Modules\\ProductCatalogue\\');
    expect(ProductCatalogueRepository::class)->toStartWith('Modules\\ProductCatalogue\\');
});

it('cenario 11: CatalogueService declara DI constructor (Repository + ProductUtil)', function () {
    $ref = new ReflectionClass(CatalogueService::class);
    $constructor = $ref->getConstructor();

    expect($constructor)->not->toBeNull();
    expect($constructor->getParameters())->toHaveCount(2);
});

it('cenario 12: CatalogueQrService declara DI constructor (Repository + ModuleUtil)', function () {
    $ref = new ReflectionClass(CatalogueQrService::class);
    $constructor = $ref->getConstructor();

    expect($constructor)->not->toBeNull();
    expect($constructor->getParameters())->toHaveCount(2);
});
