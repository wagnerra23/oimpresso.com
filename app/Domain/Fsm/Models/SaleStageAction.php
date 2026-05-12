<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Transição disponível em uma SaleProcessStage (ADR 0129 §Schema).
 *
 * `target_stage_id` null = ação que NÃO transita (ex: re-emitir 2ª via DANFE).
 * `event_class` = FQCN do Event Laravel disparado pós-execução.
 * `side_effect_class` = FQCN de App\Domain\Fsm\Contracts\SideEffectInterface.
 * `roles` vazio = action PÚBLICA (sem RBAC).
 *
 * Tenancy: herda via stage → process → business_id. Service valida.
 */
class SaleStageAction extends Model
{
    protected $table = 'sale_stage_actions';

    protected $guarded = ['id'];

    protected $casts = [
        'requires_confirmation' => 'bool',
        'is_critical' => 'bool',
        'side_effect_payload' => 'array',
    ];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(SaleProcessStage::class, 'stage_id');
    }

    public function targetStage(): BelongsTo
    {
        return $this->belongsTo(SaleProcessStage::class, 'target_stage_id');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(SaleStageActionRole::class, 'action_id');
    }

    public function isPublic(): bool
    {
        return $this->roles()->count() === 0;
    }
}
