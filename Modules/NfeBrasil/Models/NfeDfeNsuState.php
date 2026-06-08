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
 */
class NfeDfeNsuState extends Model
{
    use HasBusinessScope;

    protected $table = 'nfe_dfe_nsu_state';

    protected $fillable = [
        'business_id',
        'last_nsu',
        'ultimo_check_em',
        'total_xmls_processados',
        'ultimo_lote_count',
    ];

    protected $casts = [
        'last_nsu'               => 'integer',
        'ultimo_check_em'        => 'datetime',
        'total_xmls_processados' => 'integer',
        'ultimo_lote_count'      => 'integer',
    ];

    /**
     * Throttle: SEFAZ recomenda 5min entre consultas DistribuicaoDFe.
     */
    public function podeConsultarAgora(int $cooldownMinutes = 5): bool
    {
        if (! $this->ultimo_check_em) {
            return true;
        }
        return $this->ultimo_check_em->lt(now()->subMinutes($cooldownMinutes));
    }
}
