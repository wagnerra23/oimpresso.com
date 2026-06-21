<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Services\PlacaLookup;

/**
 * PlacaProvider — contrato de um fornecedor de consulta de placa.
 *
 * Adapter agnóstico (decisão Wagner 2026-06-09): o oimpresso não amarra num
 * fornecedor específico. Drivers concretos (`stub`, `http`) implementam este
 * contrato e são resolvidos por config (`oficina-auto.placa_lookup.driver`).
 * Trocar de fornecedor BR = trocar driver/.env, sem reescrever a regra de negócio.
 *
 * @see Modules\OficinaAuto\Services\PlacaLookup\StubPlacaProvider  (dev/CI, sem custo)
 * @see Modules\OficinaAuto\Services\PlacaLookup\HttpPlacaProvider  (fornecedor real)
 */
interface PlacaProvider
{
    /**
     * Consulta uma placa já normalizada (uppercase, sem separadores).
     *
     * @return PlacaLookupResult|null  null quando a placa não foi encontrada.
     *
     * @throws PlacaLookupException  em falha de configuração/comunicação.
     */
    public function lookup(string $plate): ?PlacaLookupResult;
}
