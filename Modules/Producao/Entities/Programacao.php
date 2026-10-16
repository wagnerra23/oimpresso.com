<?php

namespace Modules\Producao\Entities;

use App\Concerns\HasBusinessScope;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * `pcp_programacoes` — fundo do PCP (ADR 0431, thread 02). Escopo por empresa (ADR 0093).
 *
 * @property int $id
 * @property int $business_id
 * @property string $os_tipo
 * @property int $os_id
 * @property int $posto_id
 * @property ?int $operacao_id
 * @property ?int $user_id
 * @property string $status
 * @property \Illuminate\Support\Carbon $inicio_previsto
 * @property \Illuminate\Support\Carbon $fim_previsto
 */
class Programacao extends Model
{
    use HasBusinessScope;

    protected $table = 'pcp_programacoes';

    protected $guarded = ['id'];

    protected $casts = ['inicio_previsto' => 'datetime', 'fim_previsto' => 'datetime'];

    /** Etapa da venda: `os_id` = transactions.id. Só este tipo mexe na etapa da venda (ProducaoDaVendaService). */
    public const OS_VENDA = 'venda';

    protected static function booted(): void
    {
        // Tier 0 (ADR 0093): a venda programada tem de ser da mesma empresa da programação.
        static::saving(function (self $p): void {
            if ($p->os_tipo !== self::OS_VENDA || ! $p->isDirty(['os_tipo', 'os_id', 'business_id'])) {
                return;
            }
            $existe = DB::table('transactions')->where('id', $p->os_id)
                ->where('business_id', $p->business_id)->where('type', 'sell')->exists();
            if (! $existe) {
                throw new DomainException('Venda não encontrada nesta empresa.');
            }
        });
    }
}
