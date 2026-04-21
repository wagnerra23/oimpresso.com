<?php

use Faker\Generator as Faker;
use Modules\PontoWr2\Entities\Marcacao;

$factory->define(Marcacao::class, function (Faker $faker) {
    return [
        'business_id'           => 1,
        'colaborador_config_id' => null,
        'rep_id'                => null,
        'nsr'                   => $faker->numberBetween(1, 999999),
        'momento'               => $faker->dateTimeBetween('-7 days', 'now'),
        'origem'                => Marcacao::ORIGEM_MANUAL,
        'tipo'                  => Marcacao::TIPO_ENTRADA,
        'hash_anterior'         => null,
        'hash'                  => hash('sha256', uniqid('mfac', true)),
        'usuario_criador_id'    => 1,
    ];
});
