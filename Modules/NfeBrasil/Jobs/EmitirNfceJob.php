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
use Modules\NfeBrasil\Events\NFCeAutorizada;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\NfeService;
use Throwable;

/**
 * US-NFE-002 · Job NFC-e (modelo 65) — emissão a partir de venda finalizada.
 *
 * **Fase 1** (PR #193): wire elétrico — listener venda → job + idempotência.
 * **Fase 2A** (PR #198): NfeService.emitirParaTransaction real (XML + SEFAZ).
 * **Fase 2B** (este PR): dispatch event `NFCeAutorizada` pós-cstat 100/150.
 * **Fase 2C** (futuro): Listener Broadcast Centrifugo `business.{id}.nfe-status`.
 *
 * Fluxo:
 *   1. Carrega Transaction; cross-tenant guard (tx.business_id == job.businessId).
 *   2. NfeService::emitirParaTransaction($tx, '65')
 *      → idempotência (UNIQUE constraint + check)
 *      → monta XML via sped-nfe builder
 *      → assina A1 via CertificadoService
 *      → envia SEFAZ (autoriza4 indSinc=1)
 *      → persiste cstat + chave_44 + status
 *      → gera DANFE PDF via DanfeService::salvar
 *   3. Se status='autorizada' → event(NFCeAutorizada) → listeners encadeados:
 *      - EnviarDanfeNFCePorEmail (PR #200): email pro consumidor (opt-in)
 *      - Fase 2C futura: BroadcastStatusNfce → tela POS atualiza tempo real.
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

    // PHP 8.4 + Laravel 13: Queueable trait declara `public $queue;` (sem default).
    // Re-declarar property aqui com QUALQUER default ('nfe' ou null) viola trait
    // composition strict rules ("definition differs and is considered incompatible").
    // Solução canônica: setar via $this->onQueue() no constructor.
    // $tries/$backoff são propriedades de ShouldQueue contract (não trait), seguros.
    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly int $businessId,
        public readonly int $transactionId,
    ) {
        $this->onQueue('nfe');
    }

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

            // Fase 2B: dispatch event quando SEFAZ autorizou. Mesmo pattern do
            // EmitirNFeAoReceberPagamento (NFe55). Listeners encadeados:
            //   - EnviarDanfeNFCePorEmail (PR #200): manda DANFE pro consumidor
            //     se Transaction.contact tem email (flag opt-in)
            //   - Fase 2C futura: BroadcastStatusNfce → Centrifugo channel
            //     `business.{id}.nfe-status` (US-NFE-002 AC #5)
            //
            // Status denegada/rejeitada NÃO dispara — ficam só no log + DB pra
            // dashboard mostrar. Event futuro NFCeRejeitada cobrirá esse caso.
            if ($emissao->status === 'autorizada') {
                event(new NFCeAutorizada($emissao));
            }
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
