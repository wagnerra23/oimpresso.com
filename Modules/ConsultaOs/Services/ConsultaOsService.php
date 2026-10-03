<?php

declare(strict_types=1);

namespace Modules\ConsultaOs\Services;

use App\Util\OtelHelper;
use Modules\ConsultaOs\Contracts\ConsultaOsRepositoryInterface;

/**
 * ConsultaOsService — consulta pública de OS (US-CONSULTA-001: dados reais do Repair).
 *
 * Era `ConsultaOsMockService` (Wave 18, mock de 4 OS fixas). Renomeado em 2026-10-02 quando
 * a fonte virou o Modules/Repair — manter "Mock" no nome de um endpoint público real
 * induziria a ler o Tier 0 como teatro.
 *
 * SoC: o Controller valida (ConsultaPublicaRequest) e audita; este Service decide
 * found/not_found; o Repositório monta o payload público campo a campo.
 *
 * Multi-tenant Tier 0 (ADR 0093): rota pública, sem business_id — ver o PENDENTE [W] no
 * docblock do RepairConsultaOsRepository.
 *
 * @see Modules\ConsultaOs\Repositories\RepairConsultaOsRepository
 * @see memory/requisitos/ConsultaOs/SPEC.md US-CONSULTA-001
 */
class ConsultaOsService
{
    public function __construct(
        private readonly ConsultaOsRepositoryInterface $repository,
    ) {
    }

    /**
     * @return array{found: bool, ordens?: list<array<string, mixed>>, reason?: string}
     */
    public function buscar(string $tipo, string $numero, ?string $serie = null): array
    {
        return OtelHelper::span('consultaos.busca_publica', [
            'tipo' => $tipo,
            // Sem business_id intencionalmente — rota pública (ADR 0093, escape comentado).
        ], function () use ($tipo, $numero, $serie) {
            $ordens = $this->repository->buscar($tipo, $numero, $serie);

            if ($ordens === []) {
                return ['found' => false, 'reason' => 'not_found'];
            }

            return ['found' => true, 'ordens' => $ordens];
        });
    }
}
