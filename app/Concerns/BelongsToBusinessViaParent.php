<?php

declare(strict_types=1);

namespace App\Concerns;

use Modules\Jana\Scopes\ScopeByBusinessViaParent;

/**
 * Trait BelongsToBusinessViaParent — multi-tenant Tier 0 via FK chain (ADR 0093).
 *
 * Para Models FILHAS que herdam tenancy do parent (sem coluna business_id direta).
 * Companion da HasBusinessScope (que cobre Models com business_id direto).
 *
 * Uso canônico (Wave 7 audit Jana 2026-05-16):
 *
 *   use App\Concerns\BelongsToBusinessViaParent;
 *
 *   class Sugestao extends Model
 *   {
 *       use BelongsToBusinessViaParent;
 *
 *       protected string $businessParentRelation = 'conversa';
 *
 *       public function conversa() { return $this->belongsTo(Conversa::class); }
 *   }
 *
 * Por que existe (defesa em profundidade):
 *  - SEM trait: `Sugestao::all()` autenticado como biz=1 retorna TUDO (cross-tenant)
 *  - COM trait: scope global injeta `whereHas('conversa', fn ($q) => $q->where(biz))`
 *  - Eager-load via parent (`$conversa->sugestoes`) já funcionava — não regride
 *
 * NÃO é substituto de adicionar `business_id` direto + index (mais performático),
 * mas é fix REAL pra cross-tenant em query direta sem tocar schema.
 *
 * @see App\Concerns\HasBusinessScope                trait para Models com biz direto
 * @see Modules\Jana\Scopes\ScopeByBusinessViaParent scope aplicado
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
trait BelongsToBusinessViaParent
{
    protected static function bootBelongsToBusinessViaParent(): void
    {
        static::addGlobalScope(new ScopeByBusinessViaParent);
    }
}
