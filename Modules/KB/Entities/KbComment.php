<?php

declare(strict_types=1);

namespace Modules\KB\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\KB\Entities\Concerns\BelongsToBusinessTrait;

/**
 * KbComment — comment inline ancorado em block_idx do body_blocks.
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §9
 *
 * Sem versionamento — historico via soft-delete.
 *
 * @property int    $id
 * @property int    $business_id
 * @property int    $node_id
 * @property int    $block_idx
 * @property string $text
 * @property int    $author_user_id
 */
class KbComment extends Model
{
    use BelongsToBusinessTrait, SoftDeletes;

    protected $table = 'kb_comments';

    protected $fillable = [
        'business_id', 'node_id', 'block_idx', 'text', 'author_user_id',
    ];

    protected $casts = [
        'business_id'    => 'integer',
        'node_id'        => 'integer',
        'block_idx'      => 'integer',
        'author_user_id' => 'integer',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(KbNode::class, 'node_id');
    }
}
