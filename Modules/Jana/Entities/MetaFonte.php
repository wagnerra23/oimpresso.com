<?php

namespace Modules\Jana\Entities;

use App\Concerns\BelongsToBusinessViaParent;
use Illuminate\Database\Eloquent\Model;

/**
 * MetaFonte — driver de cálculo (sql/php/http).
 *
 * Ver adr/tech/0001-drivers-apuracao-plugaveis.md pras regras de segurança.
 *
 * Multi-tenant Tier 0 (ADR 0093): tenancy herdada via parent `meta`
 * (jana_metas.business_id) — defesa em profundidade Wave 7.
 */
class MetaFonte extends Model
{
    use BelongsToBusinessViaParent;

    protected $table = 'jana_meta_fontes';

    /** Relação parent que carrega business_id (usada por ScopeByBusinessViaParent). */
    protected string $businessParentRelation = 'meta';

    protected $fillable = [
        'meta_id', 'driver', 'config_json', 'cadencia',
    ];

    protected $casts = [
        'config_json' => 'array',
    ];

    public function meta()
    {
        return $this->belongsTo(Meta::class, 'meta_id');
    }
}
