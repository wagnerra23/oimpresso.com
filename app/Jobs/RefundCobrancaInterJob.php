<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Fsm\Models\TransactionDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use RuntimeException;
use Throwable;

/**
 * RefundCobrancaInterJob — STUB honesto pra refund de boleto Inter PJ JÁ PAGO.
 *
 * US-CASCADE-BOLETO-006 (refund Inter — caminho manual). Inter PJ NÃO tem
 * endpoint nativo de reembolso de boleto pago. As 2 opções reais hoje:
 *
 *   (a) TED/PIX manual via app Inter Business — humano executa
 *   (b) API "Cobrança v3 — Cancelamento com estorno" — ainda em fase beta
 *       Inter, sem GA público (verificado 2026-05). Vira US futura.
 *
 * Comportamento atual:
 *   1. Guards multi-tenant + doc_type (igual aos outros refund jobs)
 *   2. Log warning "Refund Inter PJ requer ação manual"
 *   3. Se tabela `manual_actions_queue` existir, insere registro pendente
 *      pra UI admin processar; senão só fica no log
 *   4. NÃO chama nenhuma API externa
 *   5. NÃO marca documento como refunded (refund é estado contábil que
 *      só vira real depois que ação manual executar)
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093):
 *   - businessId vem do constructor (Job assíncrono não tem session)
 *   - Query usa withoutGlobalScope + where('business_id', $businessId)
 *   - Documento de outro tenant lança RuntimeException
 *
 * Retry policy: tries=3, backoff=300s — mesmo sendo stub, futura integração
 * Inter v3 beta vai precisar da mesma policy que RefundCobrancaAsaasJob.
 *
 * ⚠️ TODO US-CASCADE-BOLETO-006b: integrar API Inter v3 cobrança cancelamento
 * com estorno quando sair do beta — substituir log + queue manual por chamada
 * HTTP real ao endpoint Inter de refund automatizado.
 *
 * ⚠️ TODO UI admin: criar tela `/admin/refunds/manual-queue` listando
 * registros de `manual_actions_queue` com botão "marcar como executado"
 * que o operador clica depois de fazer TED/PIX no Inter Business.
 */
class RefundCobrancaInterJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Máximo de tentativas em falha. */
    public int $tries = 3;

    /** Segundos entre retries (refund manual = não há corrida). */
    public int $backoff = 300;

    public function __construct(
        public readonly int $businessId,
        public readonly int $transactionDocumentId,
        public readonly string $motivo,
    ) {}

    public function handle(): void
    {
        // 1) Carrega documento ignorando global scope
        $document = TransactionDocument::withoutGlobalScope(ScopeByBusiness::class)
            ->find($this->transactionDocumentId);

        if ($document === null) {
            throw new RuntimeException(
                "TransactionDocument id={$this->transactionDocumentId} não encontrado"
            );
        }

        // 2) Multi-tenant guard
        if ((int) $document->business_id !== $this->businessId) {
            throw new RuntimeException(
                "Cross-tenant violation: Job businessId={$this->businessId} != "
                . "document.business_id={$document->business_id} (doc id={$document->id})"
            );
        }

        // 3) Validação de doc_type — só boleto_inter
        if ($document->doc_type !== TransactionDocument::DOC_BOLETO_INTER) {
            throw new RuntimeException(
                "doc_type inválido pra RefundCobrancaInterJob: '{$document->doc_type}'. "
                . 'Esperado: boleto_inter (doc id=' . $document->id . ')'
            );
        }

        // 4) Log explícito — operador precisa saber que tem ação pendente
        Log::warning('RefundCobrancaInterJob: Refund Inter PJ requer ação manual (TED/PIX via app Inter Business)', [
            'business_id' => $this->businessId,
            'document_id' => $document->id,
            'doc_type' => $document->doc_type,
            'motivo' => $this->motivo,
            'todo' => 'US-CASCADE-BOLETO-006b: integrar API Inter v3 cobrança cancelamento com estorno (beta)',
            'acao_manual' => 'Operador deve fazer TED/PIX no app Inter Business e marcar como executado em /admin/refunds/manual-queue',
        ]);

        // 5) Best-effort: registra na fila manual se tabela existir
        $this->registrarAcaoManualSeTabelaExistir($document);
    }

    /**
     * Registra ação pendente em `manual_actions_queue` se a tabela existir.
     * Hoje a tabela ainda NÃO foi criada (vira US separada de UI admin) —
     * stub só loga; quando landed, este método já está pronto.
     */
    private function registrarAcaoManualSeTabelaExistir(TransactionDocument $document): void
    {
        try {
            if (! Schema::hasTable('manual_actions_queue')) {
                Log::info('RefundCobrancaInterJob: tabela manual_actions_queue não existe (TODO criar)', [
                    'document_id' => $document->id,
                    'todo' => 'Migration manual_actions_queue + UI admin /admin/refunds/manual-queue',
                ]);

                return;
            }

            DB::table('manual_actions_queue')->insert([
                'business_id' => $this->businessId,
                'action_type' => 'refund_inter_pj',
                'related_class' => TransactionDocument::class,
                'related_id' => $document->id,
                'payload' => json_encode([
                    'motivo' => $this->motivo,
                    'doc_type' => $document->doc_type,
                    'doc_class' => $document->doc_class,
                    'doc_id' => $document->doc_id,
                ], JSON_UNESCAPED_UNICODE),
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('RefundCobrancaInterJob: ação manual registrada em manual_actions_queue', [
                'business_id' => $this->businessId,
                'document_id' => $document->id,
            ]);
        } catch (Throwable $e) {
            // Não relançar — log é a fonte da verdade enquanto tabela não existe
            Log::warning('RefundCobrancaInterJob: falha ao registrar em manual_actions_queue (best-effort)', [
                'business_id' => $this->businessId,
                'document_id' => $document->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
