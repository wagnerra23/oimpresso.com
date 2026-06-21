<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Models\InterWebhookLog;
use Modules\PaymentGateway\Services\ReconciliarCobrancaService;

/**
 * US-FIN-032 (Onda 26) — Worker que processa webhook PIX Inter recebido.
 *
 * Fluxo:
 *   1. Carrega InterWebhookLog pelo ID
 *   2. Busca Cobranca por gateway_external_id (txid) — mesma credencial
 *   3. Atualiza Cobranca.status='paga' + paga_em + valor_pago_centavos
 *   4. Dispara evento `CobrancaPaga` (já há listener canon
 *      `OnCobrancaPagaCreateFinanceiroTitulo` que cria Titulo + Baixa pra biz=1)
 *   5. Para tituloS já existentes vinculados (origem_id=cobranca.id),
 *      força status='quitado' via FsmAuthorizationFlag se titulo tem
 *      pipeline FSM, ou Titulo::update direto se não tem
 *   6. Marca InterWebhookLog.status='processed' + processed_at
 *
 * Multi-tenant Tier 0 (ADR 0093): $businessId passado no constructor
 * (session() não funciona em queue worker).
 *
 * FSM Pipeline (ADR 0143): NUNCA $titulo->current_stage_id = X direto.
 * Use FsmAuthorizationFlag::mark() singleton consume-once + Titulo::update
 * pra trait GuardsFsmTransitions deixar passar. Se titulo não tem pipeline
 * FSM ativa (current_stage_id NULL), fallback é update direto de status
 * sem mexer em stage_id.
 *
 * Idempotência: webhook duplicado já é barrado em InterWebhookLog UNIQUE
 * (credential_id, txid). Se job re-rodar pela mesma linha (retry queue),
 * status='processed' já existente faz cedo-return.
 *
 * Failure mode: throw → Laravel queue retry (default 3 tentativas).
 * Status='erro_outro' marcado no catch antes de re-throw.
 */
class ProcessarWebhookPixInterJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public string $queue = 'paymentgateway';

    public int $tries = 3;

    public int $backoff = 60; // 1min entre retries

    public function __construct(
        public readonly int $interWebhookLogId,
        public readonly int $businessId, // ADR 0093 — propagação obrigatória
    ) {
    }

    public function handle(ReconciliarCobrancaService $reconciliador): void
    {
        // SUPERADMIN: queue worker não tem session(); carrega o InterWebhookLog filtrando pelo business_id propagado no constructor (ADR 0093).
        $log = InterWebhookLog::withoutGlobalScopes()
            ->where('id', $this->interWebhookLogId)
            ->where('business_id', $this->businessId)
            ->first();

        if (! $log) {
            Log::warning('paymentgateway.inter.worker.log_not_found', [
                'inter_webhook_log_id' => $this->interWebhookLogId,
                'business_id'          => $this->businessId,
            ]);
            return;
        }

        // Idempotência: job re-rodado pela mesma linha já processada → skip
        if ($log->status === 'processed') {
            return;
        }

        try {
            // Resolve Cobranca pelo txid + credencial (mesmo gateway_external_id)
            // SUPERADMIN: queue worker sem session(); resolve a Cobranca pelo business_id propagado no constructor.
            $cobranca = Cobranca::withoutGlobalScopes()
                ->where('business_id', $this->businessId)
                ->where('payment_gateway_credential_id', $log->payment_gateway_credential_id)
                ->where('gateway_external_id', $log->txid)
                ->first();

            if (! $cobranca) {
                $this->marcarTituloNaoEncontrado($log);
                return;
            }

            DB::transaction(function () use ($log, $cobranca, $reconciliador): void {
                $valorPagoCentavos = $log->valor_centavos ?? $cobranca->valor_centavos;
                $pagaEm = $log->data_pagamento
                    ? \DateTimeImmutable::createFromMutable($log->data_pagamento->toDateTime())
                    : new \DateTimeImmutable();

                // Reconciliação canônica (mesma usada pelo polling de fallback):
                // marca Cobranca paga + quita títulos vinculados + dispara CobrancaPaga
                // (listener OnCobrancaPagaCreateFinanceiroTitulo cria Titulo + Baixa biz=1).
                $reconciliador->marcarPaga(
                    cobranca: $cobranca,
                    businessId: $this->businessId,
                    valorPagoCentavos: $valorPagoCentavos,
                    pagaEm: $pagaEm,
                    formaPagamento: 'pix',
                );

                // Marca log processado
                $log->update([
                    'cobranca_id'  => $cobranca->id,
                    'status'       => 'processed',
                    'processed_at' => now(),
                ]);
            });

            Log::info('paymentgateway.inter.worker.processed', [
                'inter_webhook_log_id' => $log->id,
                'business_id'          => $this->businessId,
                'cobranca_id'          => $cobranca->id,
                'txid'                 => $log->txid,
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'status'        => 'erro_outro',
                'error_message' => substr($e->getMessage(), 0, 500),
                'processed_at'  => now(),
            ]);
            Log::error('paymentgateway.inter.worker.error', [
                'inter_webhook_log_id' => $log->id,
                'business_id'          => $this->businessId,
                'error'                => substr($e->getMessage(), 0, 200),
            ]);
            throw $e;
        }
    }

    private function marcarTituloNaoEncontrado(InterWebhookLog $log): void
    {
        $log->update([
            'status'       => 'titulo_nao_encontrado',
            'processed_at' => now(),
        ]);
        Log::warning('paymentgateway.inter.worker.cobranca_not_found', [
            'inter_webhook_log_id' => $log->id,
            'business_id'          => $this->businessId,
            'txid'                 => $log->txid,
            'credential_id'        => $log->payment_gateway_credential_id,
        ]);
        // 200 OK pro Inter (NÃO erro — Wagner reconcilia depois).
        // Listener OnCobrancaPagaCreateFinanceiroTitulo NÃO dispara
        // (sem Cobranca ainda — pode ter chegado antes da emissão registrar).
    }
}
