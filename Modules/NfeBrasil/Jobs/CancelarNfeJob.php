<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Jobs;

use App\Domain\Fsm\Models\TransactionDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\NfeService;

/**
 * US-SELL-034 — Cancelar NFe via evento SEFAZ (tpEvento=110111).
 *
 * Disparado pelo side-effect CancelarVendaCascade pra cada TransactionDocument
 * tipo nfe* com status=authorized.
 *
 * Idempotência: se NfeEmissao.status='cancelada' OU TransactionDocument já está
 * com status='cancelled', loga e retorna sem chamar SEFAZ. Sem isso, retry em
 * failure poderia tentar cancelar 2x (SEFAZ devolveria cstat=573 "Duplicidade
 * de evento" — mas evita o roundtrip).
 *
 * Multi-tenant Tier 0 (ADR 0093): $businessId no constructor (jobs em fila
 * NÃO leem session()). Service tem cross-tenant guard explícito a mais.
 *
 * Escopo do cancelamento: SOMENTE doc_type=nfe55 e nfce65. NFSe (modelo 56)
 * tem fluxo SEFAZ municipal totalmente distinto — cancelamento NFSe é US
 * separada (escopo SEFAZ-municipal).
 */
class CancelarNfeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly int $businessId,
        public readonly int $transactionDocumentId,
        public readonly string $motivo,
    ) {}

    public function handle(NfeService $nfeService): void
    {
        // ── 1. Carregar TransactionDocument (sem global scope p/ cross-tenant
        //      guard explícito — defesa em profundidade ADR 0093) ────────────
        // SUPERADMIN: job em fila não tem session; valida business_id explicitamente abaixo (linha ~66).
        $doc = TransactionDocument::withoutGlobalScopes()->find($this->transactionDocumentId);

        if (! $doc) {
            Log::warning('CancelarNfeJob: TransactionDocument não encontrado', [
                'business_id'             => $this->businessId,
                'transaction_document_id' => $this->transactionDocumentId,
            ]);
            return;
        }

        if ((int) $doc->business_id !== $this->businessId) {
            Log::error('CancelarNfeJob: cross-tenant — business_id divergente', [
                'job_business_id'      => $this->businessId,
                'doc_business_id'      => $doc->business_id,
                'transaction_document' => $doc->id,
            ]);
            return; // não relança — drop silencioso pra não retentar com mesma config
        }

        // ── 2. Verificar tipo (só NFe55/NFCe65 — NFSe é fluxo SEFAZ-municipal) ─
        $tiposSuportados = [
            TransactionDocument::DOC_NFE55,
            TransactionDocument::DOC_NFCE65,
        ];
        if (! in_array($doc->doc_type, $tiposSuportados, true)) {
            Log::info('CancelarNfeJob: doc_type fora do escopo NFe SEFAZ — pulando', [
                'transaction_document_id' => $doc->id,
                'doc_type'                => $doc->doc_type,
            ]);
            return;
        }

        // ── 3. Idempotência nível TransactionDocument ───────────────────────
        if ($doc->status === TransactionDocument::STATUS_CANCELLED) {
            Log::info('CancelarNfeJob: TransactionDocument já cancelado — skip', [
                'transaction_document_id' => $doc->id,
            ]);
            return;
        }

        // ── 4. Resolver NfeEmissao via doc_class+doc_id ─────────────────────
        // Em prod doc_class deve ser Modules\NfeBrasil\Models\NfeEmissao.
        // Defensivo: se doc_id apontar pra Model diferente do esperado, aborta.
        if ($doc->doc_class !== NfeEmissao::class) {
            Log::warning('CancelarNfeJob: doc_class inesperado — pulando', [
                'transaction_document_id' => $doc->id,
                'doc_class'               => $doc->doc_class,
                'esperado'                => NfeEmissao::class,
            ]);
            return;
        }

        // SUPERADMIN: job em fila sem session; $doc já passou no guard de business_id (linha ~66).
        $emissao = NfeEmissao::withoutGlobalScopes()->find($doc->doc_id);
        if (! $emissao) {
            Log::warning('CancelarNfeJob: NfeEmissao não encontrada', [
                'transaction_document_id' => $doc->id,
                'doc_id'                  => $doc->doc_id,
            ]);
            return;
        }

        // ── 5. Idempotência nível NfeEmissao ─────────────────────────────────
        // Se já cancelada na SEFAZ, só sincroniza status do TransactionDocument
        // (evita retry roundtrip pra SEFAZ). NfeService.cancelar também é
        // idempotente — defesa em profundidade.
        if ($emissao->status === 'cancelada') {
            Log::info('CancelarNfeJob: NfeEmissao já cancelada na SEFAZ — sincronizando TransactionDocument', [
                'transaction_document_id' => $doc->id,
                'nfe_emissao_id'          => $emissao->id,
            ]);
            $doc->update(['status' => TransactionDocument::STATUS_CANCELLED]);
            return;
        }

        // ── 6. Chamar NfeService::cancelar (lança em failure → retry job) ───
        // Re-lance explícito pra Job retry (tries=3, backoff=60).
        try {
            $nfeService->cancelar(
                businessId:    $this->businessId,
                nfeEmissaoId:  (int) $emissao->id,
                justificativa: $this->motivo,
            );

            // ── 7. Sucesso: atualiza TransactionDocument.status=cancelled ───
            $doc->update(['status' => TransactionDocument::STATUS_CANCELLED]);

            Log::info('CancelarNfeJob: NFe cancelada com sucesso', [
                'business_id'             => $this->businessId,
                'transaction_document_id' => $doc->id,
                'nfe_emissao_id'          => $emissao->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('CancelarNfeJob: falha — será retentado (tries=' . $this->tries . ')', [
                'business_id'             => $this->businessId,
                'transaction_document_id' => $doc->id,
                'nfe_emissao_id'          => $emissao->id,
                'attempt'                 => $this->attempts(),
                'error'                   => $e->getMessage(),
            ]);
            throw $e; // re-lança pra fila retentar (tries=3, backoff=60)
        }
    }
}
