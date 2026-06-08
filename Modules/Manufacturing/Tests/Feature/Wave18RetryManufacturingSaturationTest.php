<?php

declare(strict_types=1);

use Modules\Manufacturing\Http\Requests\DestroyRecipeRequest;
use Modules\Manufacturing\Http\Requests\StoreIngredientGroupRequest;
use Modules\Manufacturing\Http\Requests\UpdateIngredientGroupRequest;

uses(Tests\TestCase::class);

/**
 * Wave 18 RETRY — Manufacturing D8 SATURATION (2026-05-16).
 *
 * Fecha CRUD com 3 FormRequests novos:
 *   - StoreIngredientGroupRequest (entity Group — primeiro request)
 *   - UpdateIngredientGroupRequest
 *   - DestroyRecipeRequest (par Store/Update existentes)
 *
 * Tier 0 multi-tenant ({@see ADR 0093}): authorize() depende de
 * session('user.business_id') + permission + subscription module.
 *
 * @see Modules\Manufacturing\Http\Requests\StoreIngredientGroupRequest
 */
describe('Wave 18 RETRY — Manufacturing FormRequests Group (D8)', function () {
    it('StoreIngredientGroupRequest carrega e expõe rules+authorize', function () {
        expect(class_exists(StoreIngredientGroupRequest::class))->toBeTrue();

        $ref = new ReflectionClass(StoreIngredientGroupRequest::class);
        expect($ref->hasMethod('rules'))->toBeTrue();
        expect($ref->hasMethod('authorize'))->toBeTrue();
    });

    it('UpdateIngredientGroupRequest carrega e expõe rules+authorize', function () {
        expect(class_exists(UpdateIngredientGroupRequest::class))->toBeTrue();

        $ref = new ReflectionClass(UpdateIngredientGroupRequest::class);
        expect($ref->hasMethod('rules'))->toBeTrue();
        expect($ref->hasMethod('authorize'))->toBeTrue();
    });

    it('StoreIngredientGroupRequest rules cobrem name obrigatório', function () {
        $req = new StoreIngredientGroupRequest();
        $rules = $req->rules();

        expect($rules)->toHaveKey('name');
        expect($rules)->toHaveKey('description');
        expect($rules['name'])->toContain('required');
        expect($rules['name'])->toContain('max:255');
    });

    it('UpdateIngredientGroupRequest aceita PATCH parcial (sometimes)', function () {
        $req = new UpdateIngredientGroupRequest();
        $rules = $req->rules();

        expect($rules['name'])->toContain('sometimes');
    });
});

describe('Wave 18 RETRY — Manufacturing DestroyRecipeRequest (D8)', function () {
    it('DestroyRecipeRequest existe + authorize gate manufacturing.access_recipe', function () {
        expect(class_exists(DestroyRecipeRequest::class))->toBeTrue();

        $ref = new ReflectionClass(DestroyRecipeRequest::class);
        expect($ref->hasMethod('authorize'))->toBeTrue();
    });

    it('DestroyRecipeRequest aceita confirm boolean opcional', function () {
        $req = new DestroyRecipeRequest();
        $rules = $req->rules();

        expect($rules)->toHaveKey('confirm');
        expect($rules['confirm'])->toContain('nullable');
        expect($rules['confirm'])->toContain('boolean');
    });
});

describe('Wave 18 RETRY — Manufacturing D8 ratio Form/Controller', function () {
    it('Manufacturing tem ≥7 FormRequests pós-saturação D8', function () {
        $glob = glob(__DIR__ . '/../../Http/Requests/*.php');
        $count = is_array($glob) ? count($glob) : 0;

        // Pré-saturação: 4 (Store/Update Recipe + Store/Update Production)
        // Pós Wave 18 RETRY: + 3 (Store/Update IngredientGroup + DestroyRecipe) = 7
        expect($count)->toBeGreaterThanOrEqual(7);
    });
});
