<?php

namespace Modules\Producao\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * `pcp_aprovacoes_arte` — pedido de aprovação da prova pelo cliente (UC-FLX-02 · US-COMVIS-NEW-004).
 * Guarda só o sha256 do token. Escopo por empresa (ADR 0093).
 *
 * @property int $id
 * @property int $business_id
 * @property int $ordem_id
 * @property int $programacao_id
 * @property int $arte_programacao_id
 * @property string $token_hash
 * @property string $arquivo
 * @property \Illuminate\Support\Carbon $expira_em
 * @property ?string $decisao
 * @property ?string $comentario
 * @property ?\Illuminate\Support\Carbon $decidido_em
 * @property int $created_by
 */
class AprovacaoArte extends Model
{
    use HasBusinessScope;

    public const APROVADA = 'aprovada';

    public const RECUSADA = 'recusada';

    protected $table = 'pcp_aprovacoes_arte';

    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    protected $casts = ['expira_em' => 'datetime', 'decidido_em' => 'datetime'];
}
