<?php

namespace Modules\Producao\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * `pcp_apontamentos` — fundo do PCP (ADR 0431, thread 02). Escopo por empresa (ADR 0093).
 * APPEND-ONLY (trigger MySQL): nunca update/delete; correção é linha nova.
 */
class Apontamento extends Model
{
    use HasBusinessScope;

    public const UPDATED_AT = null;

    protected $table = 'pcp_apontamentos';

    protected $guarded = ['id'];

    protected $casts = ['momento' => 'datetime'];
}
