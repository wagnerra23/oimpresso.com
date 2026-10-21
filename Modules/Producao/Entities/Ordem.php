<?php

namespace Modules\Producao\Entities;

use App\Concerns\HasBusinessScope;
use App\Domain\Fsm\Concerns\GuardsFsmTransitions;
use App\Domain\Fsm\Models\SaleProcessStage;
use Illuminate\Database\Eloquent\Model;

/**
 * `pcp_ordens` — a OS por item (ADR 0436): uma linha por item da venda que gera OS.
 *
 * A situação do item (Aguardando liberação, Na fila, Em execução, Pausada, Retrabalho, Concluída,
 * Cancelada) anda no motor FSM pelo `current_stage_id` — UPDATE direto é barrado pelo
 * GuardsFsmTransitions. O roteiro anda nas programações do PCP (`os_tipo = OS_TIPO`, `os_id = id`).
 * Escopo por empresa (ADR 0093).
 *
 * @property int $id
 * @property int $business_id
 * @property int $transaction_id
 * @property int $transaction_sell_line_id
 * @property int $product_id
 * @property ?int $variation_id
 * @property string $quantidade
 * @property ?int $roteiro_id
 * @property ?int $current_stage_id
 * @property ?string $custo_real
 * @property ?\Illuminate\Support\Carbon $pronta_em
 * @property ?\Illuminate\Support\Carbon $custo_congelado_em
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 */
class Ordem extends Model
{
    use GuardsFsmTransitions;
    use HasBusinessScope;

    /** Chave das programações do PCP que pertencem a uma OS por item. */
    public const OS_TIPO = 'pcp_ordem';

    protected $table = 'pcp_ordens';

    protected $guarded = ['id'];

    protected $casts = [
        'pronta_em' => 'datetime',
        'custo_congelado_em' => 'datetime',
    ];

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<SaleProcessStage, $this> */
    public function currentStage(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SaleProcessStage::class, 'current_stage_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Roteiro, $this> */
    public function roteiro(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Roteiro::class, 'roteiro_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<Programacao, $this> */
    public function programacoes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Programacao::class, 'os_id')->where('os_tipo', self::OS_TIPO)
            ->orderBy('bloco')->orderBy('trilha')->orderBy('ordem');
    }
}
