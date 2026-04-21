<?php

namespace Modules\PontoWr2\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\PontoWr2\Entities\Colaborador;

class ColaboradorFactory extends Factory
{
    protected $model = Colaborador::class;

    public function definition(): array
    {
        return [
            'business_id'      => 1,
            'user_id'          => function () {
                $user = \App\Models\User::inRandomOrder()->first()
                    ?? \App\User::inRandomOrder()->first();
                return $user ? $user->id : 1;
            },
            'matricula'        => $this->faker->numerify('MAT-####'),
            'pis'              => $this->faker->numerify('############'),
            'cpf'              => $this->faker->numerify('###########'),
            'escala_atual_id'  => null,
            'controla_ponto'   => true,
            'usa_banco_horas'  => true,
            'admissao'         => $this->faker->dateTimeBetween('-5 years', '-1 month')->format('Y-m-d'),
            'desligamento'     => null,
        ];
    }
}
