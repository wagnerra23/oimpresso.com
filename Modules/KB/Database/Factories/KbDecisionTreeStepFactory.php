<?php

declare(strict_types=1);

namespace Modules\KB\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\KB\Entities\KbDecisionTreeStep;

/**
 * Factory de KbDecisionTreeStep.
 *
 * Invariante por linha (Observer enforces):
 *   - exatamente UM de (yes_next_step_id, yes_fix) populado
 *   - exatamente UM de (no_next_step_id, no_fix) populado
 *
 * Default: ambos branches terminam com fix (não aponta pra próximo step).
 */
class KbDecisionTreeStepFactory extends Factory
{
    protected $model = KbDecisionTreeStep::class;

    public function definition(): array
    {
        return [
            'business_id'      => 1,
            'tree_id'          => null,  // OBRIGATORIO
            'position'         => 1,
            'question'         => 'A peça está ligada?',
            'yes_next_step_id' => null,
            'yes_fix'          => 'Verificar tensão na rede.',
            'yes_fix_node_id'  => null,
            'no_next_step_id'  => null,
            'no_fix'           => 'Trocar fusível.',
            'no_fix_node_id'   => null,
        ];
    }
}
