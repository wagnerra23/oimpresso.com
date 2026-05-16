<?php

namespace Modules\Jana\Entities;

use App\Concerns\BelongsToBusinessViaParent;
use Illuminate\Database\Eloquent\Model;

/**
 * MetaApuracao — registro append-only do realizado.
 *
 * Idempotência garantida por unique (meta_id, data_ref, fonte_query_hash).
 * Ver adr/tech/0001-drivers-apuracao-plugaveis.md.
 *
 * Multi-tenant Tier 0 (ADR 0093): tenancy herdada via parent `meta`
 * (jana_metas.business_id) — defesa em profundidade Wave 7.
 */
class MetaApuracao extends Model
{
    use BelongsToBusinessViaParent;

    protected $table = 'jana_meta_apuracoes';

    /** Relação parent que carrega business_id (usada por ScopeByBusinessViaParent). */
    protected string $businessParentRelation = 'meta';

    protected $fillable = [
        'meta_id', 'data_ref', 'valor_realizado', 'calculado_em', 'fonte_query_hash',
    ];

    protected $casts = [
        'data_ref' => 'date',
        'calculado_em' => 'datetime',
        'valor_realizado' => 'decimal:2',
    ];

    public function meta()
    {
        return $this->belongsTo(Meta::class, 'meta_id');
    }
}
