<?php

declare(strict_types=1);

namespace App\Domain\Oficina;

/**
 * Rótulos dos tipos de veículo da Oficina (chave = valor do ENUM `vehicles.vehicle_type`).
 *
 * Mora no núcleo para o app das lojas (`App\Http\Controllers\Api\App\OficinaController`) ler
 * sem importar de `Modules/` (catraca DependencyDirectionTest: a seta vai módulo → núcleo).
 * `Modules\OficinaAuto\...\VehicleController::vehicleTypes()` devolve esta mesma lista.
 */
final class TiposVeiculo
{
    /** @var array<string, string> */
    public const ROTULOS = [
        'caminhao'             => 'Caminhão',
        'cavalo'               => 'Cavalo (truck-cabine)',
        'semi_reboque'         => 'Semi-reboque',
        'cacamba_estacionaria' => 'Caçamba estacionária',
        'automovel'            => 'Automóvel',
        'motocicleta'          => 'Motocicleta',
        'outro'                => 'Outro',
    ];
}
