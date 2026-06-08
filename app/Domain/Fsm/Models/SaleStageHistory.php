<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit log de transições FSM executadas (ADR 0129 §Schema).
 *
 * Append-only por convenção (ExecuteStageActionService nunca dá update/delete).
 * Multi-tenant Tier 0 (ADR 0093) via HasBusinessScope.
 */
class SaleStageHistory extends Model
{
    use HasBusinessScope;

    protected $table = 'sale_stage_history';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'payload_snapshot' => 'array',
        'executed_at' => 'datetime',
    ];

    public function action(): BelongsTo
    {
        return $this->belongsTo(SaleStageAction::class, 'action_id');
    }

    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(SaleProcessStage::class, 'from_stage_id');
    }

    public function toStage(): BelongsTo
    {
        return $this->belongsTo(SaleProcessStage::class, 'to_stage_id');
    }
}
