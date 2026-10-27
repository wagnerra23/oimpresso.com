<?php

namespace Modules\Financeiro\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Financeiro\Models\Concerns\BusinessScope;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Orçado de uma conta num mês (US-FIN-076). Gravado só pelo OrcamentoService.
 *
 * @property int $id
 * @property int $business_id
 * @property string $competencia_mes
 * @property int $plano_conta_id
 * @property int|null $centro_custo_id
 * @property string $valor
 */
class Orcamento extends Model
{
    use BusinessScope, LogsActivity;

    protected $table = 'fin_orcamentos';

    protected $fillable = ['business_id', 'competencia_mes', 'plano_conta_id', 'centro_custo_id', 'valor', 'updated_by'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['competencia_mes', 'plano_conta_id', 'centro_custo_id', 'valor'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('financeiro.orcamento');
    }
}
