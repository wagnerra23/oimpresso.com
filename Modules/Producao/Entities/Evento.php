<?php

namespace Modules\Producao\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * `pcp_eventos` — fundo do PCP (ADR 0431, thread 02). Escopo por empresa (ADR 0093).
 * APPEND-ONLY (trigger MySQL): nunca update/delete; correção é linha nova.
 *
 * @property int $id
 * @property int $business_id
 * @property string $tipo
 * @property ?string $os_tipo
 * @property ?int $os_id
 * @property ?array $dados
 */
class Evento extends Model
{
    use HasBusinessScope;

    public const UPDATED_AT = null;

    protected $table = 'pcp_eventos';

    protected $guarded = ['id'];

    protected $casts = ['dados' => 'array'];
}
