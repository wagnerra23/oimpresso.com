<?php

namespace Modules\Producao\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * `pcp_refugos` — peças refugadas numa etapa da OS por item, com o motivo (UC-FLX-16 · US-PCP-026).
 * Escopo por empresa (ADR 0093).
 *
 * @property int $id
 * @property int $business_id
 * @property string $chave
 * @property int $ordem_id
 * @property int $programacao_id
 * @property int $motivo_id
 * @property string $motivo
 * @property string $quantidade
 * @property int|null $retrabalho_programacao_id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon $momento
 */
class Refugo extends Model
{
    use HasBusinessScope;

    protected $table = 'pcp_refugos';

    protected $guarded = ['id'];

    protected $casts = ['momento' => 'datetime'];
}
