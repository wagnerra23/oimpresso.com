<?php

declare(strict_types=1);

namespace Modules\KB\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\KB\Entities\Concerns\BelongsToBusinessTrait;

/**
 * KbSubcategory — 2ª camada da taxonomia, opcionalmente auto-derivada.
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §5
 *
 * `auto_match` é uma regra JSON que o `KbEdgeAutoDeriver` usa pra ligar
 * automaticamente um node à subcategoria sem o usuário escolher.
 * Exemplo: {"field": "equip", "op": "=", "value": "Roland VS-540"}
 *
 * @property int    $id
 * @property int    $business_id
 * @property int    $category_id
 * @property string $slug
 * @property string $label
 * @property string|null $description
 * @property array|null  $auto_match
 */
class KbSubcategory extends Model
{
    use BelongsToBusinessTrait;

    protected $table = 'kb_subcategories';

    protected $fillable = [
        'business_id', 'category_id', 'slug', 'label',
        'description', 'auto_match',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'category_id' => 'integer',
        'auto_match'  => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(KbCategory::class, 'category_id');
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(KbNode::class, 'subcategory_id');
    }
}
