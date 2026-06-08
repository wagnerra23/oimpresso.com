<?php

declare(strict_types=1);

namespace Modules\Crm\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * W27 — Deal (oportunidade de venda em pipeline Kanban Pipedrive/HubSpot-like).
 *
 * Representa uma OPORTUNIDADE comercial que percorre 6 stages canônicos
 * (PT-BR) e pode gerar 0..N Proposals durante seu ciclo de vida.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093):
 *   - HasBusinessScope trait (defesa-em-profundidade — soma ao where explícito do Controller)
 *   - business_id sempre populado em $fillable
 *
 * Auditoria LGPD:
 *   - LogsActivity registra mudanças de stage/valor/owner
 *   - description NUNCA contém PII (ADR 0093 §LGPD) — só nome do campo + valor sanitizado
 *
 * @see Modules/Crm/Services/DealPipelineService.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 *
 * @property int $id
 * @property int $business_id
 * @property int|null $contact_id
 * @property int|null $proposal_id
 * @property string $titulo
 * @property string $stage
 * @property string $valor_estimado
 * @property \Illuminate\Support\Carbon|null $data_fechamento_prevista
 * @property int $owner_user_id
 * @property array|null $metadata
 */
class Deal extends Model
{
    use HasBusinessScope;
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'crm_deals';

    /**
     * Stages canônicos PT-BR (5-7 sweet spot — best practice Pipedrive/HubSpot 2026).
     */
    public const STAGE_LEAD = 'lead';
    public const STAGE_QUALIFICACAO = 'qualificacao';
    public const STAGE_PROPOSTA = 'proposta';
    public const STAGE_NEGOCIACAO = 'negociacao';
    public const STAGE_GANHO = 'ganho';
    public const STAGE_PERDIDO = 'perdido';

    public const STAGES = [
        self::STAGE_LEAD,
        self::STAGE_QUALIFICACAO,
        self::STAGE_PROPOSTA,
        self::STAGE_NEGOCIACAO,
        self::STAGE_GANHO,
        self::STAGE_PERDIDO,
    ];

    /**
     * Probabilidades de fechamento por stage (weighted forecast — HubSpot pattern).
     * Hardcoded baseline; override per-business via metadata['probabilities'] (backlog).
     *
     * @var array<string, float>
     */
    public const PROBABILIDADES_DEFAULT = [
        self::STAGE_LEAD          => 0.10,
        self::STAGE_QUALIFICACAO  => 0.25,
        self::STAGE_PROPOSTA      => 0.50,
        self::STAGE_NEGOCIACAO    => 0.75,
        self::STAGE_GANHO         => 1.00,
        self::STAGE_PERDIDO       => 0.00,
    ];

    protected $fillable = [
        'business_id',
        'contact_id',
        'proposal_id',
        'titulo',
        'stage',
        'valor_estimado',
        'data_fechamento_prevista',
        'owner_user_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'data_fechamento_prevista' => 'date',
        'valor_estimado' => 'decimal:2',
    ];

    /**
     * Defaults Eloquent-level (cobre SQLite que não aplica DB default em INSERT
     * sem coluna explícita; em MySQL a migration default cobre como fallback).
     */
    protected $attributes = [
        'stage' => self::STAGE_LEAD,
        'valor_estimado' => 0,
    ];

    /**
     * Stage é terminal? (ganho/perdido — não permite mover pra outro stage).
     */
    public function isTerminal(): bool
    {
        return in_array($this->stage, [self::STAGE_GANHO, self::STAGE_PERDIDO], true);
    }

    /**
     * Probabilidade de fechamento do stage atual (0.0 a 1.0).
     */
    public function probabilidade(): float
    {
        return self::PROBABILIDADES_DEFAULT[$this->stage] ?? 0.0;
    }

    /**
     * Valor ponderado (valor_estimado × probabilidade) — usado em forecast.
     */
    public function valorPonderado(): float
    {
        return (float) $this->valor_estimado * $this->probabilidade();
    }

    /**
     * Auditoria LGPD-safe — registra mudanças sem expor PII em description.
     *
     * Campos logados: stage, valor_estimado, owner_user_id, data_fechamento_prevista.
     * NÃO loga: titulo (pode conter nome cliente), contact_id (relação PII).
     *
     * @see ADR 0093 §LGPD multi-tenant
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['stage', 'valor_estimado', 'owner_user_id', 'data_fechamento_prevista'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('crm.deal');
    }

    /**
     * Scope: deals abertos (não-terminais).
     */
    public function scopeAbertos($query)
    {
        return $query->whereNotIn('stage', [self::STAGE_GANHO, self::STAGE_PERDIDO]);
    }

    /**
     * Scope: deals ganhos (closed-won).
     */
    public function scopeGanhos($query)
    {
        return $query->where('stage', self::STAGE_GANHO);
    }
}
