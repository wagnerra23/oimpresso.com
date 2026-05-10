<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Estado (etapa) dentro de um SaleProcess (ADR 0129 §Schema).
 *
 * Sem business_id direto: o tenancy é garantido via SaleProcess (HasBusinessScope).
 * ExecuteStageActionService valida `$user->business_id === $stage->process->business_id`
 * antes de qualquer transição.
 */
class SaleProcessStage extends Model
{
    protected $table = 'sale_process_stages';

    protected $guarded = ['id'];

    protected $casts = [
        'is_initial' => 'bool',
        'is_terminal' => 'bool',
        'sort_order' => 'int',
    ];

    public function process(): BelongsTo
    {
        return $this->belongsTo(SaleProcess::class, 'process_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(SaleStageAction::class, 'stage_id');
    }
}
