<?php

declare(strict_types=1);

namespace Modules\KB\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\KB\Entities\Concerns\BelongsToBusinessTrait;

/**
 * KbPathStep — passo ordenado de uma trilha.
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §6
 *
 * `position` é 1-based. UNIQUE (path_id, position) garante ordem total.
 *
 * @property int    $id
 * @property int    $business_id
 * @property int    $path_id
 * @property int    $node_id
 * @property int    $position
 * @property string $step_type      leitura|pratica|decisao
 * @property string|null $note
 */
class KbPathStep extends Model
{
    use BelongsToBusinessTrait;

    protected $table = 'kb_path_steps';

    protected $fillable = [
        'business_id', 'path_id', 'node_id',
        'position', 'step_type', 'note',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'path_id'     => 'integer',
        'node_id'     => 'integer',
        'position'    => 'integer',
    ];

    public function path(): BelongsTo
    {
        return $this->belongsTo(KbPath::class, 'path_id');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(KbNode::class, 'node_id');
    }
}
