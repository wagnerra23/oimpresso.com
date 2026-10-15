<?php

namespace Modules\Producao\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * `pcp_colaborador_custos` — custo-hora da pessoa (E03); só aparece com `producao.desempenho.ver`. Cadastros do PCP (ADR 0431, thread 03). Escopo por empresa (ADR 0093).
 *
 * @property int $id
 * @property int $business_id
 * @property int $user_id
 * @property string $custo_hora
 */
class ColaboradorCusto extends Model
{
    use HasBusinessScope;

    protected $table = 'pcp_colaborador_custos';

    protected $guarded = ['id'];
}
