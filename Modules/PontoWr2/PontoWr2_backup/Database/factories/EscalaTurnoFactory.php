<?php

use Faker\Generator as Faker;
use Modules\PontoWr2\Entities\EscalaTurno;

$factory->define(EscalaTurno::class, function (Faker $faker) {
    return [
        'escala_id'          => null, // definir explicitamente ao usar
        'dia_semana'         => 1,    // segunda
        'hora_entrada'       => '08:00:00',
        'hora_almoco_inicio' => '12:00:00',
        'hora_almoco_fim'    => '13:00:00',
        'hora_saida'         => '17:00:00',
    ];
});
