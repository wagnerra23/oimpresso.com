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
use Modules\NfeBrasil\Models\NfseEmissao;
use Modules\NfeBrasil\Services\NfseCancelService;

/**
 * US-NFSE-CANCEL-001 — Cancelar NFSe via driver per-município.
 *
 * Espelha `CancelarNfeJob` (NFe55/NFCe65) mas pra NFSe modelo 56 — em vez de
 * tpEvento=110111 SEFAZ central, chama `NfseCancelService` que resolve o
 * driver per-município (ABRASF v2.04, GINFES, IPM, Tiplan, `nfse.gov.br/sefin`).
 *
 * Disparado pelo side-effect `CancelarVendaCascade` quando
 * TransactionDocument.doc_type=nfse56 e status=authorized. Bifurcação no
 * Cascade: nfe55/nfce65 → CancelarNfeJob, nfse56 → este job.
 *
 * Idempotência: se NfseEmissao.status='cancelled' OU TransactionDocument já
 * em 'cancelled', loga e retorna sem tocar driver. NfseCancelService também
 * é idempotente — defesa em profundidade.
 *
 * Multi-tenant Tier 0 (ADR 0093): $businessId no constructor (jobs em fila
 * NÃO leem session()). Service tem cross-tenant guard explícito.
 *
 * Failure modes:
 *   - Município sem driver registrado → RuntimeException → job retry tries=3
 *     (espera-se que admin registre driver per-município ANTES do cancel chegar)
 *   - Driver stub (sem SOAP) → RuntimeException → mesma coisa
 *   - SOAP/REST municipal falha → driver lança → job retry
 *
 * @see NfseCancelService
 * @see CancelarNfeJob  (espelho NFe55/NFCe65)
 */
class CancelarNfseJob implements ShouldQueue
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

    public function handle(NfseCancelService $service): void
    {
        // ── 1. Carregar TransactionDocument sem global scope p/ cross-tenant
        //      guard explícito (defesa em profundidade ADR 0093) ────────────
        $doc = TransactionDocument::withoutGlobalScopes()->find($this->transactionDocumentId);

        if (! $doc) {
            Log::warning('CancelarNfseJob: TransactionDocument não encontrado', [
                'business_id'             => $this->businessId,
                'transaction_document_id' => $this->transactionDocumentId,
            ]);
            return;
        }

        if ((int) $doc->business_id !== $this->businessId) {
            Log::error('CancelarNfseJob: cross-tenant — business_id divergente', [
                'job_business_id'      => $this->businessId,
                'doc_business_id'      => $doc->business_id,
                'transaction_document' => $doc->id,
            ]);
            return; // drop silencioso — não retentar com mesma config
        }

        // ── 2. Só doc_type=nfse56 (defensivo — Cascade já filtra) ────────────
        if ($doc->doc_type !== TransactionDocument::DOC_NFSE56) {
            Log::warning('CancelarNfseJob: doc_type inesperado — pulando', [
                'transaction_document_id' => $doc->id,
                'doc_type'                => $doc->doc_type,
                'esperado'                => TransactionDocument::DOC_NFSE56,
            ]);
            return;
        }

        // ── 3. Idempotência nível TransactionDocument ───────────────────────
        if ($doc->status === TransactionDocument::STATUS_CANCELLED) {
            Log::info('CancelarNfseJob: TransactionDocument já cancelado — skip', [
                'transaction_document_id' => $doc->id,
            ]);
            return;
        }

        // ── 4. Resolver NfseEmissao via doc_class/doc_id ─────────────────────
        if ($doc->doc_class !== NfseEmissao::class) {
            Log::warning('CancelarNfseJob: doc_class inesperado — pulando', [
                'transaction_document_id' => $doc->id,
                'doc_class'               => $doc->doc_class,
                'esperado'                => NfseEmissao::class,
            ]);
            return;
        }

        $emissao = NfseEmissao::withoutGlobalScopes()->find($doc->doc_id);
        if (! $emissao) {
            Log::warning('CancelarNfseJob: NfseEmissao não encontrada', [
                'transaction_document_id' => $doc->id,
                'doc_id'                  => $doc->doc_id,
            ]);
            return;
        }

        // ── 5. Idempotência nível NfseEmissao ────────────────────────────────
        if ($emissao->status === NfseEmissao::STATUS_CANCELLED) {
            Log::info('CancelarNfseJob: NfseEmissao já cancelled — sincronizando TransactionDocument', [
                'transaction_document_id' => $doc->id,
                'nfse_emissao_id'         => $emissao->id,
            ]);
            $doc->update(['status' => TransactionDocument::STATUS_CANCELLED]);
            return;
        }

        // ── 6. Delegar pro service (que resolve driver per-município) ───────
        try {
            $evento = $service->cancelar(
                businessId:    $this->businessId,
                nfseEmissaoId: (int) $emissao->id,
                motivo:        $this->motivo,
            );

            // ── 7. Sucesso — atualiza TransactionDocument ────────────────────
            $doc->update(['status' => TransactionDocument::STATUS_CANCELLED]);

            Log::info('CancelarNfseJob: NFSe cancelada com sucesso', [
                'business_id'             => $this->businessId,
                'transaction_document_id' => $doc->id,
                'nfse_emissao_id'         => $emissao->id,
                'evento_id'               => $evento->id,
                'driver_key'              => $evento->driver_key,
            ]);
        } catch (\Throwable $e) {
            Log::error('CancelarNfseJob: falha — será retentado (tries=' . $this->tries . ')', [
                'business_id'             => $this->businessId,
                'transaction_document_id' => $doc->id,
                'nfse_emissao_id'         => $emissao->id,
                'attempt'                 => $this->attempts(),
                'error'                   => $e->getMessage(),
            ]);
            throw $e; // re-lança pra fila retentar
        }
    }
}
