<?php

declare(strict_types=1);

namespace Modules\KB\Observers;

use Modules\KB\Entities\KbDecisionTreeStep;

/**
 * KbDecisionTreeStepObserver — enforce invariante de branches.
 *
 * Por linha:
 *   - exatamente UM de (yes_next_step_id, yes_fix) populado
 *   - exatamente UM de (no_next_step_id, no_fix) populado
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §7
 */
class KbDecisionTreeStepObserver
{
    public function saving(KbDecisionTreeStep $step): void
    {
        $this->validateBranch($step, 'yes');
        $this->validateBranch($step, 'no');
    }

    private function validateBranch(KbDecisionTreeStep $step, string $branch): void
    {
        $hasNext = $step->{"{$branch}_next_step_id"} !== null;
        $hasFix  = trim((string) $step->{"{$branch}_fix"}) !== '';

        if ($hasNext && $hasFix) {
            throw new \DomainException(
                "KbDecisionTreeStep tree={$step->tree_id} pos={$step->position}: ".
                "branch '{$branch}' não pode ter '{$branch}_next_step_id' E '{$branch}_fix' ".
                'ao mesmo tempo. Escolha um.'
            );
        }

        if (! $hasNext && ! $hasFix) {
            throw new \DomainException(
                "KbDecisionTreeStep tree={$step->tree_id} pos={$step->position}: ".
                "branch '{$branch}' precisa ter '{$branch}_next_step_id' OU '{$branch}_fix' ".
                'populado.'
            );
        }
    }
}
