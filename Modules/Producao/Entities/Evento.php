<?php

namespace Modules\Producao\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * `pcp_eventos` — fundo do PCP (ADR 0431, thread 02). Escopo por empresa (ADR 0093).
 * APPEND-ONLY (trigger MySQL): nunca update/delete; correção é linha nova.
 */
class Evento extends Model
{
    use HasBusinessScope;

    public const UPDATED_AT = null;

    protected $table = 'pcp_eventos';

    protected $guarded = ['id'];

    protected $casts = ['dados' => 'array'];
}
