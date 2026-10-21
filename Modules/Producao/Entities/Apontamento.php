<?php

namespace Modules\Producao\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * `pcp_apontamentos` — fundo do PCP (ADR 0431, thread 02). Escopo por empresa (ADR 0093).
 * APPEND-ONLY (trigger MySQL): nunca update/delete; correção é linha nova.
 *
 * @property int $id
 * @property int $business_id
 * @property string $chave_idempotencia
 * @property string $tipo
 * @property ?int $inicio_id
 * @property ?int $programacao_id
 * @property ?int $motivo_id
 * @property ?string $os_tipo
 * @property ?int $os_id
 * @property int $posto_id
 * @property string $tipo_custo
 * @property string $custo_hora
 * @property ?int $maquina_id
 * @property ?int $user_id
 * @property int $iniciado_por
 * @property \Illuminate\Support\Carbon $momento
 * @property string $origem
 */
class Apontamento extends Model
{
    use HasBusinessScope;

    public const UPDATED_AT = null;

    protected $table = 'pcp_apontamentos';

    protected $guarded = ['id'];

    protected $casts = ['momento' => 'datetime'];
}
