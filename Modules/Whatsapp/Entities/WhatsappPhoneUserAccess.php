<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WhatsappPhoneUserAccess — ACL atendente↔número Whatsapp.
 *
 * Q1 + Q5 do ADR 0117 — atendente fixado num número via ACL própria
 * (não polui Spatie permissions com N permissões/número/business).
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) — global scope `business_id`
 * via trait HasBusinessScope.
 *
 * @property int $id
 * @property int $business_id
 * @property int $whatsapp_business_phone_id
 * @property int $user_id
 */
class WhatsappPhoneUserAccess extends Model
{
    use HasBusinessScope;

    protected $table = 'whatsapp_phone_user_access';

    protected $guarded = ['id'];

    public function phone(): BelongsTo
    {
        return $this->belongsTo(WhatsappBusinessPhone::class, 'whatsapp_business_phone_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\User::class, 'user_id');
    }
}
