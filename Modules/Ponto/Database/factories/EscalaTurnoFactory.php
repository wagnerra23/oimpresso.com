<?php

namespace Modules\Ponto\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Ponto\Entities\EscalaTurno;

class EscalaTurnoFactory extends Factory
{
    protected $model = EscalaTurno::class;

    public function definition(): array
    {
        return [
            'escala_id'          => null,
            'dia_semana'         => 1,
            'hora_entrada'       => '08:00:00',
            'hora_almoco_inicio' => '12:00:00',
            'hora_almoco_fim'    => '13:00:00',
            'hora_saida'         => '17:00:00',
        ];
    }
}
