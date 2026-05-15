<?php

declare(strict_types=1);

namespace Modules\KB\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\KB\Entities\Concerns\BelongsToBusinessTrait;

/**
 * KbFavorite — bookmark per user.
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §9
 *
 * UNIQUE (user_id, node_id) — toggle no controller via firstOrCreate→delete.
 *
 * @property int    $id
 * @property int    $business_id
 * @property int    $user_id
 * @property int    $node_id
 */
class KbFavorite extends Model
{
    use BelongsToBusinessTrait;

    public const UPDATED_AT = null; // só created_at

    protected $table = 'kb_favorites';

    protected $fillable = ['business_id', 'user_id', 'node_id'];

    protected $casts = [
        'business_id' => 'integer',
        'user_id'     => 'integer',
        'node_id'     => 'integer',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(KbNode::class, 'node_id');
    }
}
