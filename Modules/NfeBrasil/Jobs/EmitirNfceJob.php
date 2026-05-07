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

    public function handle(): void
    {
        Log::info('NFC-e emission requested', [
            'business_id'    => $this->businessId,
            'transaction_id' => $this->transactionId,
            'modelo'         => 65,
        ]);

        // Idempotência: re-dispatch da mesma venda = no-op silencioso.
        $existing = NfeEmissao::where('business_id', $this->businessId)
            ->where('transaction_id', $this->transactionId)
            ->where('modelo', 65)
            ->first();

        if ($existing !== null) {
            Log::info('NFC-e emissão já existe — idempotente, no-op', [
                'business_id'    => $this->businessId,
                'transaction_id' => $this->transactionId,
                'emissao_id'     => $existing->id,
                'status'         => $existing->status,
            ]);
            return;
        }

        $transaction = Transaction::find($this->transactionId);
        if (! $transaction || (int) $transaction->business_id !== $this->businessId) {
            Log::warning('NFC-e: transaction não encontrada ou cross-tenant — pulando', [
                'business_id'    => $this->businessId,
                'transaction_id' => $this->transactionId,
            ]);
            return;
        }

        try {
            // Placeholder enquanto Fase 2 não implementa NfeService::emitirParaTransaction.
            // Cria emissão `pendente` pra dashboard refletir intenção; Fase 2 atualiza
            // o mesmo registro com cstat/status real após SEFAZ retornar.
            $emissao = NfeEmissao::create([
                'business_id'    => $this->businessId,
                'transaction_id' => $this->transactionId,
                'modelo'         => 65,
                'serie'          => '1',
                'numero'         => 0, // Fase 2 popula via NfeService::proximoNumeroLocked
                'status'         => 'pendente',
                'valor_total'    => $transaction->final_total ?? 0,
                'metadata'       => ['fase' => '1-skeleton'],
            ]);

            Log::info('NFC-e emissão skeleton criada (Fase 1) — aguardando submissão SEFAZ Fase 2', [
                'business_id'    => $this->businessId,
                'transaction_id' => $this->transactionId,
                'emissao_id'     => $emissao->id,
            ]);

            // TODO Fase 2: chamar NfeService::emitirParaTransaction($transaction, 65, $emissao);
            //  — monta XML via sped-nfe builder
            //  — assina com cert A1 via CertificadoService
            //  — envia SEFAZ → atualiza $emissao->cstat + status + chave_44
            //  — gera DANFE PDF via sped-da
            //  — dispara event NFCeAutorizada se cstat 100
            //  — broadcast `business.{id}.nfe-status` via Reverb (US-NFE-002 AC #5)
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
