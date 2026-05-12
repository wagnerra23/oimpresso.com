<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Concerns\HasBusinessScope;
use App\Product;
use App\Variation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProductBom — Bill of Materials normalizada (US-INV-001, SPEC §4.1, ADR proposed).
 *
 * Composição de kits: 1 produto pai → N componentes (cada componente pode
 * ele mesmo ser outro kit — multi-level). Substitui JSON `variations.combo_variations`
 * legacy UPos como fonte canônica V2.
 *
 * Multi-tenant Tier 0 (ADR 0093) via HasBusinessScope.
 *
 * Cross-vertical — vive em app/Domain/Inventory/ (SPEC §9, irmão de app/Domain/Fsm/).
 */
class ProductBom extends Model
{
    use HasBusinessScope;

    protected $table = 'product_bom';

    protected $guarded = ['id'];

    protected $casts = [
        'qty_required' => 'decimal:4',
        'is_optional' => 'boolean',
        'allow_substitution' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** Produto pai (kit) — pode ser products.type='combo' OU 'single' com BOM opt-in. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_product_id');
    }

    /** Produto componente (filho do kit). */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }

    /** Variação específica do pai (se kit definido per variação). */
    public function parentVariation(): BelongsTo
    {
        return $this->belongsTo(Variation::class, 'parent_variation_id');
    }

    /** Variação específica do componente (obrigatório se componente é variable). */
    public function componentVariation(): BelongsTo
    {
        return $this->belongsTo(Variation::class, 'component_variation_id');
    }
}
