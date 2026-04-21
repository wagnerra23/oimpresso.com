<?php

namespace Modules\PontoWr2\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\PontoWr2\Entities\Colaborador;
use Modules\PontoWr2\Services\ApuracaoService;

/**
 * Dispara recálculo da apuração diária para um colaborador+data.
 *
 * Gatilhos típicos:
 *   - Aprovação de intercorrência (IntercorrenciaService::aprovar)
 *   - Importação AFD concluída (processamento em lote chama por range)
 *   - Ajuste manual de banco de horas
 *   - Comando artisan de reapuração retroativa
 */
class ReapurarDiaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    public $colaboradorId;

    /** @var string YYYY-MM-DD */
    public $data;

    /** @var int */
    public $tries = 3;

    public function __construct($colaboradorId, $data)
    {
        $this->colaboradorId = (int) $colaboradorId;
        // Carbon serializa mal como propriedade pública — passar string.
        if ($data instanceof Carbon) {
            $this->data = $data->toDateString();
        } else {
            $this->data = (string) $data;
        }
    }

    public function handle(ApuracaoService $apuracao)
    {
        $colaborador = Colaborador::find($this->colaboradorId);
        if (!$colaborador) {
            Log::warning('[PontoWr2] ReapurarDiaJob: Colaborador não encontrado', [
                'id' => $this->colaboradorId,
            ]);
            return;
        }

        $apuracao->apurar($colaborador, Carbon::parse($this->data));
    }

    public function failed(\Throwable $e)
    {
        Log::error('[PontoWr2] ReapurarDiaJob failed', [
            'colaborador_id' => $this->colaboradorId,
            'data'           => $this->data,
            'erro'           => $e->getMessage(),
        ]);
    }
}
