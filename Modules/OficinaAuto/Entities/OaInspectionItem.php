<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Arquivos\Concerns\HasArquivos;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * OaInspectionItem — item DVI (Vistoria Digital) de uma Ordem de Serviço.
 *
 * Wave 3 OficinaAuto (US-OFICINA-035) — wedge competitivo vs RepairShopr/mHelpDesk
 * CAPTERRA-FICHA Repair gap #3. Mecânico registra itens vistoriados com semáforo
 * (verde/amarelo/vermelho) + recomendação + valor pra cliente aprovar via
 * WhatsApp (integração com US-OFICINA-014 chega em Wave 3b).
 *
 * Schema: oa_inspection_items (migration 2026_05_26_120002).
 *
 * Multi-tenant Tier 0 ([ADR 0093]): global scope obrigatório.
 *
 * @property int          $id
 * @property int          $business_id
 * @property int          $service_order_id
 * @property string       $categoria              motor|freios|correia|bateria|pneus|suspensao|direcao|eletrica|fluidos|outro
 * @property string       $descricao
 * @property string       $severity               ok | atencao | critico
 * @property string|null  $recomendacao
 * @property string|null  $valor_recomendado      decimal:2
 * @property array|null   $metadata               json livre (vida_util_pct, km_restantes, voltagem...)
 * @property string|null  $photo_url
 * @property int          $sort_order
 *
 * @see memory/requisitos/OficinaAuto/SPEC.md US-OFICINA-035
 */
class OaInspectionItem extends Model
{
    use HasArquivos; // Gap 1 (2026-05-26) — upload foto inline DVI item via Modules/Arquivos backbone (ADR 0123). Best-practice 2026 (AutoVitals/Tekmetric): foto por item DVI, não anexo solto.
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'oa_inspection_items';

    public const SEVERITY_OK = 'ok';
    public const SEVERITY_ATENCAO = 'atencao';
    public const SEVERITY_CRITICO = 'critico';

    public const SEVERITIES_VALIDAS = [
        self::SEVERITY_OK,
        self::SEVERITY_ATENCAO,
        self::SEVERITY_CRITICO,
    ];

    /** Categorias enum espelhadas da migration. Extensível futuramente via migration ALTER. */
    public const CATEGORIAS = [
        'motor',
        'freios',
        'correia',
        'bateria',
        'pneus',
        'suspensao',
        'direcao',
        'eletrica',
        'fluidos',
        'outro',
    ];

    /** Severidades que entram no "TOTAL RECOMENDADO · CLIENTE" (atencao + critico). */
    public const SEVERIDADES_RECOMENDAVEIS = [
        self::SEVERITY_ATENCAO,
        self::SEVERITY_CRITICO,
    ];

    // Gap 2 PR 2b — tracking aprovação cliente item-a-item via mobile (US-OFICINA-035 Wave 3b)
    public const CLIENT_DECISION_PENDING = 'pending';

    public const CLIENT_DECISION_APPROVED = 'approved';

    public const CLIENT_DECISION_REJECTED = 'rejected';

    public const CLIENT_DECISIONS_VALIDAS = [
        self::CLIENT_DECISION_PENDING,
        self::CLIENT_DECISION_APPROVED,
        self::CLIENT_DECISION_REJECTED,
    ];

    protected $fillable = [
        'business_id',
        'service_order_id',
        'categoria',
        'descricao',
        'severity',
        'recomendacao',
        'valor_recomendado',
        'metadata',
        'photo_url',
        'sort_order',
        'client_decision',
        'client_decided_at',
    ];

    protected $casts = [
        'business_id'       => 'integer',
        'service_order_id'  => 'integer',
        'valor_recomendado' => 'decimal:2',
        'metadata'          => 'array',
        'sort_order'        => 'integer',
        'client_decided_at' => 'datetime',
    ];

    // ------------------------------------------------------------------
    // Global scope multi-tenant Tier 0 ([ADR 0093])
    // ------------------------------------------------------------------

    protected static function booted(): void
    {
        static::addGlobalScope('business_id', function (Builder $query) {
            $businessId = session('user.business_id') ?? session('business.id');
            if ($businessId !== null) {
                $query->where('oa_inspection_items.business_id', $businessId);
            }
        });

        static::creating(function (OaInspectionItem $row) {
            if ($row->business_id === null) {
                $row->business_id = session('user.business_id') ?? session('business.id') ?? 0;
            }
        });
    }

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class, 'service_order_id');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeOks(Builder $query): Builder
    {
        return $query->where('severity', self::SEVERITY_OK);
    }

    public function scopeAtencoes(Builder $query): Builder
    {
        return $query->where('severity', self::SEVERITY_ATENCAO);
    }

    public function scopeCriticas(Builder $query): Builder
    {
        return $query->where('severity', self::SEVERITY_CRITICO);
    }

    /** Items recomendáveis (atencao + critico) — entram no "TOTAL RECOMENDADO". */
    public function scopeRecomendaveis(Builder $query): Builder
    {
        return $query->whereIn('severity', self::SEVERIDADES_RECOMENDAVEIS);
    }

    // ------------------------------------------------------------------
    // LGPD audit trail (D7.b — Wave 14 pattern)
    // ------------------------------------------------------------------

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'categoria',
                'descricao',
                'severity',
                'recomendacao',
                'valor_recomendado',
                'sort_order',
                'client_decision',
                'client_decided_at',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('oficinaauto.dvi_inspection_item');
    }
}
