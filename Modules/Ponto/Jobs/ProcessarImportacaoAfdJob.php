<?php

namespace Modules\Ponto\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Ponto\Entities\Importacao;
use Modules\Ponto\Services\AfdParserService;

/**
 * Job assíncrono que processa um arquivo AFD previamente persistido em storage.
 *
 * Fluxo:
 *   1) ImportacaoController::store() salva o arquivo + registro Importacao
 *      com estado = PENDENTE e dispatch() este job.
 *   2) Worker (queue:work) pega o job e chama AfdParserService::processar.
 *   3) O service atualiza o Importacao progressivamente (linhas, estado, amostras).
 */
class ProcessarImportacaoAfdJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int business_id do tenant (multi-tenant Tier 0, ADR 0093) */
    public $businessId;

    /** @var int ID da importação (Importacao.id) */
    public $importacaoId;

    /** @var int Número máximo de tentativas antes de marcar como failed */
    public $tries = 3;

    /** @var int Timeout em segundos */
    public $timeout = 600;

    public function __construct($businessId, $importacaoId)
    {
        $this->businessId = (int) $businessId;
        $this->importacaoId = (int) $importacaoId;
    }

    public function handle(AfdParserService $parser)
    {
        // D9.b Wave 16 — log estruturado entry queue worker. Tier 0: business_id
        // SEMPRE no constructor (session() não funciona em fila — ADR 0093).
        Log::info('ponto.afd.job.iniciado', [
            'business_id'   => $this->businessId,
            'importacao_id' => $this->importacaoId,
        ]);

        // SUPERADMIN: job sem session (queue worker não tem auth) — scope manual
        // garante isolamento multi-tenant Tier 0. Ver ADR 0093 + ScopeByBusiness.
        $importacao = Importacao::where('business_id', $this->businessId)
            ->find($this->importacaoId);

        if (!$importacao) {
            Log::warning('[PontoWr2] ProcessarImportacaoAfdJob: Importacao não encontrada', [
                'business_id' => $this->businessId,
                'id'          => $this->importacaoId,
            ]);
            return;
        }

        // Evita reprocessar concluídas
        if (in_array($importacao->estado, [
            Importacao::ESTADO_CONCLUIDA,
            Importacao::ESTADO_CONCLUIDA_COM_ERROS,
        ])) {
            return;
        }

        $parser->processar($importacao);
    }

    public function failed(\Throwable $e)
    {
        // SUPERADMIN: callback failed() roda fora de session — scope manual.
        $importacao = Importacao::where('business_id', $this->businessId)
            ->find($this->importacaoId);
        if ($importacao) {
            $importacao->update([
                'estado' => Importacao::ESTADO_FALHOU,
                'log'    => substr('Falha no job: ' . $e->getMessage(), 0, 65000),
            ]);
        }
        Log::error('[PontoWr2] ProcessarImportacaoAfdJob failed', [
            'business_id' => $this->businessId,
            'id'          => $this->importacaoId,
            'erro'        => $e->getMessage(),
            'trace'       => $e->getTraceAsString(),
        ]);
    }
}
