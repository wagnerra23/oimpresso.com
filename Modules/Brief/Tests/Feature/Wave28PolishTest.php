<?php

declare(strict_types=1);

use Modules\Brief\Services\BriefGeneratorService;

uses(Tests\TestCase::class);

/**
 * Wave 28 Brief POLISH — saturação final ≥95.
 *
 * 2 Pest adicionais (Wave 28 sentry BriefFallback ADR 0091):
 *   1. BriefGeneratorService preserva métodos canônicos (generateNow +
 *      generateFromAggregated) — regression guard se alguém renomear/remover.
 *   2. BriefGeneratorService usa OtelHelper::spanBiz wrap (regression W17 D9
 *      observabilidade — span 'brief.generate_now' obrigatório).
 *
 * Tier 0 IRREVOGÁVEL ({@see ADR 0091}):
 *   - Brief é repo-wide (sem business_id no fallback)
 *   - Brain B OpenAI gpt-4o-mini canônico
 *   - PT-BR + NÃO biz=4 ({@see ADR 0101})
 *   - Mock mode: Pest reflection-only (sem chamada LLM real)
 *
 * @see Modules\Brief\Tests\Feature\BriefFallbackTest (predecessor)
 * @see memory/decisions/0091-daily-brief.md
 */
describe('Wave 28 Brief Polish — saturação final ≥95', function () {

    it('W28 sentry — BriefGeneratorService preserva API canônica generateNow', function () {
        expect(class_exists(BriefGeneratorService::class))->toBeTrue();

        $ref = new ReflectionClass(BriefGeneratorService::class);
        expect($ref->hasMethod('generateNow'))->toBeTrue(
            'generateNow() removido — pipeline brief quebrado'
        );
        expect($ref->getMethod('generateNow')->isPublic())->toBeTrue();

        // generateFromAggregated é o entry-point dry-run (golden tests + fixtures)
        expect($ref->hasMethod('generateFromAggregated'))->toBeTrue(
            'generateFromAggregated() removido — fixtures + golden tests quebrados'
        );
    });

    it('W28 sentry — BriefGeneratorService preserva OtelHelper::spanBiz wrap (regression W17 D9)', function () {
        $source = file_get_contents(__DIR__ . '/../../Services/BriefGeneratorService.php');

        expect($source)->toContain('use App\Util\OtelHelper');
        expect($source)->toContain("OtelHelper::spanBiz('brief.generate_now'");

        // Constantes canônicas Brain B (regression: trocar provider sem ADR é proibido)
        expect($source)->toContain('gpt-4o-mini');
        expect($source)->toContain('---END---'); // sentinela ADR 0091
    });
});
