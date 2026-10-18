<?php

declare(strict_types=1);

namespace Modules\Fisio\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Fisio\Entities\Exercise;

/** Exercício da biblioteca. Default = GLOBAL (business_id nulo); `proprio($biz)` para o da clínica. */
class ExerciseFactory extends Factory
{
    protected $model = Exercise::class;

    public function definition(): array
    {
        return [
            'business_id' => null,
            'name' => 'Exercício '.$this->faker->unique()->numerify('####'),
            'region' => $this->faker->randomElement(['lombar', 'cervical', 'ombro', 'joelho', 'quadril', 'tornozelo']),
            'type' => $this->faker->randomElement(['fortalecimento', 'alongamento', 'mobilidade', 'equilibrio']),
            'pose' => $this->faker->randomElement(['deitado', 'sentado', 'em_pe']),
            'steps' => ['Posicione-se', 'Execute o movimento devagar', 'Volte à posição inicial'],
            'default_sets' => 3,
            'default_reps' => 10,
            'default_hold_s' => 0,
            'times_per_day' => 1,
            'safety_notes' => ['Não prenda a respiração'],
            'contraindication_flags' => [],
            'needs_support' => false,
            'is_custom' => false,
        ];
    }

    public function proprio(int $businessId): static
    {
        return $this->state(fn () => ['business_id' => $businessId, 'is_custom' => true]);
    }

    /** @param  list<string>  $flags */
    public function contraindicadoPara(array $flags): static
    {
        return $this->state(fn () => ['contraindication_flags' => $flags]);
    }
}
