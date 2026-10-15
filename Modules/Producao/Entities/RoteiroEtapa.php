<?php

namespace Modules\Producao\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * `pcp_roteiro_etapas` — etapa do roteiro; trilha 0 = sequência, 1 e 2 = bloco paralelo (UC-PCV-13). Cadastros do PCP (ADR 0431, thread 03). Escopo por empresa (ADR 0093).
 *
 * @property int $id
 * @property int $business_id
 * @property int $roteiro_id
 * @property int $bloco
 * @property int $trilha
 * @property int $ordem
 * @property string $nome
 * @property ?int $posto_id
 * @property ?string $horas
 * @property ?string $opcional_por
 * @property ?string $junta
 */
class RoteiroEtapa extends Model
{
    use HasBusinessScope;

    protected $table = 'pcp_roteiro_etapas';

    protected $guarded = ['id'];
}
