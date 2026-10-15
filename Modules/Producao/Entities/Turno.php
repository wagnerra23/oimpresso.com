<?php

namespace Modules\Producao\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * `pcp_turnos` — fundo do PCP (ADR 0431, thread 02). Escopo por empresa (ADR 0093).
 *
 * @property int $id
 * @property int $business_id
 * @property string $inicio
 * @property string $fim
 */
class Turno extends Model
{
    use HasBusinessScope;

    protected $table = 'pcp_turnos';

    protected $guarded = ['id'];
}
