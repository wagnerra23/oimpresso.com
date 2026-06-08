<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Jana\Scopes\ScopeByBusiness;

/**
 * WhatsappContactBotOverride — override per-contato do `bot_enabled` global
 * (US-WA-077, ADR 0142 §3c).
 *
 * Atendente executa `/config bot=off` em nota interna → cria/atualiza row
 * aqui. Engine de bot (DispatchToJanaBot) chama {@see self::resolvedFor()}
 * que retorna o override OU faz fallback pro flag do canal/business.
 *
 * UNIQUE (business_id, contact_id) — sempre 1 só override por contato.
 * Toggle múltiplo via `/config` faz updateOrCreate (idempotente).
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — global scope `business_id`
 * via trait HasBusinessScope.
 *
 * @property int $id
 * @property int $business_id
 * @property int $contact_id
 * @property bool $bot_enabled
 * @property int $set_by_user_id
 * @property ?string $reason
 * @property \Carbon\Carbon $set_at
 *
 * @see memory/decisions/0142-notas-internas-sinal-treino-jana.md §3c
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-077
 */
class WhatsappContactBotOverride extends Model
{
    use HasBusinessScope;

    protected $table = 'whatsapp_contact_bot_overrides';

    protected $fillable = [
        'business_id',
        'contact_id',
        'bot_enabled',
        'set_by_user_id',
        'reason',
        'set_at',
    ];

    protected $casts = [
        'bot_enabled' => 'boolean',
        'set_at' => 'datetime',
    ];

    /**
     * Resolve `bot_enabled` efetivo pra um contato: override > fallback.
     *
     * Pesquisa override per-contato (mesmo business). Se existir, retorna
     * o flag. Senão, retorna `$fallback` (tipicamente `$phone->bot_enabled`
     * OU `$businessConfig->bot_enabled` do caller).
     *
     * Chamado fora de request HTTP (Job/listener): usa `withoutGlobalScope`
     * + filtro explícito `business_id` pra preservar isolamento Tier 0
     * sem depender de session. Padrão DispatchToJanaBot (Tier 0 ADR 0093).
     *
     * @param  int  $businessId  Tenant explícito (NUNCA confiar em session em listener/Job)
     * @param  int  $contactId   FK contacts UltimatePOS
     * @param  bool $fallback    Valor a usar quando NÃO há override
     */
    public static function resolvedFor(int $businessId, int $contactId, bool $fallback = true): bool
    {
        // SUPERADMIN: chamado fora de session HTTP (listener bot engine) —
        // global scope quebraria fallback Tier 0. Filtro explícito where
        // business_id preserva isolamento (ADR 0093).
        $override = static::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where('contact_id', $contactId)
            ->first();

        if ($override === null) {
            return $fallback;
        }

        return (bool) $override->bot_enabled;
    }

    /**
     * Helper: existe override pra (business, contact)?
     *
     * Usado pela UI pra renderizar badge "🤖 bot desligado" no header da
     * conversa quando override = off.
     */
    public static function existsFor(int $businessId, int $contactId): bool
    {
        return static::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where('contact_id', $contactId)
            ->exists();
    }

    public function setBy(): BelongsTo
    {
        return $this->belongsTo(\App\User::class, 'set_by_user_id');
    }
}
