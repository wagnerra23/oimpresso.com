<?php

use Faker\Generator as Faker;
use Modules\PontoWr2\Entities\Escala;

$factory->define(Escala::class, function (Faker $faker) {
    return [
        'business_id'           => 1,
        'nome'                  => '44h semanais — 8h/dia',
        'codigo'                => strtoupper($faker->lexify('ESC-???')),
        'tipo'                  => Escala::TIPO_FIXA,
        'carga_diaria_minutos'  => 480,   // 8h
        'carga_semanal_minutos' => 2640,  // 44h
        'permite_banco_horas'   => true,
        'ativo'                 => true,
    ];
});
