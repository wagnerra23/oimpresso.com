<?php

namespace Modules\Copiloto\Contracts;

/**
 * MemoriaContrato — interface canônica da camada C (memória semântica).
 *
 * Drivers planejados (ADRs 0031/0033/0036):
 *  - MeilisearchDriver (DEFAULT — self-hosted, R$0/mês recorrente)
 *  - Mem0RestDriver (CONDICIONAL — sprint 8+ se trigger ativar)
 *  - NullMemoriaDriver (dev / dry_run / CI)
 *
 * Multi-tenant scope obrigatório: businessId + userId em toda chamada (ver US-COPI-MEM-005).
 *
 * Ver:
 *  - memory/decisions/0031-memoriacontrato-mem0-default.md
 *  - memory/decisions/0033-vector-store-meilisearch-pgvector-mem0.md
 *  - memory/decisions/0036-replanejamento-meilisearch-first.md
 */
interface MemoriaContrato
{
    /**
     * Persiste um fato sobre o usuário no scope (business, user).
     */
    public function lembrar(int $businessId, int $userId, string $fato, array $metadata = []): MemoriaPersistida;

    /**
     * Busca top-K memórias relevantes pra query (semantic search).
     *
     * @return MemoriaPersistida[]
     */
    public function buscar(int $businessId, int $userId, string $query, int $topK = 5): array;

    /**
     * Atualiza fato existente. Em drivers temporais, supersedes o antigo (valid_until = now).
     */
    public function atualizar(int $memoriaId, string $novoFato, array $metadata = []): void;

    /**
     * Esquece (LGPD opt-out). Soft delete na tabela; índice removido.
     */
    public function esquecer(int $memoriaId): void;

    /**
     * Lista todas as memórias ativas do user (pra tela "O Copiloto lembra de você").
     *
     * @return MemoriaPersistida[]
     */
    public function listar(int $businessId, int $userId): array;
}
