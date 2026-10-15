<?php

namespace Modules\Producao\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * `pcp_operacoes` — fundo do PCP (ADR 0431, thread 02). Escopo por empresa (ADR 0093).
 */
class Operacao extends Model
{
    use HasBusinessScope;

    protected $table = 'pcp_operacoes';

    protected $guarded = ['id'];
}
