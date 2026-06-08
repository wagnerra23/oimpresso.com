<?php

declare(strict_types=1);

namespace Modules\ConsultaOs\Contracts;

/**
 * ConsultaOsRepositoryInterface — fonte de dados de OS publica.
 *
 * Wave 18 D4 — Repository pattern (SoC brutal). Hoje implementacao unica
 * (MockRepository) retorna array fixo; US-CONSULTA-001 entrega RepairRepository
 * que consulta `transactions` real com filtro multi-tenant via protocolo.
 *
 * Contrato deliberadamente simples: retorna array ou null. Service decide
 * found/stage_mismatch. Reposicao no Provider via bind() troca implementacao.
 *
 * @see Modules\ConsultaOs\Services\ConsultaOsMockService
 * @see Modules\ConsultaOs\Repositories\MockConsultaOsRepository
 */
interface ConsultaOsRepositoryInterface
{
    /**
     * Busca OS por numero (protocolo).
     *
     * @return array<string, mixed>|null Array com chaves: id, client, contact,
     *                                    vendedor, designer, created, updated,
     *                                    stage, items[]. Ou null se nao encontrada.
     */
    public function buscarPorNumero(string $numero): ?array;
}
