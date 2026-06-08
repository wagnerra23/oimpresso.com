<?php

declare(strict_types=1);

namespace Modules\KB\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\KB\Entities\KbPathStep;

class KbPathStepFactory extends Factory
{
    protected $model = KbPathStep::class;

    public function definition(): array
    {
        return [
            'business_id' => 1,
            'path_id'     => null,  // OBRIGATORIO state()
            'node_id'     => null,  // OBRIGATORIO state()
            'position'    => 1,
            'step_type'   => 'leitura',
            'note'        => null,
        ];
    }
}
