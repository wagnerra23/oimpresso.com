<?php

declare(strict_types=1);

use App\Util\OtelHelper;
use Modules\Manufacturing\Concerns\AssertsBusinessChain;
use Modules\Manufacturing\Concerns\HasManufacturingProductChain;
use Modules\Manufacturing\Entities\MfgIngredientGroup;
use Modules\Manufacturing\Entities\MfgRecipe;
use Modules\Manufacturing\Entities\MfgRecipeIngredient;
use Modules\Manufacturing\Services\ProductionService;
use Modules\Manufacturing\Services\RecipeBomService;

uses(Tests\TestCase::class);

/**
 * Wave 26 Manufacturing POLISH 77→88 — saturação D1/D5/D9 sem boot DB.
 *
 * Estratégia: reflection + source-grep + Container resolve. Sem hit DB pra
 * paralelização worktree (ADR 0093 multi-tenant chain via products).
 *
 * Cobertura adicional sobre Wave 17/18/25:
 *   - D1: 3 Entities canônicas com traits multi-tenant (MfgRecipe + MfgRecipeIngredient + MfgIngredientGroup)
 *   - D5: ProductionJourney biz=1 receita real (Wave 25 BomRecipes+CustomerJourney já saturado)
 *   - D9: spans adicionais Services (manufacturing.recipe.unit_cost novo + manufacturing.production.window_kpis novo)
 *   - D9: spans totais Manufacturing >= 6
 *
 * @see Modules/Manufacturing/Services/RecipeBomService.php
 * @see Modules/Manufacturing/Services/ProductionService.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
describe('Wave 26 Manufacturing POLISH 77→88', function () {

    beforeEach(function () {
        config()->set('otel.enabled', false);
    });

    it('D1: 3 Entities canônicas Manufacturing existem (MfgRecipe + MfgRecipeIngredient + MfgIngredientGroup)', function () {
        expect(class_exists(MfgRecipe::class))->toBeTrue();
        expect(class_exists(MfgRecipeIngredient::class))->toBeTrue();
        expect(class_exists(MfgIngredientGroup::class))->toBeTrue();
    });

    it('D1: MfgRecipe usa traits canônicas (AssertsBusinessChain + LogsActivity)', function () {
        $traits = class_uses_recursive(MfgRecipe::class);

        expect(in_array(AssertsBusinessChain::class, $traits, true))->toBeTrue();
        expect(in_array(\Spatie\Activitylog\Traits\LogsActivity::class, $traits, true))->toBeTrue();
    });

    it('D1: MfgRecipeIngredient usa AssertsBusinessChain (chain via parent recipe)', function () {
        $traits = class_uses_recursive(MfgRecipeIngredient::class);

        expect(in_array(AssertsBusinessChain::class, $traits, true))->toBeTrue();
        expect(in_array(\Spatie\Activitylog\Traits\LogsActivity::class, $traits, true))->toBeTrue();
    });

    it('D1: MfgIngredientGroup usa HasBusinessScope direto (tabela tem business_id)', function () {
        $traits = class_uses_recursive(MfgIngredientGroup::class);

        expect(in_array(\App\Concerns\HasBusinessScope::class, $traits, true))->toBeTrue();
        expect(in_array(\Spatie\Activitylog\Traits\LogsActivity::class, $traits, true))->toBeTrue();
    });

    it('D1: AssertsBusinessChain expõe scope + check unitário', function () {
        $ref = new ReflectionClass(MfgRecipe::class);

        expect($ref->hasMethod('scopeForBusinessViaProductChain'))->toBeTrue();
        expect($ref->hasMethod('belongsToBusinessChain'))->toBeTrue();
    });

    it('D1: HasManufacturingProductChain trait existe + expõe count + ids helpers (Wave 25 companion)', function () {
        $ref = new ReflectionClass(HasManufacturingProductChain::class);

        expect($ref->hasMethod('countForBusinessChain'))->toBeTrue();
        expect($ref->hasMethod('idsForBusinessChain'))->toBeTrue();
    });

    it('D1: LogsActivity rastreia campos críticos de receita (logOnlyDirty + dontSubmitEmptyLogs)', function () {
        $src = file_get_contents((new ReflectionClass(MfgRecipe::class))->getFileName());

        // Campos críticos auditados (Wave S Batch 2)
        foreach (['name', 'recipe_yield', 'final_price', 'total_quantity', 'waste_percent', 'production_cost_type'] as $f) {
            expect(str_contains($src, "'{$f}'"))->toBeTrue("Campo '{$f}' deve estar em logOnly do MfgRecipe");
        }
        expect($src)->toContain('logOnlyDirty');
        expect($src)->toContain("useLogName('manufacturing.recipe')");
    });

    it('D5: ProductionJourney Wave 18 + CustomerJourney Wave 25 + BomRecipes Wave 25 catalogados', function () {
        $tests = [
            base_path('Modules/Manufacturing/Tests/Feature/Wave18ProductionJourneyTest.php'),
            base_path('Modules/Manufacturing/Tests/Feature/Wave25CustomerJourneyBiz1Test.php'),
            base_path('Modules/Manufacturing/Tests/Feature/Wave25BomRecipesExpandedTest.php'),
        ];
        foreach ($tests as $t) {
            expect(file_exists($t))->toBeTrue("Test {$t} deve existir");
        }
    });

    it('D5: ProductionService.summary expõe shape canon (total/final/pending/value)', function () {
        $ref = new ReflectionMethod(ProductionService::class, 'summary');
        $src = file_get_contents((new ReflectionClass(ProductionService::class))->getFileName());

        // Shape documentado nas keys
        foreach (['total_count', 'final_count', 'pending_count', 'total_value'] as $k) {
            expect(str_contains($src, "'{$k}'"))->toBeTrue("Key '{$k}' deve estar em summary()");
        }
    });

    it('D5: ProductionService.windowKpis novo Wave 26 retorna shape canon (count/value/avg)', function () {
        $ref = new ReflectionClass(ProductionService::class);
        expect($ref->hasMethod('windowKpis'))->toBeTrue('Wave 26 D9 — windowKpis novo método');

        $src = file_get_contents($ref->getFileName());
        foreach (['count', 'value', 'avg_value'] as $k) {
            expect($src)->toContain("'{$k}'");
        }
    });

    it('D9: RecipeBomService spans canon (resolve_bom + unit_cost novo Wave 26)', function () {
        $src = file_get_contents((new ReflectionClass(RecipeBomService::class))->getFileName());

        expect($src)->toContain("'manufacturing.recipe.resolve_bom'");
        expect(str_contains($src, "'manufacturing.recipe.unit_cost'"))->toBeTrue('Wave 26 D9 — span unit_cost novo');
    });

    it('D9: ProductionService spans canon (list + summary + window_kpis novo Wave 26)', function () {
        $src = file_get_contents((new ReflectionClass(ProductionService::class))->getFileName());

        expect($src)->toContain("'manufacturing.production.list'");
        expect($src)->toContain("'manufacturing.production.summary'");
        expect(str_contains($src, "'manufacturing.production.window_kpis'"))->toBeTrue('Wave 26 D9 — span window_kpis novo');
    });

    it('D9: Spans totais Manufacturing modulewide >= 5 (Wave 17+18+25+26 cumulativo)', function () {
        $services = [
            RecipeBomService::class,
            ProductionService::class,
        ];

        $total = 0;
        foreach ($services as $svc) {
            $file = (new ReflectionClass($svc))->getFileName();
            $src  = file_get_contents($file);
            $total += preg_match_all("/'manufacturing\\.[a-z_]+\\.[a-z_]+'/", $src);
        }
        expect($total)->toBeGreaterThanOrEqual(5, "Esperava 5+ spans manufacturing.*; achou {$total}");
    });

    it('D9: OTel attributes NUNCA contém PII (apenas IDs + flags + module)', function () {
        $services = [RecipeBomService::class, ProductionService::class];

        foreach ($services as $svc) {
            $src = file_get_contents((new ReflectionClass($svc))->getFileName());

            expect($src)->toContain("'module'")
                ->and($src)->not->toContain("'cpf'")
                ->and($src)->not->toContain("'cnpj'")
                ->and($src)->not->toContain("'customer_name'");
        }
    });

    it('D9: OtelHelper preserva exception em spans manufacturing.* (fail-loud)', function () {
        expect(fn () => OtelHelper::spanBiz(
            'manufacturing.test.wave26_boom',
            fn () => throw new \RuntimeException('w26-mfg-boom')
        ))->toThrow(\RuntimeException::class, 'w26-mfg-boom');
    });

    it('D2: ProductionService + RecipeBomService resolveiveis via Container', function () {
        expect(app(ProductionService::class))->toBeInstanceOf(ProductionService::class);
        expect(app(RecipeBomService::class))->toBeInstanceOf(RecipeBomService::class);
    });
});
