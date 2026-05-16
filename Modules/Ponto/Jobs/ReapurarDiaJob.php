<?php

namespace Modules\Ponto\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Services\ApuracaoService;

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
    public $businessId;

    /** @var int */
    public $colaboradorId;

    /** @var string YYYY-MM-DD */
    public $data;

    /** @var int */
    public $tries = 3;

    public function __construct($businessId, $colaboradorId, $data)
    {
        $this->businessId = (int) $businessId;
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
        // D9.b Wave 16 — log estruturado entry queue worker (reapuração disparada
        // por aprovação intercorrência, AFD concluído ou ajuste BH). Tier 0:
        // business_id sempre no constructor (ADR 0093).
        Log::info('ponto.apuracao.job.iniciado', [
            'business_id'    => $this->businessId,
            'colaborador_id' => $this->colaboradorId,
            'data'           => $this->data,
        ]);

        // SUPERADMIN: job sem session (queue worker não tem auth) — scope manual
        // garante isolamento multi-tenant Tier 0 mesmo sem global scope ativo.
        // Ver ADR 0093 + ScopeByBusiness::apply() (sem auth → sem filtro).
        $colaborador = Colaborador::withoutGlobalScopes()
            ->where('business_id', $this->businessId)
            ->find($this->colaboradorId);

        if (!$colaborador) {
            Log::warning('[PontoWr2] ReapurarDiaJob: Colaborador não encontrado', [
                'business_id' => $this->businessId,
                'id'          => $this->colaboradorId,
            ]);
            return;
        }

        $apuracao->apurar($colaborador, Carbon::parse($this->data));
    }

    public function failed(\Throwable $e)
    {
        Log::error('[PontoWr2] ReapurarDiaJob failed', [
            'business_id'    => $this->businessId,
            'colaborador_id' => $this->colaboradorId,
            'data'           => $this->data,
            'erro'           => $e->getMessage(),
        ]);
    }
}
