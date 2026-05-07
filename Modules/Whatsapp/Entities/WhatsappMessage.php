<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WhatsappMessage — append-only.
 *
 * Cada mensagem (in/out) = 1 row imutável. Updates permitidos só em
 * `status`/`failed_reason`/`updated_at` (status delivery flow).
 *
 * Append-only enforcement: observer `saving` em Lote 2c bloqueia UPDATE
 * em colunas-chave (body, direction, provider, provider_message_id,
 * conversation_id). Padrão Ponto Marcacoes.
 *
 * Multi-tenant Tier 0 (ADR 0093) via HasBusinessScope.
 *
 * @property int $id
 * @property int $business_id
 * @property int $conversation_id
 * @property string $direction
 * @property string $provider
 * @property ?string $provider_message_id
 * @property string $type
 * @property ?string $template_name
 * @property ?string $body
 * @property ?array $payload
 * @property string $status
 * @property ?string $failed_reason
 * @property ?int $sender_user_id
 * @property ?string $sender_kind
 * @property ?int $cost_centavos
 * @property \Carbon\CarbonImmutable $created_at
 */
class WhatsappMessage extends Model
{
    use HasBusinessScope;

    protected $table = 'whatsapp_messages';

    protected $guarded = ['id'];

    /**
     * Colunas que JAMAIS podem ser UPDATEd após INSERT (append-only).
     *
     * Observer Lote 2c valida saving event e dispara exception se alguma
     * dessas colunas mudar entre dirty/original. Padrão Ponto Marcacoes
     * (memory/proibicoes.md — append-only por força de lei).
     *
     * @var array<int, string>
     */
    public const IMMUTABLE_COLUMNS = [
        'business_id',
        'conversation_id',
        'direction',
        'provider',
        'provider_message_id',
        'body',
        'payload',
        'sender_user_id',
        'sender_kind',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
        'cost_centavos' => 'integer',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsappConversation::class, 'conversation_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(\App\Business::class, 'business_id');
    }

    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(\App\User::class, 'sender_user_id');
    }

    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }

    public function isOutbound(): bool
    {
        return $this->direction === 'outbound';
    }
}
