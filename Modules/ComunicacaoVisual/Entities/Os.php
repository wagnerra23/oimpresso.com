<?php

namespace Modules\ComunicacaoVisual\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Os — Ordem de Serviço de comunicação visual.
 *
 * Representa o fluxo produtivo após aprovação do orçamento.
 * Cada etapa reflete o estágio físico do trabalho na gráfica/plotagem:
 *   arte → producao → finalizando → entrega → instalacao → concluida
 *
 * Uma OS pode ser criada:
 *   a) automaticamente ao aprovar um Orcamento (orcamento_id preenchido)
 *   b) avulsa, sem orçamento prévio (orcamento_id = null)
 *
 * Responsável de produção (responsavel_producao_id) é separado do vendedor —
 * quem vende não é necessariamente quem produz.
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * global scope obrigatório — filtra por business_id da sessão automaticamente.
 *
 * @property int         $id
 * @property int         $business_id
 * @property int|null    $orcamento_id
 * @property string      $numero                     OS-YYYY-NNNNN
 * @property string      $status_etapa               arte|producao|finalizando|entrega|instalacao|concluida|cancelada
 * @property string|null $data_inicio
 * @property string|null $data_prazo
 * @property string|null $data_conclusao
 * @property int|null    $vendedor_id
 * @property int|null    $responsavel_producao_id
 * @property float       $valor_total
 * @property string|null $observacoes
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md
 */
class Os extends Model
{
    use SoftDeletes;
    use LogsActivity;

    /**
     * Audit trail Spatie ActivityLog (Wave 18 D7 LGPD compliance).
     *
     * Loga transições de etapa + datas + valor — crítico pra rastreabilidade
     * produção e audit fiscal.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status_etapa', 'data_inicio', 'data_prazo', 'data_conclusao', 'valor_total'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('comvis.os');
    }


    protected $table = 'comvis_os';

    protected $fillable = [
        'business_id',
        'orcamento_id',
        'numero',
        'status_etapa',
        'data_inicio',
        'data_prazo',
        'data_conclusao',
        'vendedor_id',
        'responsavel_producao_id',
        'valor_total',
        'observacoes',
    ];

    protected $casts = [
        'business_id'             => 'integer',
        'orcamento_id'            => 'integer',
        'vendedor_id'             => 'integer',
        'responsavel_producao_id' => 'integer',
        'data_inicio'             => 'date',
        'data_prazo'              => 'date',
        'data_conclusao'          => 'date',
        'valor_total'             => 'decimal:2',
    ];

    /** Etapas em ordem canônica — usado pelo Controller pra validar transições. */
    public const ETAPAS = [
        'arte',
        'producao',
        'finalizando',
        'entrega',
        'instalacao',
        'concluida',
        'cancelada',
    ];

    // ------------------------------------------------------------------
    // Global scope multi-tenant Tier 0 (ADR 0093)
    // ------------------------------------------------------------------

    protected static function booted(): void
    {
        static::addGlobalScope('business_id', function (Builder $query) {
            $businessId = session('user.business_id') ?? session('business.id');
            if ($businessId !== null) {
                $query->where('comvis_os.business_id', $businessId);
            }
        });

        static::creating(function (Os $row) {
            if ($row->business_id === null) {
                $row->business_id = session('user.business_id') ?? session('business.id') ?? 0;
            }
        });
    }

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    /**
     * Orçamento que originou esta OS (pode ser null).
     */
    public function orcamento(): BelongsTo
    {
        return $this->belongsTo(Orcamento::class, 'orcamento_id');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeEtapa(Builder $query, string $etapa): Builder
    {
        return $query->where('comvis_os.status_etapa', $etapa);
    }

    public function scopeEmAberto(Builder $query): Builder
    {
        return $query->whereNotIn('comvis_os.status_etapa', ['concluida', 'cancelada']);
    }

    public function scopeAtrasadas(Builder $query): Builder
    {
        return $query->whereNotNull('comvis_os.data_prazo')
                     ->where('comvis_os.data_prazo', '<', now()->toDateString())
                     ->whereNotIn('comvis_os.status_etapa', ['concluida', 'cancelada']);
    }
}
