<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `fisio_portal_tokens` — acesso do paciente ao portal (link + PIN). Guarda SÓ hashes; o token
 * e o PIN em claro existem apenas na mensagem enviada. Escopo por empresa (ADR 0093).
 * O portal público resolve o token sem sessão de usuário (escopo não filtra sem auth) e
 * daí em diante filtra pelo `business_id` do próprio token (US-FISIO-007).
 *
 * @property int $id
 * @property int $business_id
 * @property int $patient_id
 * @property string $token_hash
 * @property string $pin_hash
 * @property int $attempts
 * @property ?\Illuminate\Support\Carbon $locked_until
 * @property \Illuminate\Support\Carbon $expires_at
 * @property ?\Illuminate\Support\Carbon $revoked_at
 */
class PortalToken extends Model
{
    use HasBusinessScope;

    protected $table = 'fisio_portal_tokens';

    protected $guarded = ['id'];

    protected $hidden = ['token_hash', 'pin_hash'];

    protected $casts = [
        'attempts' => 'integer',
        'locked_until' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}