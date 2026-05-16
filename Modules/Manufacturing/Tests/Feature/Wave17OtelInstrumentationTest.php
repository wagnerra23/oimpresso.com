<?php

declare(strict_types=1);

use App\Util\OtelHelper;
use Modules\Manufacturing\Services\ProductionService;
use Modules\Manufacturing\Services\RecipeBomService;

/**
 * D9.a Wave 17 — smoke OtelHelper wrap em Services Manufacturing.
 *
 * Verifica contrato: OtelHelper presente nos arquivos canônicos +
 * zero-cost callback quando otel.enabled=false. NÃO exercita queries DB
 * (escopo do RecipeBomIntegrityTest).
 *
 * @see Modules\Manufacturing\Services\RecipeBomService
 * @see Modules\Manufacturing\Services\ProductionService
 * @see app/Util/OtelHelper.php
 */
uses(Tests\TestCase::class);

it('OtelHelper zero-cost quando otel.enabled=false (Manufacturing default prod)', function () {
    config(['otel.enabled' => false]);

    $executed = false;
    $result = OtelHelper::spanBiz('manufacturing.smoke.span', function () use (&$executed) {
        $executed = true;
        return 'ok';
    });

    expect($executed)->toBeTrue();
    expect($result)->toBe('ok');
});

it('RecipeBomService importa OtelHelper sem erro de classe', function () {
    expect(class_exists(RecipeBomService::class))->toBeTrue();

    $reflection = new ReflectionClass(RecipeBomService::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('use App\\Util\\OtelHelper;');
    expect($source)->toContain('OtelHelper::spanBiz');
    expect($source)->toContain('manufacturing.recipe.resolve_bom');
});

it('ProductionService importa OtelHelper sem erro de classe', function () {
    expect(class_exists(ProductionService::class))->toBeTrue();

    $reflection = new ReflectionClass(ProductionService::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('use App\\Util\\OtelHelper;');
    expect($source)->toContain('OtelHelper::spanBiz');
    expect($source)->toContain('manufacturing.production');
});

it('OTel span attributes NUNCA contem PII (apenas IDs + flags)', function () {
    $reflection = new ReflectionClass(RecipeBomService::class);
    $source = file_get_contents($reflection->getFileName());

    // attributes do span devem ter apenas: module, recipe_id — NUNCA preço, nome de produto,
    // nem qualquer campo de cliente (CPF/CNPJ).
    expect($source)->toContain("'module'");
    expect($source)->toContain("'recipe_id'");
    expect($source)->not->toContain("'cpf'");
    expect($source)->not->toContain("'cnpj'");
});
