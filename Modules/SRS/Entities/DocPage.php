<?php

namespace Modules\SRS\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * DocPage — catálogo de páginas Inertia/React do projeto (governance).
 *
 * Wave 17 — Multi-tenant Tier 0 (ADR 0093) — EXCEÇÃO REPO-WIDE documentada.
 *
 * Tabela `docs_pages` é repo-wide governance (governança do código fonte do
 * próprio oimpresso — paths, components, módulos, status, ADRs, stories).
 * NÃO contém dados de negócio per-tenant. Equivalente conceitual a
 * `permissions`, `migrations`, `media_library` (catálogos compartilhados).
 *
 * Migration 2026_04_22_000006 NÃO inclui `business_id` por design — o conteúdo
 * é o mesmo pra todos os tenants do oimpresso (Wagner=1, ROTA LIVRE=4, etc).
 * Toda página `resources/js/Pages/X/Y.tsx` é uma só, indiferente ao business
 * que está logado.
 *
 * NÃO aplicar HasBusinessScope: tabela sem coluna `business_id` causaria
 * "Unknown column 'docs_pages.business_id'" em toda query. Acesso a este
 * catálogo é restrito a Wagner/admin via gate (não exposição cross-tenant).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md §"Exceção repo-wide"
 */
class DocPage extends Model
{
    protected $table = 'docs_pages';

    protected $fillable = [
        'path',
        'component',
        'module',
        'status',
        'stories',
        'rules',
        'adrs',
        'tests',
        'file_path',
        'last_synced_at',
    ];

    protected $casts = [
        'stories'        => 'array',
        'rules'          => 'array',
        'adrs'           => 'array',
        'tests'          => 'array',
        'last_synced_at' => 'datetime',
    ];
}
