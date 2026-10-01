<?php

namespace Modules\Ponto\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Aparelho que recebe o lembrete de bater ponto (ADR 0422).
 *
 * Multi-tenant Tier 0 ([ADR 0093]): HasBusinessScope aplica o global scope por business_id.
 * Fora de sessão (comando do scheduler, Job) o scope não filtra — quem consulta passa
 * `$businessId` e filtra explicitamente.
 *
 * @property int $id
 * @property int $business_id
 * @property int $user_id
 * @property string $token
 * @property string $plataforma
 * @property bool $ativo
 * @property \Illuminate\Support\Carbon|null $ultimo_uso_at
 */
class PushDispositivo extends Model
{
    use HasBusinessScope;

    public const PLATAFORMAS = ['android', 'ios'];

    protected $table = 'ponto_push_dispositivos';

    protected $fillable = [
        'business_id',
        'user_id',
        'token',
        'plataforma',
        'ativo',
        'ultimo_uso_at',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'ultimo_uso_at' => 'datetime',
    ];
}
