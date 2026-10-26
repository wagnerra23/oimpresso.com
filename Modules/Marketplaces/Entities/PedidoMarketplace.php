<?php

namespace Modules\Marketplaces\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pedido recebido de um marketplace (mkt_orders), ponte para a venda do ERP (`transaction_id`,
 * preenchido pela US-MKT-007). Único por (empresa, marketplace, id externo): webhook repetido
 * não cria segundo pedido. O comprador fica só em hash (`buyer_external`).
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

    public function conta(): BelongsTo
    {
        return $this->belongsTo(ContaVendedor::class, 'account_id');
    }
}
