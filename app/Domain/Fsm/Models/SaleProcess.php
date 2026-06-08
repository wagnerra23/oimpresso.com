<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo de processos por business (ADR 0129 §Schema).
 *
 * Multi-tenant Tier 0 (ADR 0093) via HasBusinessScope.
 */
class SaleProcess extends Model
{
    use HasBusinessScope;

    protected $table = 'sale_processes';

    protected $guarded = ['id'];

    protected $casts = [
        'active' => 'bool',
    ];

    public function stages(): HasMany
    {
        return $this->hasMany(SaleProcessStage::class, 'process_id');
    }

    public function initialStage(): ?SaleProcessStage
    {
        return $this->stages()->where('is_initial', true)->first();
    }
}
