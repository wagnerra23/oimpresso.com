<?php

use Faker\Generator as Faker;
use Modules\PontoWr2\Entities\Colaborador;

/**
 * Factory Laravel 5.8 — formato array, não classe.
 */
$factory->define(Colaborador::class, function (Faker $faker) {
    return [
        'business_id'      => 1,
        'user_id'          => function () use ($faker) {
            // Assume existência de users no core — fallback para id 1 em dev
            $user = \App\User::inRandomOrder()->first();
            return $user ? $user->id : 1;
        },
        'matricula'        => $faker->numerify('MAT-####'),
        'pis'              => $faker->numerify('############'),
        'cpf'              => $faker->numerify('###########'),
        'escala_atual_id'  => null,
        'controla_ponto'   => true,
        'usa_banco_horas'  => true,
        'admissao'         => $faker->dateTimeBetween('-5 years', '-1 month')->format('Y-m-d'),
        'desligamento'     => null,
    ];
});
