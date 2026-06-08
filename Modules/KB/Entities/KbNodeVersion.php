<?php

declare(strict_types=1);

namespace Modules\KB\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\KB\Entities\Concerns\BelongsToBusinessTrait;

/**
 * KbNodeVersion — snapshot append-only por edit de artigo.
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §8
 *
 * **APPEND-ONLY (Tier 0 IRREVOGÁVEL — ADR 0061):** UPDATE e DELETE
 * lançam Exception via KbNodeVersionObserver. Trigger MySQL fica V2.
 *
 * Populado APENAS pra kb_nodes.is_editable=true. Bridges canon
 * (ADR/session/charter) já têm versionamento via mcp_memory_documents_history.
 *
 * @property int    $id
 * @property int    $business_id
 * @property int    $node_id
 * @property \Illuminate\Support\Carbon $version_at
 * @property int|null    $author_user_id
 * @property array  $snapshot
 * @property string|null $change_reason
 */
class KbNodeVersion extends Model
{
    use BelongsToBusinessTrait;

    public $timestamps = false;

    protected $table = 'kb_node_versions';

    protected $fillable = [
        'business_id', 'node_id', 'version_at',
        'author_user_id', 'snapshot', 'change_reason',
    ];

    protected $casts = [
        'business_id'    => 'integer',
        'node_id'        => 'integer',
        'version_at'     => 'datetime',
        'author_user_id' => 'integer',
        'snapshot'       => 'array',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(KbNode::class, 'node_id');
    }
}
