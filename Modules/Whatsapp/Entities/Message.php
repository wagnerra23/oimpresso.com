<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Message — entidade canônica omnichannel (ADR 0135), append-only.
 *
 * Substitui long-term `WhatsappMessage`. Mensagens são imutáveis — só
 * `status` e `failed_reason` podem ser atualizados (delivery flow).
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — global scope `business_id`
 * via trait `HasBusinessScope`.
 *
 * Enforcement append-only: PR futuro adiciona Model observer + trigger
 * MySQL. Por enquanto convenção cultural + code review.
 *
 * @property int $id
 * @property int $business_id
 * @property int $conversation_id
 * @property string $direction
 * @property string $provider
 * @property ?string $provider_message_id
 * @property string $type
 * @property ?string $template_name
 * @property ?string $subject
 * @property ?string $body
 * @property ?array $payload
 * @property string $status
 * @property ?string $failed_reason
 * @property ?int $sender_user_id
 * @property ?string $sender_kind
 * @property ?int $cost_centavos
 * @property bool $is_internal_note
 * @property ?string $media_url
 * @property ?string $media_mime
 * @property ?int $media_size_bytes
 * @property ?int $media_duration_s
 * @property ?string $media_thumbnail_url
 * @property ?string $media_transcription
 * @property ?string $media_filename
 */
class Message extends Model
{
    use HasBusinessScope;

    protected $table = 'messages';

    public const UPDATED_AT = 'updated_at';

    public const DIRECTION_INBOUND = 'inbound';
    public const DIRECTION_OUTBOUND = 'outbound';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ = 'read';
    public const STATUS_FAILED = 'failed';
    public const STATUS_RECEIVED = 'received';

    public const STATUSES = [
        self::STATUS_QUEUED,
        self::STATUS_SENT,
        self::STATUS_DELIVERED,
        self::STATUS_READ,
        self::STATUS_FAILED,
        self::STATUS_RECEIVED,
    ];

    protected $fillable = [
        'business_id', 'conversation_id',
        'direction', 'provider', 'provider_message_id',
        'type', 'template_name', 'subject', 'body', 'payload',
        'status', 'failed_reason',
        'sender_user_id', 'sender_kind',
        'cost_centavos',
        'is_internal_note',
        // US-WA-072 — mídia
        'media_url', 'media_mime', 'media_size_bytes',
        'media_duration_s', 'media_thumbnail_url',
        'media_transcription', 'media_filename',
    ];

    protected $casts = [
        'payload' => 'array',
        'cost_centavos' => 'integer',
        'is_internal_note' => 'boolean',
        'media_size_bytes' => 'integer',
        'media_duration_s' => 'integer',
    ];

    /**
     * US-WA-072 — MIME whitelist seguro pra upload outbound.
     *
     * Bloqueia explicitamente:
     *   - image/svg+xml  → XSS vector (script tags em SVG executam ao renderizar)
     *   - text/html      → XSS direto
     *   - application/x-* (executáveis Windows/macOS/Linux)
     *
     * Whitelist segue limites Meta Cloud + Z-API + Baileys (interseção).
     */
    public const MEDIA_MIME_WHITELIST = [
        // Imagens (raster only — SVG é XSS)
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        // Áudio
        'audio/ogg',
        'audio/opus',
        'audio/mpeg',
        'audio/mp4',
        'audio/m4a',
        'audio/x-m4a',
        'audio/wav',
        'audio/x-wav',
        // Vídeo
        'video/mp4',
        'video/mpeg',
        'video/3gpp',
        // Documentos
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'text/csv',
    ];

    /** Max upload size — Meta Cloud cap (Baileys aceita 100MB mas alinhamos no menor). */
    public const MEDIA_MAX_SIZE_BYTES = 16 * 1024 * 1024;

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Atendente (humano) que enviou a mensagem outbound via web UI.
     *
     * Null quando:
     * - inbound (cliente externo)
     * - outbound do próprio chip (Wagner manda do celular — fora do oimpresso)
     * - outbound do Bot (sender_kind='bot')
     *
     * Usado em US-WA-077 pra renderizar o nome do atendente acima da bubble
     * na UI do Inbox (identifica QUAL agente do time enviou cada msg quando
     * vários compartilham o mesmo chip).
     */
    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(\App\User::class, 'sender_user_id');
    }
}
