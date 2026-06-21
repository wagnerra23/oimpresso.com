<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Services\PlacaLookup;

/**
 * PlacaLookupResult — resultado normalizado da consulta de placa.
 *
 * Escopo (decisão Wagner 2026-06-09 · charter Create v2): SÓ dados técnicos do
 * veículo. NÃO carrega proprietário (nome/CPF) — sem PII de terceiro no fluxo.
 *
 * Value object imutável. `brand`/`model` (marca/modelo) não têm coluna dedicada
 * na tabela `vehicles` V0 — são devolvidos pro frontend exibir e, opcionalmente,
 * prefixar em `notes`. Os demais campos mapeiam 1:1 pras colunas existentes.
 *
 * @see Modules\OficinaAuto\Entities\Vehicle
 * @see Modules\OficinaAuto\Services\VehicleLookupService
 */
final readonly class PlacaLookupResult
{
    public function __construct(
        public string $plate,
        public ?string $brand = null,
        public ?string $model = null,
        public ?int $manufactureYear = null,
        public ?int $modelYear = null,
        public ?string $color = null,
        public ?string $fuelType = null,
        public ?string $chassis = null,
        public ?string $engine = null,
        public ?string $renavam = null,
    ) {
    }

    /**
     * Campos prontos pra auto-preencher o form de Vehicle (apenas colunas que
     * existem na tabela `vehicles`). Marca/modelo ficam de fora — sem coluna V0.
     *
     * Chaves vazias são omitidas pra não sobrescrever input do operador com null.
     *
     * @return array<string, scalar>
     */
    public function toFormFill(): array
    {
        return array_filter([
            'manufacture_year' => $this->manufactureYear,
            'model_year'       => $this->modelYear,
            'color'            => $this->color,
            'fuel_type'        => $this->fuelType,
            'chassis'          => $this->chassis,
            'engine'           => $this->engine,
            'renavam'          => $this->renavam,
        ], static fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Rótulo "MARCA MODELO" pra exibição/notes (sem coluna dedicada V0).
     */
    public function brandModelLabel(): ?string
    {
        $label = trim(implode(' ', array_filter([$this->brand, $this->model])));

        return $label !== '' ? $label : null;
    }

    /**
     * Payload JSON pro frontend. NÃO inclui proprietário (sem PII de terceiro).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'plate'             => $this->plate,
            'brand'             => $this->brand,
            'model'             => $this->model,
            'brand_model_label' => $this->brandModelLabel(),
            'fields'            => $this->toFormFill(),
        ];
    }
}
