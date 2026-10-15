<?php

namespace Modules\Producao\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * `pcp_programacoes` — fundo do PCP (ADR 0431, thread 02). Escopo por empresa (ADR 0093).
 */
class Programacao extends Model
{
    use HasBusinessScope;

    protected $table = 'pcp_programacoes';

    protected $guarded = ['id'];

    protected $casts = ['inicio_previsto' => 'datetime', 'fim_previsto' => 'datetime'];
}
