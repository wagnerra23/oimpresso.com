<?php

declare(strict_types=1);

namespace Modules\KB\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\KB\Entities\Concerns\BelongsToBusinessTrait;

/**
 * KbDecisionTree — troubleshooter (grafo Q→Sim/Não→Q'/Fix).
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §7
 *
 * `root_step_id` aponta pro primeiro step (entry point) — populado
 * em segundo INSERT após criar o primeiro step (FK circular).
 *
 * @property int    $id
 * @property int    $business_id
 * @property string $slug
 * @property string $title
 * @property string|null $equip
 * @property string|null $when_to_use
 * @property int    $hue
 * @property string $status         draft|published|archived
 * @property int|null $root_step_id
 * @property int|null $author_user_id
 */
class KbDecisionTree extends Model
{
    use BelongsToBusinessTrait, SoftDeletes;

    protected $table = 'kb_decision_trees';

    protected $fillable = [
        'business_id', 'slug', 'title', 'equip',
        'when_to_use', 'hue', 'status', 'root_step_id', 'author_user_id',
    ];

    protected $casts = [
        'business_id'    => 'integer',
        'hue'            => 'integer',
        'root_step_id'   => 'integer',
        'author_user_id' => 'integer',
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(KbDecisionTreeStep::class, 'tree_id')->orderBy('position');
    }

    public function rootStep(): BelongsTo
    {
        return $this->belongsTo(KbDecisionTreeStep::class, 'root_step_id');
    }
}
