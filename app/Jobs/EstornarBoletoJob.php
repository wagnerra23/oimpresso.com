<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Fsm\Models\TransactionDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use RuntimeException;

/**
 * EstornarBoletoJob — Hub de cancelamento de cobrança (Asaas / Inter PJ).
 *
 * US-CASCADE-BOLETO-001 (PR foundational): este Job é o ponto único de entrada
 * pra cancelar um TransactionDocument do tipo boleto_*. Ele resolve o gateway
 * (Asaas vs Inter PJ) e despacha pro Cancelar*Job específico que faz a chamada
 * HTTP real ao provedor.
 *
 * ⚠️ TODO (US-CASCADE-BOLETO-002): integrar este Job no CancelarVendaCascade.
 * Hoje ele ainda NÃO é chamado por ninguém — esse PR só prepara a infra
 * (doc_type ENUM estendido + Job hub + tests). A integração com cascade fica
 * na próxima US.
 *
 * ⚠️ TODO (US-CASCADE-BOLETO-003+): implementar as classes concretas dos
 * gateways. Hoje `\Modules\RecurringBilling\Jobs\CancelarCobrancaAsaasJob` e
 * `\App\Jobs\CancelarCobrancaInterJob` NÃO existem — `class_exists()` retorna
 * false e este Job apenas loga TODO (sem falhar). Quando os Jobs reais
 * forem criados o despacho passa a funcionar automaticamente.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093):
 *   - businessId vem do constructor (Job assíncrono não enxerga session)
 *   - Query usa withoutGlobalScope + where('business_id', $businessId)
 *   - Documento de outro tenant lança RuntimeException
 *
 * Idempotência: documento já com status='cancelled' loga info e retorna
 * sem despachar gateway (proteção contra retry / dispatch duplicado).
 *
 * Retry policy: tries=3, backoff=60s (rede do gateway pode oscilar).
 */
class EstornarBoletoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Máximo de tentativas em falha. */
    public int $tries = 3;

    /** Segundos entre retries. */
    public int $backoff = 60;

    public function __construct(
        public readonly int $businessId,
        public readonly int $transactionDocumentId,
        public readonly string $motivo,
    ) {}

    public function handle(): void
    {
        // 1) Carrega documento ignorando global scope (Job não tem session auth)
        $document = TransactionDocument::withoutGlobalScope(ScopeByBusiness::class)
            ->find($this->transactionDocumentId);

        if ($document === null) {
            throw new RuntimeException(
                "TransactionDocument id={$this->transactionDocumentId} não encontrado"
            );
        }

        // 2) Multi-tenant guard — businessId do Job DEVE bater com o do documento
        if ((int) $document->business_id !== $this->businessId) {
            throw new RuntimeException(
                "Cross-tenant violation: Job businessId={$this->businessId} != "
                . "document.business_id={$document->business_id} (doc id={$document->id})"
            );
        }

        // 3) Validação de doc_type — só boleto_* é aceito
        $tiposValidos = [
            TransactionDocument::DOC_BOLETO_ASAAS,
            TransactionDocument::DOC_BOLETO_INTER,
        ];

        if (! in_array($document->doc_type, $tiposValidos, true)) {
            throw new RuntimeException(
                "doc_type inválido pra estorno: '{$document->doc_type}'. "
                . 'Esperado: boleto_asaas|boleto_inter (doc id=' . $document->id . ')'
            );
        }

        // 4) Idempotência — já cancelado é no-op
        if ($document->status === TransactionDocument::STATUS_CANCELLED) {
            Log::info('EstornarBoletoJob: documento já cancelado, no-op', [
                'business_id' => $this->businessId,
                'document_id' => $document->id,
                'doc_type' => $document->doc_type,
                'motivo' => $this->motivo,
            ]);

            return;
        }

        // 5) Roteamento por gateway — despacha Job específico se existir,
        //    senão loga TODO (ainda não implementado — ver header).
        $this->despacharGateway($document);

        // 6) Marca como cancelled localmente
        //    Observação: o status real do gateway é confirmado quando o
        //    Cancelar*Job concreto processar o webhook de retorno. Aqui
        //    marcamos otimisticamente — a fonte da verdade fiscal/financeira
        //    fica do lado do gateway, e o status final é reconciliado por
        //    webhook listener (US-CASCADE-BOLETO-003+).
        $document->status = TransactionDocument::STATUS_CANCELLED;
        $document->save();

        Log::info('EstornarBoletoJob: documento marcado como cancelled', [
            'business_id' => $this->businessId,
            'document_id' => $document->id,
            'doc_type' => $document->doc_type,
            'motivo' => $this->motivo,
        ]);
    }

    /**
     * Despacha Cancelar*Job específico por gateway, ou loga TODO se
     * a classe ainda não existe (Jobs concretos virão em US separada).
     */
    private function despacharGateway(TransactionDocument $document): void
    {
        $mapaJobs = [
            TransactionDocument::DOC_BOLETO_ASAAS => '\\Modules\\RecurringBilling\\Jobs\\CancelarCobrancaAsaasJob',
            TransactionDocument::DOC_BOLETO_INTER => '\\App\\Jobs\\CancelarCobrancaInterJob',
        ];

        $jobClass = $mapaJobs[$document->doc_type] ?? null;

        if ($jobClass === null) {
            // Não deveria chegar aqui — validação acima já protege.
            return;
        }

        if (! class_exists($jobClass)) {
            Log::warning('EstornarBoletoJob: gateway Job ainda não implementado (TODO)', [
                'business_id' => $this->businessId,
                'document_id' => $document->id,
                'doc_type' => $document->doc_type,
                'expected_class' => $jobClass,
                'motivo' => $this->motivo,
                'todo' => 'Implementar em US-CASCADE-BOLETO-003+ (chamada HTTP real ao gateway)',
            ]);

            return;
        }

        // Dispatch real — Job concreto faz a chamada HTTP ao gateway.
        Bus::dispatch(new $jobClass(
            $this->businessId,
            $document->id,
            $this->motivo,
        ));
    }
}
