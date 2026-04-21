<?php

use Faker\Generator as Faker;
use Modules\PontoWr2\Entities\Intercorrencia;

$factory->define(Intercorrencia::class, function (Faker $faker) {
    $data = $faker->dateTimeBetween('-30 days', '-1 day');
    return [
        'business_id'           => 1,
        'colaborador_config_id' => null,
        'codigo'                => 'INC-' . $data->format('Ymd') . '-' . sprintf('%03d', $faker->numberBetween(1, 999)),
        'tipo'                  => 'ESQUECIMENTO_MARCACAO',
        'data'                  => $data->format('Y-m-d'),
        'intervalo_inicio'      => '08:00:00',
        'intervalo_fim'         => '12:00:00',
        'dia_todo'              => false,
        'justificativa'         => $faker->sentence(12),
        'estado'                => Intercorrencia::ESTADO_PENDENTE,
        'prioridade'            => 'NORMAL',
        'impacta_apuracao'      => true,
        'descontar_banco_horas' => false,
        'solicitante_id'        => 1,
    ];
});
