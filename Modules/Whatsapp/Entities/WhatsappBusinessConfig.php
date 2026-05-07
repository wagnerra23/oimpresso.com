<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * WhatsappBusinessConfig — 1 row por business com Whatsapp ativo.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — global scope `business_id`
 * via trait HasBusinessScope.
 *
 * Tokens (meta_*, zapi_*, baileys_*) são cifrados em DB via `encrypted` cast Laravel.
 *
 * Decisão mãe: ADR 0096 (Z-API default + Meta Cloud fallback obrigatório;
 * Baileys autorizado Sprint 3; Evolution PROIBIDO permanente).
 *
 * @property int $id
 * @property int $business_id
 * @property string $business_uuid
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
 * @property ?string $baileys_daemon_url
 * @property ?string $baileys_api_key
 * @property ?\Carbon\CarbonImmutable $lgpd_acknowledged_at
 * @property ?int $lgpd_acknowledged_by_user_id
 * @property bool $bot_enabled
 * @property string $driver_health
 * @property int $driver_health_consecutive_failures
 */
class WhatsappBusinessConfig extends Model
{
    use HasBusinessScope;

    protected $table = 'whatsapp_business_configs';

    protected $guarded = ['id'];

    /**
     * Casts — tokens e secrets cifrados via Laravel `encrypted` cast.
     * Ler/gravar parece string normal; em DB fica encrypted blob.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'meta_access_token' => 'encrypted',
        'meta_app_secret' => 'encrypted',
        'zapi_instance_token' => 'encrypted',
        'zapi_client_token' => 'encrypted',
        'baileys_api_key' => 'encrypted',
        'lgpd_acknowledged_at' => 'immutable_datetime',
        'last_health_check_at' => 'immutable_datetime',
        'bot_enabled' => 'boolean',
        'driver_health_consecutive_failures' => 'integer',
    ];

    /**
     * Driver efetivo — resolve fallback automático em runtime.
     *
     * Se primário ficou degraded/disconnected/banned, retorna fallback_driver.
     */
    public function effectiveDriver(): string
    {
        return match (true) {
            $this->driver_health === 'healthy' => $this->driver,
            $this->driver_health === 'never_checked' => $this->driver,
            default => $this->fallback_driver,
        };
    }

    /**
     * Esse driver não-oficial precisa de fallback Meta Cloud configurado?
     */
    public function requiresFallback(): bool
    {
        return in_array($this->driver, config('whatsapp.fallback.mandatory_for_drivers', []), true);
    }

    /**
     * Meta Cloud está cadastrado? (gating: driver=zapi/baileys exige Meta como fallback)
     */
    public function hasMetaCloudConfigured(): bool
    {
        return ! empty($this->meta_phone_number_id)
            && ! empty($this->meta_access_token)
            && ! empty($this->meta_app_secret);
    }

    public function business(): BelongsTo
    {
        // Business é Model core UltimatePOS — sem relação reversa per multi-tenant
        return $this->belongsTo(\App\Business::class, 'business_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(WhatsappConversation::class, 'business_id', 'business_id');
    }
}
