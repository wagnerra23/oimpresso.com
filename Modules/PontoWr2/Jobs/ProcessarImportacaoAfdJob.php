<?php

namespace Modules\PontoWr2\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\PontoWr2\Entities\Importacao;
use Modules\PontoWr2\Services\AfdParserService;

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

    /** @var int ID da importação (Importacao.id) */
    public $importacaoId;

    /** @var int Número máximo de tentativas antes de marcar como failed */
    public $tries = 3;

    /** @var int Timeout em segundos */
    public $timeout = 600;

    public function __construct($importacaoId)
    {
        $this->importacaoId = $importacaoId;
    }

    public function handle(AfdParserService $parser)
    {
        $importacao = Importacao::find($this->importacaoId);
        if (!$importacao) {
            Log::warning('[PontoWr2] ProcessarImportacaoAfdJob: Importacao não encontrada', [
                'id' => $this->importacaoId,
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
        $importacao = Importacao::find($this->importacaoId);
        if ($importacao) {
            $importacao->update([
                'estado' => Importacao::ESTADO_FALHOU,
                'log'    => substr('Falha no job: ' . $e->getMessage(), 0, 65000),
            ]);
        }
        Log::error('[PontoWr2] ProcessarImportacaoAfdJob failed', [
            'id'    => $this->importacaoId,
            'erro'  => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
