<?php

declare(strict_types=1);

namespace Modules\KB\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\KB\Entities\Concerns\BelongsToBusinessTrait;

/**
 * KbBridgeState — estado do KbBridgeFromMcpJob por business.
 *
 * 1 linha por business. `last_bridge_at` permite bridge incremental
 * (filtra mcp_memory_documents.updated_at > last_bridge_at).
 *
 * @property int    $id
 * @property int    $business_id
 * @property \Illuminate\Support\Carbon|null $last_bridge_at
 * @property int    $docs_processed_last_run
 * @property int    $edges_derived_last_run
 * @property string|null $last_error
 */
class KbBridgeState extends Model
{
    use BelongsToBusinessTrait;

    protected $table = 'kb_bridge_state';

    protected $fillable = [
        'business_id', 'last_bridge_at',
        'docs_processed_last_run', 'edges_derived_last_run', 'last_error',
    ];

    protected $casts = [
        'business_id'              => 'integer',
        'last_bridge_at'           => 'datetime',
        'docs_processed_last_run'  => 'integer',
        'edges_derived_last_run'   => 'integer',
    ];
}
