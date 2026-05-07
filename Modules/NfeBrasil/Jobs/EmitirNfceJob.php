<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Jobs;

use App\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\NfeService;
use Throwable;

/**
 * US-NFE-002 fase 1 · Job NFC-e (modelo 65) — emissão a partir de venda finalizada.
 *
 * **Fase 1 (este PR):** wire elétrico — idempotência + log estruturado + skeleton.
 * **Fase 2 (PR futuro):** chamada real `nfephp-org/sped-nfe` + DANFE PDF + broadcast Echo.
 *
 * Fluxo (fase 1):
 *   1. Idempotência: NfeEmissao com (business_id, transaction_id, modelo=65) já existe?
 *      → return (sem duplicar fiscal). UNIQUE constraint em DB também garante.
 *   2. Carrega Transaction (UPos legado, schema int unsigned).
 *   3. Cria NfeEmissao com status='pendente' (placeholder).
 *   4. TODO Fase 2: NfeService::emitirParaTransaction($transaction, modelo=65)
 *      → monta XML via builder sped-nfe → assina com cert A1 → envia SEFAZ →
 *      atualiza emissao.status + cstat + chave_44.
 *
 * Falha de SEFAZ (timeout, cert vencido, etc.) NÃO derruba a venda — Throwable é
 * logado e re-throwado pra queue retry (3 tentativas, backoff 60s).
 *
 * Fila: `nfe` (mesma que NF-e modelo 55 — share queue, share retry policy).
 *
 * Pré-requisitos no business (validados pelo NfeService.emitirParaTransaction Fase 2):
 *   - Cert A1 ativo (`/nfe-brasil/configuracao/certificado`)
 *   - `nfe_business_configs.tributacao_default.ncm_default` configurado
 *   - Cliente (Contact) com `tax_number` (CPF — NFC-e B2C aceita "consumidor final")
 *
 * @see memory/requisitos/NfeBrasil/SPEC.md US-NFE-002
 * @see Modules/NfeBrasil/Listeners/EmitirNFeAoReceberPagamento.php (pattern NFe55)
 */
class EmitirNfceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'nfe';
    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly int $businessId,
        public readonly int $transactionId,
    ) {}

    /**
     * Fase 2A: chama `NfeService::emitirParaTransaction()` que monta XML +
     * assina A1 + envia SEFAZ + persiste em nfe_emissoes com cstat real.
     *
     * Idempotência ainda no service (UNIQUE constraint + check explícito).
     * Fase 1 do Job era só placeholder — agora chama service de verdade.
     */
    public function handle(NfeService $service): void
    {
        Log::info('NFC-e emission requested', [
            'business_id'    => $this->businessId,
            'transaction_id' => $this->transactionId,
            'modelo'         => 65,
        ]);

        $transaction = Transaction::find($this->transactionId);
        if (! $transaction || (int) $transaction->business_id !== $this->businessId) {
            Log::warning('NFC-e: transaction não encontrada ou cross-tenant — pulando', [
                'business_id'    => $this->businessId,
                'transaction_id' => $this->transactionId,
            ]);
            return;
        }

        try {
            // Fase 2A: chamada real ao NfeService.
            // Service cuida de: idempotência (UNIQUE), próximo número locked,
            // build XML, assinatura A1, envio SEFAZ, persistência cstat/chave_44.
            $emissao = $service->emitirParaTransaction($transaction, '65');

            Log::info('NFC-e emissão processada via NfeService', [
                'business_id'    => $this->businessId,
                'transaction_id' => $this->transactionId,
                'emissao_id'     => $emissao->id,
                'status'         => $emissao->status,
                'cstat'          => $emissao->cstat,
                'chave_44'       => $emissao->chave_44,
            ]);

            // TODO Fase 2B: dispatch event NFCeAutorizada se status='autorizada'
            // — listener gera DANFE PDF + envia email + broadcast Reverb
            //   `business.{id}.nfe-status` (US-NFE-002 AC #5)
        } catch (Throwable $e) {
            Log::error('NFC-e emission falhou', [
                'business_id'    => $this->businessId,
                'transaction_id' => $this->transactionId,
                'error'          => $e->getMessage(),
            ]);
            throw $e; // queue retenta (3 tries, backoff 60s)
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('NFC-e emission failed após retries', [
            'business_id'    => $this->businessId,
            'transaction_id' => $this->transactionId,
            'error'          => $e->getMessage(),
        ]);

        // TODO: notificar admin do business via mcp_inbox_notifications
    }
}
