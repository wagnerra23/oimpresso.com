<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Cursor NSU SEFAZ por business — DistribuicaoDFe.
 *
 * 1 row por business. NSU é cursor irreversível (SEFAZ não retorna NSU já consultado).
 * Perda = perde XMLs históricos. Backup obrigatório.
 *
 * @property \Illuminate\Support\Carbon|null $proxima_consulta_em antes disto não consultar a SEFAZ (lote 1.3)
 */
class NfeDfeNsuState extends Model
{
    use HasBusinessScope;

    protected $table = 'nfe_dfe_nsu_state';

    protected $fillable = [
        'business_id',
        'last_nsu',
        'ultimo_check_em',
        'proxima_consulta_em',
        'total_xmls_processados',
        'ultimo_lote_count',
    ];

    protected $casts = [
        'last_nsu'               => 'integer',
        'ultimo_check_em'        => 'datetime',
        'proxima_consulta_em'    => 'datetime',
        'total_xmls_processados' => 'integer',
        'ultimo_lote_count'      => 'integer',
    ];

    /**
     * Throttle: SEFAZ recomenda 5min entre consultas DistribuicaoDFe.
     */
    public function podeConsultarAgora(int $cooldownMinutes = 5): bool
    {
        // N4-05 (lote 1.3): depois de 137/656 ou de alcançar o maxNSU, a SEFAZ pede 1 h.
        if ($this->proxima_consulta_em && $this->proxima_consulta_em->isFuture()) {
            return false;
        }
        if (! $this->ultimo_check_em) {
            return true;
        }
        return $this->ultimo_check_em->lt(now()->subMinutes($cooldownMinutes));
    }
}
