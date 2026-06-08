<?php

namespace Modules\SRS\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * DocValidationRun — log de execução do DocValidator (governance/CI).
 *
 * Wave 17 — Multi-tenant Tier 0 (ADR 0093) — EXCEÇÃO REPO-WIDE documentada.
 *
 * Tabela `docs_validation_runs` armazena execuções do validador de docs do
 * próprio projeto (módulo SRS), não dados de negócio per-tenant. Validação
 * roda sobre `memory/requisitos/<Mod>/*.md` (canônico repo-wide), com
 * `module` apontando pra qual módulo do código foi validado — não pra
 * business.
 *
 * Migration 2026_04_22_000007 NÃO inclui `business_id` por design — corrida
 * de validação é evento global do projeto (ex: cron `srs:validate` rodado por
 * admin), não evento per-business. Equivalente a `failed_jobs`, `migrations`
 * ou logs de aplicação.
 *
 * NÃO aplicar HasBusinessScope: tabela sem coluna `business_id` causaria
 * "Unknown column 'docs_validation_runs.business_id'" em toda query. Acesso
 * é restrito a admin/Wagner via gate (não exposto a tenants).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md §"Exceção repo-wide"
 * @see Modules/SRS/Services/DocValidator.php
 */
class DocValidationRun extends Model
{
    protected $table = 'docs_validation_runs';

    protected $fillable = [
        'run_at',
        'module',
        'issues_total',
        'issues_critical',
        'issues',
        'health_score',
    ];

    protected $casts = [
        'run_at' => 'datetime',
        'issues' => 'array',
    ];
}
