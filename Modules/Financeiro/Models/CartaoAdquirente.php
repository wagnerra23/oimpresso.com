<?php

namespace Modules\Financeiro\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Financeiro\Models\Concerns\BusinessScope;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** Maquininha da empresa: taxa e prazo de repasse (US-FIN-081). */
class CartaoAdquirente extends Model
{
    use SoftDeletes, BusinessScope, LogsActivity;

    /** Taxa e prazo mudam o líquido previsto: quem mudou precisa de trilha. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nome', 'descricao_extrato', 'conta_bancaria_id', 'taxa_debito', 'prazo_debito_dias',
                'taxa_credito', 'prazo_credito_dias', 'padrao', 'ativo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('financeiro.cartao_adquirente');
    }

    protected $table = 'fin_cartao_adquirentes';

    protected $fillable = [
        'business_id', 'nome', 'descricao_extrato', 'conta_bancaria_id', 'taxa_debito', 'prazo_debito_dias',
        'taxa_credito', 'prazo_credito_dias', 'padrao', 'ativo', 'created_by',
    ];

    protected $casts = [
        'padrao' => 'boolean',
        'ativo'  => 'boolean',
    ];
}
