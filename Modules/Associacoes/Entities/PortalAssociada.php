<?php

namespace Modules\Associacoes\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `assoc_portal_tokens` — acesso da associada à área dela no app (link + PIN; ADR 0451 D6).
 * Guarda só hashes. A rota pública resolve o token sem sessão de usuário (o escopo de empresa
 * não filtra sem login) e daí em diante filtra pelo `business_id` assinado no token (ADR 0093).
 *
 * @property int $id
 * @property int $business_id
 * @property int $associada_id
 * @property string $token_hash
 * @property string $pin_hash
 * @property int $tentativas
 * @property \Illuminate\Support\Carbon|null $travado_ate
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $revogado_em
 */
class PortalAssociada extends Model
{
    use HasBusinessScope;

    protected $table = 'assoc_portal_tokens';

    protected $guarded = ['id'];

    protected $hidden = ['token_hash', 'pin_hash'];

    protected $casts = [
        'tentativas' => 'integer',
        'travado_ate' => 'datetime',
        'expires_at' => 'datetime',
        'revogado_em' => 'datetime',
    ];

    public function associada(): BelongsTo
    {
        return $this->belongsTo(Associada::class, 'associada_id');
    }
}
