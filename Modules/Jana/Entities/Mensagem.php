<?php

namespace Modules\Jana\Entities;

use App\Concerns\BelongsToBusinessViaParent;
use Illuminate\Database\Eloquent\Model;

/**
 * Mensagem — append-only (ver GLOSSARY).
 *
 * Multi-tenant Tier 0 (ADR 0093): tenancy herdada via parent `conversa`
 * (jana_conversas.business_id) — defesa em profundidade Wave 7.
 */
class Mensagem extends Model
{
    use BelongsToBusinessViaParent;

    protected $table = 'jana_mensagens';

    /** Relação parent que carrega business_id (usada por ScopeByBusinessViaParent). */
    protected string $businessParentRelation = 'conversa';

    protected $fillable = [
        'conversa_id', 'role', 'content', 'tokens_in', 'tokens_out',
    ];

    public const UPDATED_AT = null;

    public function conversa()
    {
        return $this->belongsTo(Conversa::class, 'conversa_id');
    }
}
