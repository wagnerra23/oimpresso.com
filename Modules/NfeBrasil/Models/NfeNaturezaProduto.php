<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Natureza fiscal de um produto — ICMS (mercadoria) ou ISS (serviço) — thread 29 (R-NFE-034).
 *
 * Produto sem linha aqui é mercadoria, como hoje. `pendente` = alguém marcou o produto como caso
 * a decidir; emite pelo padrão da UF até o contador decidir.
 *
 * @property int $id
 * @property int $business_id
 * @property int $product_id
 * @property string $natureza
 * @property string|null $codigo_servico
 * @property string|null $fonte
 * @property int|null $decidido_por
 * @property \Illuminate\Support\Carbon|null $decidido_em
 */
class NfeNaturezaProduto extends Model
{
    use HasBusinessScope; // ADR 0093 — multi-tenant Tier 0

    public const MERCADORIA = 'mercadoria';
    public const SERVICO = 'servico';
    public const PENDENTE = 'pendente';

    protected $table = 'nfe_naturezas_produto';

    protected $fillable = [
        'business_id', 'product_id', 'natureza', 'codigo_servico', 'fonte', 'decidido_por', 'decidido_em',
    ];

    protected $casts = [
        'business_id'  => 'integer',
        'product_id'   => 'integer',
        'decidido_por' => 'integer',
        'decidido_em'  => 'datetime',
    ];
}
