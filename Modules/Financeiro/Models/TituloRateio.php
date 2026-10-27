<?php

namespace Modules\Financeiro\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Financeiro\Models\Concerns\BusinessScope;

/**
 * Uma fatia do título num centro de custo (US-FIN-075). Gravada só pelo RateioService.
 *
 * @property int $id
 * @property int $business_id
 * @property int $titulo_id
 * @property int $centro_custo_id
 * @property string $percentual
 * @property string $valor
 */
class TituloRateio extends Model
{
    use BusinessScope;

    protected $table = 'fin_titulo_rateios';

    protected $fillable = ['business_id', 'titulo_id', 'centro_custo_id', 'percentual', 'valor'];

    public function titulo(): BelongsTo
    {
        return $this->belongsTo(Titulo::class, 'titulo_id');
    }

    public function centroCusto(): BelongsTo
    {
        return $this->belongsTo(CentroCusto::class, 'centro_custo_id');
    }
}
