<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\PaymentGateway\Events\CobrancaPaga;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Models\GatewayWebhookEvent;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Webhook\CobrancaWebhookResolver;

/**
 * ADR 0170 Onda 4e — Retry scheduled de webhook órfão.
 *
 * Race condition coberta: WebhookProcessor persiste em gateway_webhook_events
 * (idempotência at-DB) + retorna 200, MAS o dispatch real do CobrancaPaga
 * acontece em outro fluxo. Se o webhook chega ANTES da Cobranca ser gravada
 * (emissão ainda em curso), o event row fica com `processed_at IS NULL` +
 * `cobranca_id NULL` ou apontando pra nada — orphan.
 *
 * Estratégia:
 *   - Janela: created_at > now()-24h (mais antigo é tarde demais — Wagner reconcilia)
 *   - Espera mínima: created_at < now()-1h (dá tempo do fluxo original gravar Cobranca)
 *   - Ordem cronológica + limit 50 por run (evita run lento travar próximo tick 5min)
 *
 * Per evento órfão:
 *   1. Se `cobranca_id` NOT NULL E Cobranca exists → re-dispatch evento canônico
 *      (CobrancaPaga só se evento matches paid/received/confirmed regex conservador)
 *      → marca processed_at + error_message=null + log info `orphan_replayed`
 *   2. Se `cobranca_id` NULL OU Cobranca não encontrada → log warn `still_orphan`,
 *      processed_at PERMANECE NULL pra próximo run tentar até cutoff 24h
 *   3. Se evento NÃO matches mapping conservador (ex: cob.created/error genérico) →
 *      log warn + marca processed_at sem dispatch (Wagner reconcilia manual)
 *
 * Multi-tenant Tier 0 (ADR 0093): Job roda em background SEM sessão →
 * `withoutGlobalScopes()` consciente + log explícito do business_id por evento.
 *
 * Mapping conservador event_type → CobrancaPaga (MVP):
 *   /paid/i, /received/i, /confirmed/i, /payment_received/i, /cob\.paid/i
 *
 * NÃO dispatcha CobrancaErro automaticamente — risk de side-effects em
 * listeners ainda não-idempotentes. Wagner manualmente reconcilia errors via
 * inspeção do gateway_webhook_events.payload.
 *
 * Idempotência: re-rodar mesmo evento já processed_at != NULL → query exclui
 * automaticamente. Listener CobrancaPaga (OnCobrancaPagaCreateFinanceiroTitulo
 * Onda 5) deve ser idempotente — se não, dispatch 2x cria 2 Titulos. Risco
 * catalogado no return-report.
 *
 * Custo: zero LLM, zero HTTP externo. Pure SQL + dispatch interno → latência
 * ~10-50ms por evento × 50 max = ~2.5s worst case.
 */
class RetryOrphanWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Padrões de evento que mapeiam pra CobrancaPaga (regex case-insensitive).
     *
     * Conservador propositalmente: prefere "deixa órfão pra Wagner ver"
     * a "dispatcha falso-positivo que cria Titulo errado".
     */
    private const PAID_EVENT_PATTERNS = [
        '/paid/i',
        '/received/i',
        '/confirmed/i',
        '/payment_received/i',
        '/cob\.paid/i',
        '/pix_recebido/i',
    ];

    public int $tries = 1; // cron re-roda em 5min — sem ponto fazer retry exponential interno

    public int $timeout = 120; // 2min cap (cron everyFiveMinutes com withoutOverlapping 10min)

    public function __construct()
    {
        // $queue é property do trait Queueable — atribuímos no constructor
        // pra evitar conflito de redeclaração de property (PHP 8.4 strict).
        $this->onQueue('paymentgateway');
    }

    public function handle(): void
    {
        // Janela: órfãos entre 1h e 24h (espera fluxo original + cutoff manual)
        // SUPERADMIN: cron job worker roda sem sessão web; varre webhook events órfãos de TODOS os tenants e re-dispatcha por business_id explícito de cada linha.
        $orphans = GatewayWebhookEvent::query()
            ->withoutGlobalScopes() // Job sem sessão — ADR 0093 explicit
            ->whereNull('processed_at')
            ->where('created_at', '>', now()->subDay())
            ->where('created_at', '<', now()->subHour())
            ->orderBy('created_at')
            ->limit(50)
            ->get();

        if ($orphans->isEmpty()) {
            Log::info('paymentgateway.webhook.orphan_scan', [
                'found' => 0,
                'window' => '1h..24h',
            ]);

            return;
        }

        $replayed = 0;
        $stillOrphan = 0;
        $markedNoDispatch = 0;
        $resolver = app(CobrancaWebhookResolver::class);

        foreach ($orphans as $orphan) {
            $this->processOrphan($orphan, $resolver, $replayed, $stillOrphan, $markedNoDispatch);
        }

        Log::info('paymentgateway.webhook.orphan_scan', [
            'found' => $orphans->count(),
            'replayed' => $replayed,
            'still_orphan' => $stillOrphan,
            'marked_no_dispatch' => $markedNoDispatch,
            'window' => '1h..24h',
        ]);
    }

    private function processOrphan(
        GatewayWebhookEvent $orphan,
        CobrancaWebhookResolver $resolver,
        int &$replayed,
        int &$stillOrphan,
        int &$markedNoDispatch,
    ): void {
        $businessId = (int) $orphan->business_id;
        $evento = (string) $orphan->evento;

        // Caso 1: ainda sem cobranca_id → re-tenta o linkage AGORA (race: o
        // webhook chegou antes da emissão gravar a Cobranca). Se resolver, grava
        // cobranca_id e segue pro fluxo de dispatch; senão fica órfão pro próximo
        // run (até cutoff 24h). Antes este branch DESISTIA na hora — por isso a
        // quitação era inalcançável quando o linkage do receive-time falhava.
        if (! $orphan->cobranca_id) {
            $cobrancaId = $this->reresolve($orphan, $resolver, $businessId);
            if ($cobrancaId === null) {
                $this->logStillOrphan($orphan, $businessId, 'cobranca_id_null');
                $stillOrphan++;

                return;
            }
            $orphan->update(['cobranca_id' => $cobrancaId, 'error_message' => null]);
            $orphan->cobranca_id = $cobrancaId;
        }

        // SUPERADMIN: job worker sem sessão; resolve a Cobranca do evento órfão filtrando pelo business_id derivado da própria linha do webhook.
        $cobranca = Cobranca::withoutGlobalScopes()
            ->where('id', $orphan->cobranca_id)
            ->where('business_id', $businessId)
            ->first();

        if (! $cobranca) {
            $this->logStillOrphan($orphan, $businessId, 'cobranca_not_found');
            $stillOrphan++;

            return;
        }

        // Caso 2: evento NÃO matches mapping conservador → marca processed_at sem dispatch
        if (! $this->isPaidEvent($evento)) {
            $orphan->update([
                'processed_at' => now(),
                'error_message' => 'orphan_event_not_mapped_to_dispatch: ' . substr($evento, 0, 100),
            ]);
            Log::warning('paymentgateway.webhook.orphan_marked_no_dispatch', [
                'webhook_row_id' => $orphan->id,
                'business_id' => $businessId,
                'gateway_key' => $orphan->gateway_key,
                'evento' => $evento,
                'cobranca_id' => $cobranca->id,
                'reason' => 'event_type_not_in_paid_patterns',
            ]);
            $markedNoDispatch++;

            return;
        }

        // Caso 3: dispatch CobrancaPaga + marca processed
        $this->replay($orphan, $cobranca, $businessId);
        $replayed++;
    }

    private function replay(
        GatewayWebhookEvent $orphan,
        Cobranca $cobranca,
        int $businessId,
    ): void {
        $pagaEm = $cobranca->paga_em
            ? \DateTimeImmutable::createFromMutable($cobranca->paga_em->toDateTime())
            : new \DateTimeImmutable();

        $valorPagoCentavos = $cobranca->valor_pago_centavos ?? $cobranca->valor_centavos ?? 0;

        event(new CobrancaPaga(
            cobrancaId: (int) $cobranca->id,
            businessId: (int) $cobranca->business_id,
            valorPagoCentavos: (int) $valorPagoCentavos,
            pagaEm: $pagaEm,
            formaPagamento: (string) ($cobranca->forma_pagamento ?? 'pix'),
            occurredAt: new \DateTimeImmutable(),
            payerCpfCnpj: $cobranca->payer_cpf_cnpj,
            origemType: $cobranca->origem_type,
            origemId: $cobranca->origem_id,
        ));

        $orphan->update([
            'processed_at' => now(),
            'error_message' => null,
        ]);

        Log::info('paymentgateway.webhook.orphan_replayed', [
            'webhook_row_id' => $orphan->id,
            'business_id' => $businessId,
            'gateway_key' => $orphan->gateway_key,
            'evento' => $orphan->evento,
            'cobranca_id' => $cobranca->id,
            'replay_delay_minutes' => (int) now()->diffInMinutes($orphan->created_at),
        ]);
    }

    /**
     * Re-tenta resolver a Cobranca de um órfão (cobranca_id NULL) a partir do
     * payload guardado. Carrega a credencial de payment_gateway_credential_id
     * (o driver precisa dela) e delega pro CobrancaWebhookResolver.
     *
     * Retorna o id da Cobranca, ou null se não resolver (sem credencial, gateway
     * desconhecida, payload sem id externo, ou Cobranca ainda inexistente).
     * Defensivo: qualquer erro vira null + log (nunca derruba o run inteiro).
     */
    private function reresolve(
        GatewayWebhookEvent $orphan,
        CobrancaWebhookResolver $resolver,
        int $businessId,
    ): ?int {
        $credentialId = $orphan->payment_gateway_credential_id;
        if (! $credentialId) {
            return null; // sem credencial não dá pra resolver (driver precisa dela)
        }

        // SUPERADMIN: cron worker sem sessão; carrega a credencial scopando pelo
        // business_id da própria linha do webhook (ADR 0093).
        $credential = PaymentGatewayCredential::withoutGlobalScopes()
            ->where('id', $credentialId)
            ->where('business_id', $businessId)
            ->first();

        if (! $credential) {
            return null;
        }

        try {
            return $resolver->resolve(
                $businessId,
                (string) $orphan->gateway_key,
                (array) ($orphan->payload ?? []),
                $credential,
            )?->id;
        } catch (\Throwable $e) {
            Log::warning('paymentgateway.webhook.orphan_reresolve_failed', [
                'webhook_row_id' => $orphan->id,
                'business_id'    => $businessId,
                'gateway_key'    => $orphan->gateway_key,
                'error'          => substr($e->getMessage(), 0, 200),
            ]);

            return null;
        }
    }

    private function logStillOrphan(
        GatewayWebhookEvent $orphan,
        int $businessId,
        string $reason,
    ): void {
        // NÃO altera processed_at — próximo run tenta de novo até cutoff 24h
        $orphan->update([
            'error_message' => 'still_orphan: ' . $reason,
        ]);

        Log::warning('paymentgateway.webhook.still_orphan', [
            'webhook_row_id' => $orphan->id,
            'business_id' => $businessId,
            'gateway_key' => $orphan->gateway_key,
            'evento' => $orphan->evento,
            'reason' => $reason,
            'age_minutes' => (int) now()->diffInMinutes($orphan->created_at),
        ]);
    }

    private function isPaidEvent(string $evento): bool
    {
        foreach (self::PAID_EVENT_PATTERNS as $pattern) {
            if (preg_match($pattern, $evento) === 1) {
                return true;
            }
        }

        return false;
    }
}
