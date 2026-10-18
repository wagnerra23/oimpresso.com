<?php

declare(strict_types=1);

namespace Modules\Fisio\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Fisio\Entities\Program;

/** Programa ATIVO manual. Quem chama passa business_id, patient_id e created_by. */
class ProgramFactory extends Factory
{
    protected $model = Program::class;

    public function definition(): array
    {
        return [
            'phase' => 'fase_1',
            'status' => 'ativo',
            'origin' => 'manual',
            'stop_instruction' => 'Pare os exercícios de hoje e fale com a clínica.',
            'pain_alert_threshold' => null,
            'starts_at' => now()->toDateString(),
        ];
    }

    public function encerrado(): static
    {
        return $this->state(fn () => ['status' => 'encerrado']);
    }
}
