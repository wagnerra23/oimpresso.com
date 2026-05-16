<?php

namespace Modules\Jana\Entities;

use App\Concerns\BelongsToBusinessViaParent;
use Illuminate\Database\Eloquent\Model;

/**
 * Sugestao — proposta de meta gerada pela IA.
 *
 * Quando o gestor escolhe, `meta_id` é preenchido com a Meta criada.
 * Quando rejeita, `rejeitada_em` marca — feedback passivo pro prompt futuro.
 *
 * Multi-tenant Tier 0 (ADR 0093): tenancy herdada via parent `conversa`
 * (jana_conversas.business_id) — defesa em profundidade Wave 7.
 */
class Sugestao extends Model
{
    use BelongsToBusinessViaParent;

    protected $table = 'jana_sugestoes';

    /** Relação parent que carrega business_id (usada por ScopeByBusinessViaParent). */
    protected string $businessParentRelation = 'conversa';

    protected $fillable = [
        'conversa_id', 'meta_id', 'payload_json', 'escolhida_em', 'rejeitada_em',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'escolhida_em' => 'datetime',
        'rejeitada_em' => 'datetime',
    ];

    public function conversa()
    {
        return $this->belongsTo(Conversa::class, 'conversa_id');
    }

    public function meta()
    {
        return $this->belongsTo(Meta::class, 'meta_id');
    }
}
