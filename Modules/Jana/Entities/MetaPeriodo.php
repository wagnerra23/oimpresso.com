<?php

namespace Modules\Jana\Entities;

use App\Concerns\BelongsToBusinessViaParent;
use Illuminate\Database\Eloquent\Model;

/**
 * MetaPeriodo — alvo de meta dentro de um período (mes/trim/ano/custom).
 *
 * Multi-tenant Tier 0 (ADR 0093): tenancy herdada via parent `meta`
 * (jana_metas.business_id) — defesa em profundidade Wave 7.
 */
class MetaPeriodo extends Model
{
    use BelongsToBusinessViaParent;

    protected $table = 'jana_meta_periodos';

    /** Relação parent que carrega business_id (usada por ScopeByBusinessViaParent). */
    protected string $businessParentRelation = 'meta';

    protected $fillable = [
        'meta_id', 'tipo_periodo', 'data_ini', 'data_fim', 'valor_alvo', 'trajetoria',
    ];

    protected $casts = [
        'data_ini' => 'date',
        'data_fim' => 'date',
        'valor_alvo' => 'decimal:2',
    ];

    public function meta()
    {
        return $this->belongsTo(Meta::class, 'meta_id');
    }
}
