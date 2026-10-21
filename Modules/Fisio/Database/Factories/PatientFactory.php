<?php

declare(strict_types=1);

namespace Modules\Fisio\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Fisio\Entities\Patient;

/**
 * Perfil clínico 1:1 de um contact. NÃO cria contact nem business: quem chama passa
 * `business_id` e `contact_id` (tenant 98/99 da ADR 0358 — nunca default hardcoded).
 * As fixtures dos contratos (Tests/Support/FisioFixtures) não dependem desta factory;
 * ela existe para seeders/demos e para o implementador, que pode adotá-la nas fixtures.
 *
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        return [
            'main_complaint' => $this->faker->randomElement(['Lombalgia mecânica', 'Dor no ombro ao elevar', 'Pós-operatório de LCA']),
            'goals' => ['voltar às atividades sem dor'],
            'sessions_per_week' => $this->faker->numberBetween(1, 7),
            'status' => 'ativo',
            'condition_flags' => [],
            'blocked_at' => null,
        ];
    }

    public function bloqueado(): static
    {
        return $this->state(fn () => ['blocked_at' => now()]);
    }

    /** @param  list<string>  $flags */
    public function comCondicoes(array $flags): static
    {
        return $this->state(fn () => ['condition_flags' => $flags]);
    }
}
