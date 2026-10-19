<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `fisio_portal_devices` — aparelho lembrado no portal do paciente por 30 dias (US-FISIO-007b,
 * etapa 02 T-07a). Guarda SÓ o hash do segredo do cookie; amarrado à linha do token. Escopo por
 * empresa (ADR 0093); o portal público filtra pelo business do token.
 *
 * @property int $id
 * @property int $business_id
 * @property int $token_id
 * @property string $device_hash
 * @property \Illuminate\Support\Carbon $remembered_until
 * @property ?\Illuminate\Support\Carbon $revoked_at
 */
class PortalDevice extends Model
{
    use HasBusinessScope;

    protected $table = 'fisio_portal_devices';

    protected $guarded = ['id'];

    protected $hidden = ['device_hash'];

    protected $casts = [
        'remembered_until' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /** @return BelongsTo<PortalToken, $this> */
    public function token(): BelongsTo
    {
        return $this->belongsTo(PortalToken::class, 'token_id');
    }
}
