<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RBAC join: ação × role Spatie (ADR 0129 §Schema).
 *
 * `role_name` é FK lógica pra `roles.name` do spatie/laravel-permission v6.0.
 * Tenancy: herda via action → stage → process → business_id. Service valida.
 */
class SaleStageActionRole extends Model
{
    protected $table = 'sale_stage_action_roles';

    protected $guarded = ['id'];

    public function action(): BelongsTo
    {
        return $this->belongsTo(SaleStageAction::class, 'action_id');
    }
}
