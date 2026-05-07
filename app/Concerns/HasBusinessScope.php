<?php

declare(strict_types=1);

namespace App\Concerns;

use Modules\Jana\Scopes\ScopeByBusiness;

/**
 * Trait HasBusinessScope — multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093).
 *
 * Aplica ScopeByBusiness automático no boot() do Model — equivalente
 * Laravel-idiomático ao addGlobalScope manual. Padrão Anthropic 2026
 * trend report: traits são preferíveis pra cross-cutting concerns.
 *
 * Comportamento (definido em ScopeByBusiness):
 * - Usuário comum vê apenas rows com business_id = session('user.business_id')
 * - Superadmin vê próprio business + rows com business_id NULL (plataforma)
 * - CLI/jobs sem auth → sem filtro (Job DEVE passar $businessId no constructor
 *   e usar ->withoutGlobalScopes()->where('business_id', $this->businessId))
 *
 * Uso canônico:
 *
 *   use App\Concerns\HasBusinessScope;
 *
 *   class Subscription extends Model
 *   {
 *       use HasBusinessScope;
 *       // ...
 *   }
 *
 * Pra escapar deliberadamente do scope (ex: superadmin batch op):
 *
 *   Subscription::withoutGlobalScope(ScopeByBusiness::class)->get();
 *   // Ou: Subscription::withoutGlobalScopes()->where('business_id', $bizId)->get();
 *
 * ⚠️ NUNCA chamar withoutGlobalScopes() sem comentar o porquê — skill
 * `commit-discipline` (Tier A) detecta e alerta.
 *
 * Migrar Model existente: substituir
 *   protected static function boot() {
 *       parent::boot();
 *       static::addGlobalScope(new ScopeByBusiness);
 *   }
 * Por:
 *   use App\Concerns\HasBusinessScope;
 *   ...
 *   class X extends Model {
 *       use HasBusinessScope;
 *   }
 *
 * @see Modules\Jana\Scopes\ScopeByBusiness
 * @see ADR 0093 (Multi-tenant Tier 0 IRREVOGÁVEL)
 * @see ADR 0094 (Constituição v2 — princípio duro #6)
 */
trait HasBusinessScope
{
    /**
     * Boot do trait — chamado automaticamente pelo Eloquent.
     */
    protected static function bootHasBusinessScope(): void
    {
        static::addGlobalScope(new ScopeByBusiness);
    }
}
