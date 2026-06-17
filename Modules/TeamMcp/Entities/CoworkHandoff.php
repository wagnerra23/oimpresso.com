<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * CoworkHandoff — PR-1 Loop de Handoff Zero-Paste (Fase 0 · ADR 0283).
 *
 * Fonte da verdade dos handoffs de design Cowork→Code (F1→F3). `handoff:ingest`
 * cria 'pending'; o tool `handoff-ack` (PR-2) fecha pra 'applied'/'rejected'.
 *
 * Tier 0 ({@see ADR 0093}): SEM business_id e SEM HasBusinessScope — handoff de
 * design é artefato do REPO (cross-tenant), não dado de tenant. Espelha
 * {@see McpIngestHeartbeat}. NUNCA adicionar global scope de business aqui.
 *
 * Append-only no plano de slug ({@see ADR 0130}): revisão = nova version
 * 'pending' + anterior 'superseded'. NUNCA delete.
 *
 * @property int $id
 * @property string $slug
 * @property int $version
 * @property string $tela
 * @property string $status
 * @property string|null $audited_against
 * @property string $body_md
 * @property array $files_json
 * @property string $source_hash
 * @property string $sig
 * @property string $created_by
 * @property \Illuminate\Support\Carbon|null $applied_at
 * @property string|null $applied_by
 * @property string|null $pr_url
 * @property array|null $gate_status
 */
class CoworkHandoff extends Model
{
    protected $table = 'cowork_handoffs';

    // created_at via useCurrent na migration; applied_at gravado manualmente no
    // ack. Sem updated_at (espelha McpAuditLog — escrita controlada).
    public $timestamps = false;

    protected $fillable = [
        'slug', 'version', 'tela', 'status', 'audited_against',
        'body_md', 'files_json', 'source_hash', 'sig', 'created_by',
        'created_at', 'applied_at', 'applied_by', 'pr_url', 'gate_status',
    ];

    protected $casts = [
        'version'     => 'integer',
        'files_json'  => 'array',
        'gate_status' => 'array',
        'created_at'  => 'datetime',
        'applied_at'  => 'datetime',
    ];

    /** Status válidos (espelha o comentário da coluna `status`). */
    public const STATUSES = ['pending', 'applied', 'rejected', 'stale', 'superseded'];
}
