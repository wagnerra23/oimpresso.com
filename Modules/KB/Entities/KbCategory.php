<?php

declare(strict_types=1);

namespace Modules\KB\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\KB\Entities\Concerns\BelongsToBusinessTrait;

/**
 * KbCategory — 1ª camada da taxonomia operacional.
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §5
 *
 * Categorias seedadas em KbCategoriesSeeder (7 operacionais + governance).
 *
 * @property int    $id
 * @property int    $business_id
 * @property string $slug
 * @property string $label
 * @property string|null $description
 * @property int    $hue           OKLCH chroma 0-360
 * @property string|null $icon
 * @property int    $sort_order
 */
class KbCategory extends Model
{
    use BelongsToBusinessTrait;

    protected $table = 'kb_categories';

    protected $fillable = [
        'business_id', 'slug', 'label', 'description',
        'hue', 'icon', 'sort_order',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'hue'         => 'integer',
        'sort_order'  => 'integer',
    ];

    public function subcategories(): HasMany
    {
        return $this->hasMany(KbSubcategory::class, 'category_id');
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(KbNode::class, 'category_id');
    }
}
