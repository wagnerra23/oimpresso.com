<?php

namespace Modules\Producao\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * `pcp_operacoes` — fundo do PCP (ADR 0431, thread 02). Escopo por empresa (ADR 0093).
 *
 * @property int $id
 * @property int $business_id
 * @property int $posto_id
 * @property string $codigo
 * @property string $nome
 * @property int $tempo_padrao_minutos
 */
class Operacao extends Model
{
    use HasBusinessScope;

    protected $table = 'pcp_operacoes';

    protected $guarded = ['id'];
}
