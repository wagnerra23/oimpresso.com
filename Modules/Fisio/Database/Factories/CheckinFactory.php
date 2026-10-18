<?php

declare(strict_types=1);

namespace Modules\Fisio\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Fisio\Entities\Checkin;

/**
 * Check-in de HISTÓRICO. Quem chama passa business_id, patient_id, program_id, date e done_item_ids.
 *
 * @extends Factory<Checkin>
 */
class CheckinFactory extends Factory
{
    protected $model = Checkin::class;

    public function definition(): array
    {
        return [
            'done_item_ids' => [],
            'pain' => $this->faker->numberBetween(0, 6),
            'effort' => $this->faker->randomElement(['facil', 'adequado', 'dificil']),
            'note' => null,
            'red_flags' => [],
        ];
    }

    public function comDor(int $dor): static
    {
        return $this->state(fn () => ['pain' => $dor]);
    }

    /** @param  list<string>  $flags */
    public function comSinal(array $flags): static
    {
        return $this->state(fn () => ['red_flags' => $flags]);
    }
}
