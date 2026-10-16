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

    /**
     * Só as transições da VENDA. `transaction_id` guarda a chave do documento que andou no motor;
     * a OS por item (ADR 0436) e a OS da oficina gravam o id delas no mesmo campo, então o histórico
     * da venda X traria a OS de mesmo número. Exclui as etapas de processos de produção.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $q
     */
    public function scopeDaVenda($q): void
    {
        $producao = fn ($p) => $p->where('dominio', 'producao');
        $q->whereDoesntHave('toStage.process', $producao)->whereDoesntHave('fromStage.process', $producao);
    }

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
