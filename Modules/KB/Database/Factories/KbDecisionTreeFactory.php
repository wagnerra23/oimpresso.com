<?php

declare(strict_types=1);

namespace Modules\KB\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\KB\Entities\KbDecisionTree;

/**
 * Factory de KbDecisionTree (troubleshooter).
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §7
 *
 * `root_step_id` é setado em segundo INSERT após criar os steps — factory NÃO
 * preenche pra evitar FK violation. Test responsável atualiza após criar steps.
 */
class KbDecisionTreeFactory extends Factory
{
    protected $model = KbDecisionTree::class;

    public function definition(): array
    {
        static $i = 0; $i++;
        return [
            'business_id'    => 1,
            'slug'           => sprintf('troubleshooter-%04d', $i),
            'title'          => "Troubleshooter {$i}",
            'equip'          => null,
            'when_to_use'    => null,
            'hue'            => 240,
            'status'         => 'published',
            'root_step_id'   => null,
            'author_user_id' => null,
        ];
    }
}
