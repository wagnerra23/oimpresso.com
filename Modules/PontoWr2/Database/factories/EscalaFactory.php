<?php

namespace Modules\PontoWr2\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\PontoWr2\Entities\Escala;

class EscalaFactory extends Factory
{
    protected $model = Escala::class;

    public function definition(): array
    {
        return [
            'business_id'           => 1,
            'nome'                  => '44h semanais — 8h/dia',
            'codigo'                => strtoupper($this->faker->lexify('ESC-???')),
            'tipo'                  => Escala::TIPO_FIXA,
            'carga_diaria_minutos'  => 480,
            'carga_semanal_minutos' => 2640,
            'permite_banco_horas'   => true,
            'ativo'                 => true,
        ];
    }
}
