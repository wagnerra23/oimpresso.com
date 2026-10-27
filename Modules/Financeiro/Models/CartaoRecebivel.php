<?php

namespace Modules\Financeiro\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Financeiro\Models\Concerns\BusinessScope;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * O que a adquirente deve pagar por um pagamento de venda no cartão (US-FIN-083).
 *
 * @property int $id
 * @property int $business_id
 * @property int $adquirente_id
 * @property int|null $transaction_id
 * @property int $transaction_payment_id
 * @property string $modalidade
 * @property string $data_venda
 * @property string $data_prevista
 * @property string $valor_bruto
 * @property string $taxa_percentual
 * @property string $valor_taxa
 * @property string $valor_liquido
 * @property string $status
 * @property string|null $extrato_origem
 * @property int|null $extrato_linha_id
 * @property string|null $recebido_em
 */
class CartaoRecebivel extends Model
{
    use BusinessScope, LogsActivity;

    public const PREVISTO = 'previsto';
    public const RECEBIDO = 'recebido';
    public const CANCELADO = 'cancelado';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['valor_bruto', 'valor_taxa', 'valor_liquido', 'data_prevista', 'status', 'extrato_linha_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('financeiro.cartao_recebivel');
    }

    protected $table = 'fin_cartao_recebiveis';

    protected $fillable = [
        'business_id', 'adquirente_id', 'transaction_id', 'transaction_payment_id', 'modalidade', 'data_venda',
        'data_prevista', 'valor_bruto', 'taxa_percentual', 'valor_taxa', 'valor_liquido', 'status',
        'extrato_origem', 'extrato_linha_id', 'recebido_em',
    ];
}
