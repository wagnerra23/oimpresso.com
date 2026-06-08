<?php

declare(strict_types=1);

use Modules\Manufacturing\Http\Requests\StoreProductionRequest;
use Modules\Manufacturing\Http\Requests\StoreRecipeRequest;
use Modules\Manufacturing\Http\Requests\UpdateProductionRequest;
use Modules\Manufacturing\Http\Requests\UpdateRecipeRequest;

uses(Tests\TestCase::class);

/**
 * Wave 18 — D8 SATURATION: FormRequests novos (Update*) + smoke contrato.
 *
 * Pest validador de contrato (sem booting full HTTP) — garante que classes
 * carregam, declaram rules() e authorize(), e que rules cobrem campos
 * canônicos do CRUD Manufacturing.
 *
 * Multi-tenant Tier 0 ({@see ADR 0093}): authorize() depende de session
 * `user.business_id` + permission Spatie + subscription ModuleUtil.
 *
 * @see Modules\Manufacturing\Http\Requests\UpdateProductionRequest
 * @see Modules\Manufacturing\Http\Requests\UpdateRecipeRequest
 */
describe('Wave 18 — Manufacturing FormRequests contract', function () {
    it('UpdateRecipeRequest carrega e expõe rules+authorize', function () {
        expect(class_exists(UpdateRecipeRequest::class))->toBeTrue();

        $reflection = new ReflectionClass(UpdateRecipeRequest::class);
        expect($reflection->hasMethod('rules'))->toBeTrue();
        expect($reflection->hasMethod('authorize'))->toBeTrue();
    });

    it('UpdateProductionRequest carrega e expõe rules+authorize', function () {
        expect(class_exists(UpdateProductionRequest::class))->toBeTrue();

        $reflection = new ReflectionClass(UpdateProductionRequest::class);
        expect($reflection->hasMethod('rules'))->toBeTrue();
        expect($reflection->hasMethod('authorize'))->toBeTrue();
    });

    it('UpdateRecipeRequest rules incluem variation_id + ingredients + production_cost_type', function () {
        $req = new UpdateRecipeRequest();
        $rules = $req->rules();

        expect($rules)->toHaveKey('variation_id');
        expect($rules)->toHaveKey('ingredients');
        expect($rules)->toHaveKey('production_cost_type');
        expect($rules['production_cost_type'])->toContain('in:fixed,percentage,per_unit');
    });

    it('UpdateProductionRequest rules incluem ref_no + lot_number + exp_date', function () {
        $req = new UpdateProductionRequest();
        $rules = $req->rules();

        expect($rules)->toHaveKey('ref_no');
        expect($rules)->toHaveKey('lot_number');
        expect($rules)->toHaveKey('exp_date');
    });

    it('Store* requests existem e formam par com Update*', function () {
        // Wave 18 — D8 ratio Form/Controller passa de 2 (Store only) pra 4 (Store + Update)
        expect(class_exists(StoreProductionRequest::class))->toBeTrue();
        expect(class_exists(StoreRecipeRequest::class))->toBeTrue();
        expect(class_exists(UpdateProductionRequest::class))->toBeTrue();
        expect(class_exists(UpdateRecipeRequest::class))->toBeTrue();
    });

    it('UpdateProductionRequest valida production_cost_type allow-list (security hardening D8)', function () {
        $req = new UpdateProductionRequest();
        $rules = $req->rules();

        // Rule allow-list previne injection de valor arbitrário em coluna enum-like
        expect($rules['mfg_production_cost_type'])->toContain('in:fixed,percentage,per_unit');
    });
});
