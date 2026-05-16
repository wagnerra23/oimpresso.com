<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * ChannelUserAccess — ACL atendente↔canal omnichannel (US-WA-068, ADR 0135).
 *
 * Soft revoke via `revoked_at` (NULL = ativo). Re-grant após revoke é
 * permitido — UNIQUE composto (channel_id, user_id, revoked_at) deixa N
 * rows históricas coexistirem.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — global scope `business_id`
 * via trait HasBusinessScope.
 *
 * @property int $id
 * @property int $business_id
 * @property int $channel_id
 * @property int $user_id
 * @property int $granted_by_user_id
 * @property \Carbon\Carbon $granted_at
 * @property ?\Carbon\Carbon $revoked_at
 * @property ?int $revoked_by_user_id
 */
class ChannelUserAccess extends Model
{
    use HasBusinessScope;
    use LogsActivity;

    protected $table = 'channel_user_access';

    /**
     * Wave P — auditoria ACL atendente↔canal (LGPD compliance).
     *
     * Logga revoke/re-grant via `revoked_at` (NULL=ativo, datetime=revogado) e
     * `revoked_by_user_id`. Schema real do US-WA-068 NÃO tem `is_active` nem
     * `permission_level` (acesso é binário via revoked_at). log_name dedicado.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('channel_user_access')
            ->logOnly(['revoked_at', 'revoked_by_user_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'business_id',
        'channel_id',
        'user_id',
        'granted_by_user_id',
        'granted_at',
        'revoked_at',
        'revoked_by_user_id',
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\User::class, 'user_id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(\App\User::class, 'granted_by_user_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(\App\User::class, 'revoked_by_user_id');
    }

    /**
     * Scope local — apenas grants ativos (revoked_at IS NULL).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }
}
