<?php

namespace Modules\Financeiro\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Financeiro\Models\Concerns\BusinessScope;

/**
 * De-para origem do Financeiro → código reduzido no sistema contábil (US-FIN-077).
 *
 * @property int $id
 * @property int $business_id
 * @property string $origem_tipo
 * @property int $origem_id
 * @property string $codigo
 */
class ContabilConta extends Model
{
    use BusinessScope;

    public const ORIGENS = ['plano_conta', 'conta_bancaria', 'juros', 'desconto'];

    protected $table = 'fin_contabil_contas';

    protected $fillable = ['business_id', 'origem_tipo', 'origem_id', 'codigo'];

    protected $casts = ['origem_id' => 'integer'];
}
