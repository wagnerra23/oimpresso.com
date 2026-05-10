<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * WhatsappBusinessPhone — N rows por business (1 por número Whatsapp).
 *
 * Substitui `WhatsappBusinessConfig` (1:1 business→config) — ver ADR 0117.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — global scope `business_id`
 * via trait HasBusinessScope.
 *
 * Tokens (meta_*, zapi_*) cifrados em DB via `encrypted` cast Laravel.
 *
 * Roteamento de eventos automáticos via flags `handles_*` (decisão Q2 do
 * Wagner em ADR 0117 — cada número escolhe quais eventos dispara).
 *
 * @property int $id
 * @property int $business_id
 * @property string $phone_uuid
 * @property string $label
 * @property string $driver
 * @property string $fallback_driver
 * @property ?string $display_phone
 * @property ?string $meta_phone_number_id
 * @property ?string $meta_access_token
 * @property ?string $meta_app_secret
 * @property ?string $meta_webhook_verify_token
 * @property ?string $zapi_instance_id
 * @property ?string $zapi_instance_token
 * @property ?string $zapi_client_token
 * @property ?string $baileys_instance_id
 * @property ?string $baileys_phone_e164
 * @property ?string $baileys_verified_name
 * @property ?string $baileys_profile_pic_url
 * @property ?\Carbon\CarbonImmutable $lgpd_acknowledged_at
 * @property ?int $lgpd_acknowledged_by_user_id
 * @property bool $handles_repair_status
 * @property bool $handles_billing
 * @property bool $handles_jana_bot
 * @property bool $handles_outbound_default
 * @property bool $bot_enabled
 * @property string $driver_health
 * @property int $driver_health_consecutive_failures
 */
class WhatsappBusinessPhone extends Model
{
    use HasBusinessScope;

    protected $table = 'whatsapp_business_phones';

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'meta_access_token' => 'encrypted',
        'meta_app_secret' => 'encrypted',
        'zapi_instance_token' => 'encrypted',
        'zapi_client_token' => 'encrypted',
        'lgpd_acknowledged_at' => 'immutable_datetime',
        'last_health_check_at' => 'immutable_datetime',
        'handles_repair_status' => 'boolean',
        'handles_billing' => 'boolean',
        'handles_jana_bot' => 'boolean',
        'handles_outbound_default' => 'boolean',
        'bot_enabled' => 'boolean',
        'driver_health_consecutive_failures' => 'integer',
    ];

    public function effectiveDriver(): string
    {
        return match (true) {
            $this->driver_health === 'healthy' => $this->driver,
            $this->driver_health === 'never_checked' => $this->driver,
            default => $this->fallback_driver,
        };
    }

    public function requiresFallback(): bool
    {
        return in_array($this->driver, config('whatsapp.fallback.mandatory_for_drivers', []), true);
    }

    public function hasMetaCloudConfigured(): bool
    {
        return ! empty($this->meta_phone_number_id)
            && ! empty($this->meta_access_token)
            && ! empty($this->meta_app_secret);
    }

    public function hasBaileysConfigured(): bool
    {
        return ! empty($this->baileys_phone_e164)
            && ! empty($this->baileys_instance_id);
    }

    /**
     * Gera instance_id determinístico pra este phone.
     * Formato: "biz{business_id}-{random6}". Idempotent.
     */
    public function ensureBaileysInstanceId(): string
    {
        if (! empty($this->baileys_instance_id)) {
            return $this->baileys_instance_id;
        }
        $prefix = (string) config('whatsapp.baileys.instance_id_prefix', 'biz');
        $this->baileys_instance_id = $prefix . $this->business_id . '-' . Str::random(6);

        return $this->baileys_instance_id;
    }

    /**
     * Resolve qual phone atende um evento automático para um business.
     *
     * Procura primeiro phone com flag específica `handles_{$event}=true`.
     * Se nenhum, fallback pra phone com `handles_outbound_default=true`.
     * Retorna null se nenhum atende — listener trata como falha silenciosa.
     *
     * Se mais de 1 phone tem o flag específico, retorna primeiro (id ASC) e
     * deixa rastro pra `<EventRoutingSection>` UI alertar admin (PR 3).
     *
     * @param  string  $event  'repair_status' | 'billing' | 'jana_bot'
     */
    public static function resolveForEvent(int $businessId, string $event): ?self
    {
        $flagColumn = 'handles_' . $event;

        $allowed = ['handles_repair_status', 'handles_billing', 'handles_jana_bot'];
        if (! in_array($flagColumn, $allowed, true)) {
            throw new \InvalidArgumentException("Unknown event: {$event}");
        }

        // SUPERADMIN: listener async (event handler) chama com $businessId explícito
        // — session() não funciona em fila, scope global precisa bypass com where() explícito.
        // ADR 0093 §Job assíncrono SEMPRE passa $businessId no constructor.
        $query = static::withoutGlobalScopes()
            ->where('business_id', $businessId);

        return (clone $query)
            ->where($flagColumn, true)
            ->orderBy('id')
            ->first()
            ?? (clone $query)
                ->where('handles_outbound_default', true)
                ->orderBy('id')
                ->first();
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(\App\Business::class, 'business_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(WhatsappConversation::class, 'whatsapp_business_phone_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class, 'whatsapp_business_phone_id');
    }

    public function userAccess(): HasMany
    {
        return $this->hasMany(WhatsappPhoneUserAccess::class, 'whatsapp_business_phone_id');
    }

    /**
     * Scope: phones que um user tem acesso (via whatsapp_phone_user_access).
     * Admin/superadmin com Gate `whatsapp.view-all-phones` ignoram este scope.
     */
    public function scopeAccessibleBy(Builder $query, int $userId): Builder
    {
        return $query->whereIn('id', function ($sub) use ($userId) {
            $sub->select('whatsapp_business_phone_id')
                ->from('whatsapp_phone_user_access')
                ->where('user_id', $userId);
        });
    }
}
