<?php

declare(strict_types=1);

namespace Modules\Jana\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Ledger append-only das ações da Jana aprovadas por gente (HITL, `/ia`).
 *
 * Append-only DE PROPÓSITO: o que pode mudar é `status` (aprovada → executada /
 * recusada). `previa` e `contexto` são o recibo do que foi EXIBIDO no momento do
 * OK — reescrevê-los seria falsificar o que a pessoa aprovou.
 *
 * `HasBusinessScope` e não `addGlobalScope` manual: é o canon do projeto desde a
 * ADR 0093 (o próprio docblock do trait manda migrar o padrão antigo pra ele), e
 * é o que `Meta`/`Conversa`/as entidades Mcp já usam.
 *
 * ⚠️ O scope lê `session('user.business_id')` — logo, em CLI/queue ele NÃO filtra.
 * Job que um dia leia esta tabela recebe `$businessId` no constructor e usa
 * `withoutGlobalScopes()->where('business_id', $this->businessId)` (ADR 0093).
 *
 * @see Modules\Jana\Services\AcaoHitlService
 */
class AcaoAprovacao extends Model
{
    use HasBusinessScope;

    protected $table = 'jana_acao_aprovacoes';

    protected $fillable = [
        'business_id',
        'user_id',
        'acao_key',
        'status',
        'previa',
        'contexto',
        'aprovada_em',
    ];

    protected $casts = [
        'contexto'    => 'array',
        'aprovada_em' => 'datetime',
    ];
}
