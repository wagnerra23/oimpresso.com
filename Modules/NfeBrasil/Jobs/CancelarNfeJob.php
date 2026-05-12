<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * US-SELL-034 — Cancelar NFe via evento SEFAZ.
 *
 * Disparado pelo side-effect CancelarVendaCascade pra cada TransactionDocument
 * tipo nfe* com status=authorized.
 *
 * Idempotência: se NFe já está cancelada na SEFAZ (consulta status pré-envio),
 * pula. Sem isso, retry em failure poderia tentar cancelar 2x.
 *
 * Multi-tenant Tier 0 (ADR 0093): $businessId no constructor.
 *
 * **Implementação SEFAZ**: stub atual loga. Wagner conecta NFePHP
 * Tools::sefazCancela em US separada (CASCADE-NFE-002 a criar).
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

    public function handle(): void
    {
        Log::info('CancelarNfeJob: handler invoked (stub)', [
            'business_id' => $this->businessId,
            'transaction_document_id' => $this->transactionDocumentId,
            'motivo' => $this->motivo,
            'todo' => 'integrar NFePHP Tools::sefazCancela + atualizar TransactionDocument.status=cancelled (US CASCADE-NFE-002)',
        ]);

        // TODO US CASCADE-NFE-002:
        //   1. Carregar TransactionDocument
        //   2. Resolver NfeEmissao via doc_class+doc_id
        //   3. Verificar idempotência (status=cancelada já?)
        //   4. CertificadoService::carregarParaSefaz
        //   5. Tools::sefazCancela(chave, motivo, nProtocolo)
        //   6. Atualizar NfeEmissao.status=cancelada + TransactionDocument.status=cancelled
    }
}
