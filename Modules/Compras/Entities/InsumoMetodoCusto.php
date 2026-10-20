<?php

declare(strict_types=1);

namespace Modules\Compras\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * `compras_insumo_metodo_custo` — o método de custo próprio de um insumo, usado quando a
 * regra da empresa é `por_insumo` (US-COM-024 · UC-FLX-38). Escopo por empresa (ADR 0093).
 *
 * @property int $id
 * @property int $business_id
 * @property int $variation_id
 * @property string $metodo `medio` | `ultimo`
 */
class InsumoMetodoCusto extends Model
{
    use HasBusinessScope;

    protected $table = 'compras_insumo_metodo_custo';

    protected $guarded = ['id'];

    public const METODOS = ['medio', 'ultimo'];
}
