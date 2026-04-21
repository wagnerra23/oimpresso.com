<?php

namespace Modules\PontoWr2\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\PontoWr2\Entities\Intercorrencia;

class IntercorrenciaFactory extends Factory
{
    protected $model = Intercorrencia::class;

    public function definition(): array
    {
        $data = $this->faker->dateTimeBetween('-30 days', '-1 day');
        return [
            'business_id'           => 1,
            'colaborador_config_id' => null,
            'codigo'                => 'INC-' . $data->format('Ymd') . '-' . sprintf('%03d', $this->faker->numberBetween(1, 999)),
            'tipo'                  => 'ESQUECIMENTO_MARCACAO',
            'data'                  => $data->format('Y-m-d'),
            'intervalo_inicio'      => '08:00:00',
            'intervalo_fim'         => '12:00:00',
            'dia_todo'              => false,
            'justificativa'         => $this->faker->sentence(12),
            'estado'                => Intercorrencia::ESTADO_PENDENTE,
            'prioridade'            => 'NORMAL',
            'impacta_apuracao'      => true,
            'descontar_banco_horas' => false,
            'solicitante_id'        => 1,
        ];
    }
}
