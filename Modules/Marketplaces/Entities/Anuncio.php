<?php

namespace Modules\Marketplaces\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Anúncio de um produto do ERP num marketplace (mkt_listings). O preço do anúncio é a fonte
 * do preço no marketplace (D8 da ADR 0439).
 *
 * @property int $id
 * @property int $business_id
 * @property int $account_id
 * @property int $marketplace_id
 * @property int $product_id
 * @property int|null $variation_id
 * @property string $item_id_external
 * @property string|null $title
 * @property string $status
 * @property string|null $listing_type
 * @property string|null $price_marketplace
 * @property int|null $stock_marketplace
 * @property string|null $category_external
 * @property string|null $fee_estimate_pct
 * @property string|null $permalink
 * @property array|null $attributes
 * @property \Illuminate\Support\Carbon|null $last_sync_at
 * @property string|null $sync_error
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
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
