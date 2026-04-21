<?php

namespace Modules\PontoWr2\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\PontoWr2\Entities\Marcacao;

class MarcacaoFactory extends Factory
{
    protected $model = Marcacao::class;

    public function definition(): array
    {
        return [
            'business_id'           => 1,
            'colaborador_config_id' => null,
            'rep_id'                => null,
            'nsr'                   => $this->faker->numberBetween(1, 999999),
            'momento'               => $this->faker->dateTimeBetween('-7 days', 'now'),
            'origem'                => Marcacao::ORIGEM_MANUAL,
            'tipo'                  => Marcacao::TIPO_ENTRADA,
            'hash_anterior'         => null,
            'hash'                  => hash('sha256', uniqid('mfac', true)),
            'usuario_criador_id'    => 1,
        ];
    }
}
