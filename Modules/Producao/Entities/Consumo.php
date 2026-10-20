<?php

namespace Modules\Producao\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * `pcp_consumos` — material apontado numa etapa da OS por item (UC-FLX-37 · US-PCP-021).
 *
 * `custo` é o custo do lote que saiu do estoque, congelado no dia da baixa. A baixa em si é a linha
 * `transaction_sell_line_id` da transação `production_sell` da OS (core UltimatePOS). Escopo por
 * empresa (ADR 0093).
 *
 * @property int $id
 * @property int $business_id
 * @property string $chave
 * @property int $ordem_id
 * @property int $programacao_id
 * @property int $product_id
 * @property int $variation_id
 * @property string $quantidade
 * @property string $quantidade_sem_lote
 * @property string $custo
 * @property int $transaction_id
 * @property int $transaction_sell_line_id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon $momento
 */
class Consumo extends Model
{
    use HasBusinessScope;

    protected $table = 'pcp_consumos';

    protected $guarded = ['id'];

    protected $casts = ['momento' => 'datetime'];
}
