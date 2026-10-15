<?php

namespace Modules\Producao\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * `pcp_postos` — fundo do PCP (ADR 0431, thread 02). Escopo por empresa (ADR 0093).
 */
class Posto extends Model
{
    use HasBusinessScope;

    protected $table = 'pcp_postos';

    protected $guarded = ['id'];

    protected $casts = ['veiculo' => 'boolean', 'ativo' => 'boolean'];

    public const MAQUINA = 'maquina';

    public const PESSOA = 'pessoa';
}
