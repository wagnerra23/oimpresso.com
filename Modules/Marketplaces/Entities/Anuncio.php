<?php

namespace Modules\Marketplaces\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Anúncio de um produto do ERP num marketplace (mkt_listings). O preço do anúncio é a fonte
 * do preço no marketplace (D8 da ADR 0439).
 */
class Anuncio extends Model
{
    use HasBusinessScope;

    protected $table = 'mkt_listings';

    protected $fillable = [
        'business_id', 'account_id', 'marketplace_id', 'product_id', 'variation_id',
        'item_id_external', 'title', 'status', 'listing_type', 'price_marketplace',
        'stock_marketplace', 'category_external', 'fee_estimate_pct', 'permalink',
        'attributes', 'last_sync_at', 'sync_error',
    ];

    protected $casts = [
        'attributes' => 'array',
        'price_marketplace' => 'decimal:4',
        'fee_estimate_pct' => 'decimal:2',
        'last_sync_at' => 'datetime',
    ];

    public function conta(): BelongsTo
    {
        return $this->belongsTo(ContaVendedor::class, 'account_id');
    }
}
