<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * WhatsappConversation — 1 conversa = par (business + customer_phone).
 *
 * Multi-tenant Tier 0 (ADR 0093) via HasBusinessScope.
 *
 * Janela 24h Meta tracked em `last_inbound_at` — driver MetaCloud só
 * aceita freeform se now() - last_inbound_at < 24h.
 *
 * @property int $id
 * @property int $business_id
 * @property ?int $whatsapp_business_phone_id
 * @property ?int $contact_id
 * @property string $customer_phone
 * @property string $status
 * @property ?int $assigned_user_id
 * @property bool $bot_handling
 * @property ?\Carbon\CarbonImmutable $last_inbound_at
 * @property ?\Carbon\CarbonImmutable $last_outbound_at
 * @property ?\Carbon\CarbonImmutable $last_message_at
 * @property int $unread_count
 * @property ?string $lid          PR1 — WhatsApp LID anonymized account-level (sem sufixo `@lid`)
 * @property ?string $phone_e164   PR1 — Telefone real `+E.164` quando resolvido
 * @property ?string $bsuid        PR1 — Cloud API `user_id` Meta-oficial
 */
class WhatsappConversation extends Model
{
    use HasBusinessScope;

    protected $table = 'whatsapp_conversations';

    protected $guarded = ['id'];

    protected $casts = [
        'last_inbound_at' => 'immutable_datetime',
        'last_outbound_at' => 'immutable_datetime',
        'last_message_at' => 'immutable_datetime',
        'bot_handling' => 'boolean',
        'unread_count' => 'integer',
    ];

    /**
     * Janela 24h Meta ainda aberta?
     *
     * MetaCloud só permite freeform sem template HSM se window aberta.
     * Z-API/Baileys ignoram essa regra (sempre freeform).
     */
    public function isWithinMeta24hWindow(): bool
    {
        if ($this->last_inbound_at === null) {
            return false;
        }

        return $this->last_inbound_at->diffInHours(now()) < 24;
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(\App\Business::class, 'business_id');
    }

    /**
     * Número Whatsapp deste business que dono desta conversa (ADR 0117).
     * Nullable até data migration rodar; após PR 5 vira NOT NULL.
     */
    public function whatsappBusinessPhone(): BelongsTo
    {
        return $this->belongsTo(WhatsappBusinessPhone::class, 'whatsapp_business_phone_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(\App\Contact::class, 'contact_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(\App\User::class, 'assigned_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class, 'conversation_id');
    }
}
