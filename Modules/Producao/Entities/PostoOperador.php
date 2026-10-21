<?php

namespace Modules\Producao\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * `pcp_posto_operadores` — quem trabalha em cada posto (UC-FLX-08 · US-PCP-030). Escopo por empresa
 * (ADR 0093). A regra mora em `Modules\Producao\Services\OperadoresDoPosto`.
 *
 * @property int $id
 * @property int $business_id
 * @property int $posto_id
 * @property int $user_id
 * @property ?int $created_by
 */
class PostoOperador extends Model
{
    use HasBusinessScope;

    protected $table = 'pcp_posto_operadores';

    protected $guarded = ['id'];
}
