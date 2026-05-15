<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Entities;

use App\Concerns\HasBusinessScope;
use App\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * US-WA-VOZ-001 — Memória persistente do cliente final.
 *
 * Eloquent Model da tabela `customer_memory`. Toda interação WhatsApp
 * agrega aqui: stats, identidade (linkagem Contact CRM), inferências IA
 * (futuro), notas qualitativas, flags operacionais, LGPD consent.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093](../../../decisions/0093-multi-tenant-isolation-tier-0.md))
 * via trait `HasBusinessScope` — toda query passa pelo global scope.
 *
 * Identity: chave de negócio é `(business_id, customer_external_id)` — UNIQUE.
 * `contact_id` é NULL quando cliente nunca foi cadastrado no CRM ou match
 * de telefone não bateu. Pode ser preenchido depois (re-tentativa job daily).
 *
 * @property int $id
 * @property int $business_id
 * @property string $customer_external_id
 * @property ?string $phone_normalized
 * @property ?int $contact_id
 * @property ?string $identity_match_method
 * @property ?float $identity_match_confidence
 * @property ?\Illuminate\Support\Carbon $identity_match_at
 * @property ?string $display_name
 * @property int $n_conversations
 * @property int $n_msgs_inbound
 * @property int $n_msgs_outbound
 * @property ?\Illuminate\Support\Carbon $first_interaction_at
 * @property ?\Illuminate\Support\Carbon $last_interaction_at
 * @property ?array $temas_recorrentes
 * @property ?float $sentimento_score
 * @property ?float $churn_risk_score
 * @property ?array $comunicacao_preferida
 * @property ?string $notas_jana
 * @property ?\Illuminate\Support\Carbon $notas_atualizada_em
 * @property ?array $flags
 * @property ?string $consent_status
 * @property ?\Illuminate\Support\Carbon $erasure_requested_at
 * @property ?\Illuminate\Support\Carbon $last_rebuilt_at
 * @property ?string $rebuilt_via
 */
class CustomerMemory extends Model
{
    use HasBusinessScope;

    protected $table = 'customer_memory';

    /**
     * Métodos canônicos de identity matching (sincronizado com migration
     * + ConversationContactLinker::findMatchesForPhone()).
     */
    public const MATCH_EXACT = 'exact';
    public const MATCH_SUFFIX_8 = 'suffix_8';
    public const MATCH_MANUAL = 'manual';
    public const MATCH_AMBIGUOUS = 'ambiguous_picked_first';
    public const MATCH_UNKNOWN = 'unknown';

    /**
     * Estados do consent LGPD (espelha contacts.whatsapp_consent).
     */
    public const CONSENT_GIVEN = 'given';
    public const CONSENT_WITHDRAWN = 'withdrawn';
    public const CONSENT_UNKNOWN = 'unknown';

    /**
     * Origens de rebuild — útil pra debugging "por que esse stat mudou?".
     */
    public const REBUILT_VIA_BACKFILL = 'backfill';
    public const REBUILT_VIA_CRON_DAILY = 'cron_daily';
    public const REBUILT_VIA_LISTENER = 'listener';
    public const REBUILT_VIA_MANUAL = 'manual';
    public const REBUILT_VIA_WEBHOOK = 'webhook';

    protected $fillable = [
        'business_id',
        'customer_external_id',
        'phone_normalized',
        'contact_id',
        'identity_match_method',
        'identity_match_confidence',
        'identity_match_at',
        'display_name',
        'n_conversations',
        'n_msgs_inbound',
        'n_msgs_outbound',
        'first_interaction_at',
        'last_interaction_at',
        'temas_recorrentes',
        'sentimento_score',
        'churn_risk_score',
        'comunicacao_preferida',
        'notas_jana',
        'notas_atualizada_em',
        'flags',
        'consent_status',
        'erasure_requested_at',
        'last_rebuilt_at',
        'rebuilt_via',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'contact_id' => 'integer',
        'identity_match_confidence' => 'float',
        'identity_match_at' => 'datetime',
        'n_conversations' => 'integer',
        'n_msgs_inbound' => 'integer',
        'n_msgs_outbound' => 'integer',
        'first_interaction_at' => 'datetime',
        'last_interaction_at' => 'datetime',
        'temas_recorrentes' => 'array',
        'sentimento_score' => 'float',
        'churn_risk_score' => 'float',
        'comunicacao_preferida' => 'array',
        'notas_atualizada_em' => 'datetime',
        'flags' => 'array',
        'erasure_requested_at' => 'datetime',
        'last_rebuilt_at' => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    /**
     * Total mensagens (in + out) — derivado.
     */
    public function getNMsgsTotalAttribute(): int
    {
        return (int) $this->n_msgs_inbound + (int) $this->n_msgs_outbound;
    }

    /**
     * Dias desde última interação. Útil pra churn heurística e displays.
     */
    public function daysSinceLastInteraction(): ?int
    {
        if ($this->last_interaction_at === null) {
            return null;
        }
        return (int) $this->last_interaction_at->diffInDays(now());
    }

    /**
     * Verdadeiro se cliente pediu erasure LGPD (Art. 18).
     * Sidebar/UI deve mostrar redacted state e bloquear bot Jana.
     */
    public function isErasureRequested(): bool
    {
        return $this->erasure_requested_at !== null;
    }
}
