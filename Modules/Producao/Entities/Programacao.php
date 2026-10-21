<?php

namespace Modules\Producao\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * `pcp_programacoes` — fundo do PCP (ADR 0431, thread 02). Escopo por empresa (ADR 0093).
 *
 * @property int $id
 * @property int $business_id
 * @property string $os_tipo
 * @property int $os_id
 * @property ?int $posto_id
 * @property ?int $roteiro_etapa_id
 * @property ?int $bloco
 * @property int $trilha
 * @property int $ordem
 * @property ?string $nome
 * @property ?string $junta
 * @property ?int $operacao_id
 * @property ?int $user_id
 * @property string $status
 * @property \Illuminate\Support\Carbon $inicio_previsto
 * @property \Illuminate\Support\Carbon $fim_previsto
 */
class Programacao extends Model
{
    use HasBusinessScope;

    protected $table = 'pcp_programacoes';

    protected $guarded = ['id'];

    protected $casts = ['inicio_previsto' => 'datetime', 'fim_previsto' => 'datetime'];
}
