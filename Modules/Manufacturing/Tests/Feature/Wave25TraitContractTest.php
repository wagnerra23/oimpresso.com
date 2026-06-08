<?php

declare(strict_types=1);

use Modules\Manufacturing\Concerns\AssertsBusinessChain;
use Modules\Manufacturing\Concerns\HasManufacturingProductChain;

uses(Tests\TestCase::class);

/**
 * Wave 25 — D1 trait contract Pest (pure reflection, sem DB) (2026-05-16).
 *
 * Spec puro reflection — NÃO requer schema MySQL e portanto NÃO skipa em SQLite.
 * Lock-in dos 2 traits Manufacturing companion (AssertsBusinessChain Wave 18
 * + HasManufacturingProductChain Wave 25).
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL ({@see ADR 0093}) — preservar pattern chain
 * via JOIN products (tabelas mfg_recipes / mfg_recipe_ingredients NÃO têm
 * coluna business_id direta).
 */

it('trait AssertsBusinessChain existe + métodos canon (Wave 18 baseline)', function () {
    expect(trait_exists(AssertsBusinessChain::class))->toBeTrue();

    $reflection = new ReflectionClass(AssertsBusinessChain::class);
    expect($reflection->hasMethod('scopeForBusinessViaProductChain'))->toBeTrue();
    expect($reflection->hasMethod('belongsToBusinessChain'))->toBeTrue();
});

it('trait HasManufacturingProductChain existe + métodos canon (Wave 25 companion)', function () {
    expect(trait_exists(HasManufacturingProductChain::class))->toBeTrue();

    $reflection = new ReflectionClass(HasManufacturingProductChain::class);
    expect($reflection->hasMethod('countForBusinessChain'))->toBeTrue();
    expect($reflection->hasMethod('idsForBusinessChain'))->toBeTrue();
});

it('traits são complementares (não duplicados em método)', function () {
    $base = (new ReflectionClass(AssertsBusinessChain::class))->getMethods();
    $comp = (new ReflectionClass(HasManufacturingProductChain::class))->getMethods();

    $baseNames = array_map(fn ($m) => $m->getName(), $base);
    $compNames = array_map(fn ($m) => $m->getName(), $comp);

    // Companion NÃO duplica scopeForBusinessViaProductChain (filtro) — só agrega
    $intersect = array_intersect($baseNames, $compNames);
    expect($intersect)->toBeEmpty('Traits não devem ter métodos duplicados — design companion');
});

it('HasManufacturingProductChain documenta lock-in ADR 0093 multi-tenant', function () {
    $file = (new ReflectionClass(HasManufacturingProductChain::class))->getFileName();
    $src = file_get_contents($file);

    expect($src)->toContain('ADR 0093');
    expect($src)->toContain('Multi-tenant Tier 0 IRREVOGÁVEL');
    expect($src)->toContain('mfg_recipes');

    // chain via products.business_id (alias chp ou raw — qualquer evidencia)
    $hasChain = str_contains($src, 'products.business_id')
        || str_contains($src, 'chp.business_id')
        || str_contains($src, "'business_id'");
    expect($hasChain)->toBeTrue('Trait deve referenciar chain products.business_id');
});
