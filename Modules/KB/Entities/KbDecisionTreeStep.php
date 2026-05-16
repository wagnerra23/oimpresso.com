<?php

declare(strict_types=1);

namespace Modules\KB\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\KB\Entities\Concerns\BelongsToBusinessTrait;

/**
 * KbDecisionTreeStep — pergunta com branches yes/no.
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §7
 *
 * Invariante por linha (enforce via Observer):
 *   - exatamente UM de (yes_next_step_id, yes_fix) populado
 *   - exatamente UM de (no_next_step_id, no_fix) populado
 *
 * `yes_fix` e `no_fix` aceitam markdown com `#kb-NNN` cross-link.
 * `yes_fix_node_id` e `no_fix_node_id` opcionalmente apontam pro node-fix
 * direto, gerando edge `fix-of-decision`.
 *
 * @property int    $id
 * @property int    $business_id
 * @property int    $tree_id
 * @property int    $position
 * @property string $question
 * @property int|null    $yes_next_step_id
 * @property string|null $yes_fix
 * @property int|null    $yes_fix_node_id
 * @property int|null    $no_next_step_id
 * @property string|null $no_fix
 * @property int|null    $no_fix_node_id
 */
class KbDecisionTreeStep extends Model
{
    use BelongsToBusinessTrait;

    protected $table = 'kb_decision_tree_steps';

    protected $fillable = [
        'business_id', 'tree_id', 'position', 'question',
        'yes_next_step_id', 'yes_fix', 'yes_fix_node_id',
        'no_next_step_id', 'no_fix', 'no_fix_node_id',
    ];

    protected $casts = [
        'business_id'      => 'integer',
        'tree_id'          => 'integer',
        'position'         => 'integer',
        'yes_next_step_id' => 'integer',
        'yes_fix_node_id'  => 'integer',
        'no_next_step_id'  => 'integer',
        'no_fix_node_id'   => 'integer',
    ];

    public function tree(): BelongsTo
    {
        return $this->belongsTo(KbDecisionTree::class, 'tree_id');
    }

    public function yesNextStep(): BelongsTo
    {
        return $this->belongsTo(self::class, 'yes_next_step_id');
    }

    public function noNextStep(): BelongsTo
    {
        return $this->belongsTo(self::class, 'no_next_step_id');
    }

    public function yesFixNode(): BelongsTo
    {
        return $this->belongsTo(KbNode::class, 'yes_fix_node_id');
    }

    public function noFixNode(): BelongsTo
    {
        return $this->belongsTo(KbNode::class, 'no_fix_node_id');
    }
}
