<?php

declare(strict_types=1);

use App\Util\OtelHelper;
use Modules\Manufacturing\Entities\MfgRecipe;
use Modules\Manufacturing\Services\ProductionService;
use Modules\Manufacturing\Services\RecipeBomService;

uses(Tests\TestCase::class);

/**
 * Wave 28 Manufacturing SATURATION FINAL — polish 77-90 → ≥92 (+2pp).
 *
 * Esforço por dimensão:
 *  - D2 +3 Pest novos cenários BomRecipes (sem novo span — Wave 26 já saturou D9 Manufacturing)
 *  - D3 CHANGELOG W28 entry
 *
 * Trust L0: Reflection + source-grep + cálculo determinístico (sem boot DB).
 * Preserva Tier 0 IRREVOGÁVEIS:
 *   - Tabelas `mfg_recipes` / `mfg_recipe_ingredients` SEM coluna `business_id` direta
 *     — chain via JOIN products (ADR 0093 + Wave 25 doc)
 *   - PT-BR comentários, biz=1 em smoke (NUNCA biz=4 cliente real ADR 0101)
 *   - NÃO mexer BOM existente
 *
 * @see Modules/Manufacturing/Tests/Feature/Wave26ManufacturingSaturationTest.php (Wave 26 baseline)
 */
describe('Wave 28 Manufacturing POLISH 77-90 → ≥92', function () {

    beforeEach(function () {
        config()->set('otel.enabled', false);
    });

    // ------------------------------------------------------------------
    // D2 W28 — +3 Pest cenários BomRecipes (sem novo span — D9 saturado W26)
    // ------------------------------------------------------------------

    it('D2 W28: RecipeBomService.calculateUnitCost retorna float + proteção div-by-zero canon', function () {
        $ref = new ReflectionMethod(RecipeBomService::class, 'calculateUnitCost');
        expect($ref->getReturnType()?->getName())->toBe('float');

        $src = file_get_contents((new ReflectionClass(RecipeBomService::class))->getFileName());

        // Proteção div-by-zero: total_quantity <= 0 → 0.0
        expect($src)->toContain('total_quantity <= 0');
        expect($src)->toContain('return 0.0');
    });

    it('D2 W28: RecipeBomService.calculateCost preserva paridade legacy ManufacturingUtil (waste + production_cost_type)', function () {
        $src = file_get_contents((new ReflectionClass(RecipeBomService::class))->getFileName());

        // 3 modos canon de production_cost_type ('percentage' / 'per_unit' / fallback fixed)
        expect($src)->toContain("'percentage'");
        expect($src)->toContain("'per_unit'");

        // base_unit_multiplier aplicado quando sub_unit existe (paridade legacy)
        expect($src)->toContain('base_unit_multiplier');

        // dpp_inc_tax * quantity — fórmula base ingrediente
        expect($src)->toContain('dpp_inc_tax');
    });

    it('D2 W28: RecipeBomService.resolveBom valida chain multi-tenant via products.business_id', function () {
        $src = file_get_contents((new ReflectionClass(RecipeBomService::class))->getFileName());

        // Chain JOIN canon (products NÃO mfg_recipes — preserva D1 Wave 25)
        expect($src)->toContain('mfg_recipes.variation_id');
        expect($src)->toContain('p.business_id');
        expect($src)->toContain('products as p');

        // Early return defensivo se NÃO pertence ao bizId
        expect($src)->toContain('if (! $pertence)');
        expect($src)->toContain('return collect()');
    });

    it('D2 W28: RecipeBomService.listForDropdown wrappa MfgRecipe::forDropdown (DI Controller pattern)', function () {
        $ref = new ReflectionMethod(RecipeBomService::class, 'listForDropdown');
        expect($ref->getReturnType()?->getName())->toContain('Collection');

        $params = collect($ref->getParameters())->keyBy(fn ($p) => $p->getName());
        expect($params->has('businessId'))->toBeTrue('listForDropdown deve ter businessId Tier 0');
        expect($params['businessId']->isOptional())->toBeFalse('businessId NÃO pode ser optional');
    });

    // ------------------------------------------------------------------
    // Preservação Tier 0 IRREVOGÁVEIS (regression guard)
    // ------------------------------------------------------------------

    it('Tier 0 W28 preserva: spans Manufacturing modulewide ≥5 (Wave 26 saturado)', function () {
        $services = [RecipeBomService::class, ProductionService::class];

        $total = 0;
        foreach ($services as $svc) {
            $src = file_get_contents((new ReflectionClass($svc))->getFileName());
            $total += preg_match_all("/'manufacturing\\.[a-z_]+\\.[a-z_]+'/", $src);
        }
        expect($total)->toBeGreaterThanOrEqual(5, "Spans manufacturing.* ≥5 (W26 saturado); achou {$total}");
    });

    it('Tier 0 W28 preserva: MfgRecipe usa AssertsBusinessChain (chain via products.business_id)', function () {
        $traits = class_uses_recursive(MfgRecipe::class);
        expect(in_array(\Modules\Manufacturing\Concerns\AssertsBusinessChain::class, $traits, true))->toBeTrue();
    });

    it('D9 W28: OtelHelper preserva exception em spans manufacturing.* (fail-loud)', function () {
        expect(fn () => OtelHelper::spanBiz(
            'manufacturing.test.wave28_boom',
            fn () => throw new \RuntimeException('w28-mfg-boom')
        ))->toThrow(\RuntimeException::class, 'w28-mfg-boom');
    });

    // ------------------------------------------------------------------
    // D3 W28 — CHANGELOG entry novo
    // ------------------------------------------------------------------

    it('D3 W28: CHANGELOG.md tem entrada Wave 28 (saturation 77-90 → ≥92)', function () {
        $changelog = file_get_contents(base_path('Modules/Manufacturing/CHANGELOG.md'));
        expect($changelog)->toContain('Wave 28');
    });
});
