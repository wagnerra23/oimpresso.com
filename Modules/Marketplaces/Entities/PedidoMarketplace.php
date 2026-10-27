<?php

namespace Modules\Marketplaces\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pedido recebido de um marketplace (mkt_orders), ponte para a venda do ERP (`transaction_id`,
 * preenchido pela US-MKT-007). Único por (empresa, marketplace, id externo): webhook repetido
 * não cria segundo pedido. O comprador fica só em hash (`buyer_external`).
 *
 * @property int $id
 * @property int $business_id
 * @property int $account_id
 * @property int $marketplace_id
 * @property string $order_id_external
 * @property int|null $transaction_id
 * @property string|null $status_external
 * @property array|null $buyer_external
 * @property string|null $total_amount
 * @property string|null $total_fee
 * @property string|null $shipping_cost
 * @property string|null $shipping_type
 * @property string|null $shipping_paid_by
 * @property string|null $payment_method
 * @property \Illuminate\Support\Carbon|null $date_created_external
 * @property \Illuminate\Support\Carbon|null $date_closed_external
 * @property \Illuminate\Support\Carbon|null $money_release_date
 * @property array|null $raw_payload
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read ContaVendedor|null $conta
 */
class PedidoMarketplace extends Model
{
    use HasBusinessScope;

    protected $table = 'mkt_orders';

    protected $fillable = [
        'business_id', 'account_id', 'marketplace_id', 'order_id_external', 'transaction_id',
        'status_external', 'buyer_external', 'total_amount', 'total_fee', 'shipping_cost',
        'shipping_type', 'shipping_paid_by', 'payment_method', 'date_created_external',
        'date_closed_external', 'money_release_date', 'raw_payload', 'processed_at',
    ];

    protected $casts = [
        'buyer_external' => 'array',
        'raw_payload' => 'array',
        'total_amount' => 'decimal:4',
        'total_fee' => 'decimal:4',
        'shipping_cost' => 'decimal:4',
        'date_created_external' => 'datetime',
        'date_closed_external' => 'datetime',
        'money_release_date' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /** @return BelongsTo<ContaVendedor, $this> */
    public function conta(): BelongsTo
    {
        return $this->belongsTo(ContaVendedor::class, 'account_id');
    }
}
