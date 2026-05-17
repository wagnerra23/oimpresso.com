<?php

declare(strict_types=1);

use Modules\Repair\Entities\JobSheet;

uses(Tests\TestCase::class);

/**
 * Wave 28 Repair POLISH — saturação final ≥95.
 *
 * 2 Pest adicionais (Wave 28 sentry FSM canon ADR 0143):
 *   1. JobSheet usa trait GuardsFsmTransitions (regression guard pós ADR 0143
 *      LIVE prod biz=1 — se alguém remover, UPDATE direto em current_stage_id
 *      volta a ser possível e quebra audit trail).
 *   2. CancelJobSheetRequest (W25 D8) preserva validação multi-tenant +
 *      motivo obrigatório (regression guard LGPD audit).
 *
 * Tier 0 IRREVOGÁVEL ({@see ADR 0143}):
 *   - FSM canon: SEMPRE via ExecuteStageActionService, NUNCA UPDATE direto
 *   - Multi-tenant {@see ADR 0093} — biz=99 sentinel
 *   - PT-BR + NÃO biz=4 ({@see ADR 0101})
 *
 * @see Modules\Repair\Tests\Feature\Wave25RepairFsmCanonExpandedTest (predecessor)
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 */
describe('Wave 28 Repair Polish — saturação final ≥95', function () {

    it('W28 sentry — JobSheet preserva trait GuardsFsmTransitions (regression FSM canon W25)', function () {
        $traits = class_uses_recursive(JobSheet::class);

        // Detecta via fully-qualified OR short name (defesa em profundidade)
        $hasGuard = collect($traits)->contains(
            fn ($t) => str_ends_with($t, 'GuardsFsmTransitions')
        );

        expect($hasGuard)->toBeTrue(
            'JobSheet sem GuardsFsmTransitions — FSM canon ADR 0143 quebrado, UPDATE direto em current_stage_id volta a passar'
        );
    });

    it('W28 sentry — CancelJobSheetRequest (W25 D8) preserva validação multi-tenant + motivo', function () {
        $reqPath = __DIR__ . '/../../Http/Requests/CancelJobSheetRequest.php';
        expect(file_exists($reqPath))->toBeTrue(
            'CancelJobSheetRequest ausente — W25 D8 LGPD audit (motivo obrigatório) regression'
        );

        $source = file_get_contents($reqPath);
        // Sentry: motivo obrigatório (LGPD audit trail Tier 0)
        expect($source)->toMatch('/(motivo|reason).+required/i');
        // Sentry: business_id scope (anti-IDOR cross-tenant)
        expect($source)->toContain('business_id');
    });
});
