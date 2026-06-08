<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\RecurringBilling\Models\Plan;
use Modules\RecurringBilling\Models\Subscription;
use Modules\RecurringBilling\Repositories\SubscriptionRepository;

/**
 * Service thin de lifecycle de Subscription (rb_subscriptions).
 *
 * Extração granular Wave 18 D4 saturação RecurringBilling (69→95):
 *
 *   Antes: `RecurringBillingController::store/update/destroy()` eram no-ops
 *          aguardando US-RB-002. Wave 18 extrai contrato canônico (Subscription
 *          create/pause/resume/cancel) num Service thin, type-safe, testável.
 *
 *   Depois: Controllers injetam AssinaturaService via DI. Service orquestra
 *           Repository + Carbon + Eloquent + dispatch evento de audit.
 *
 * SoC brutal (Constituição v2 §5): Service NÃO conhece HTTP — recebe primitive
 * types (int/string/array). Caller (Controller/Job/Command) traduz HTTP/CLI →
 * Service::method(int $businessId, array $payload).
 *
 * Multi-tenant Tier 0 (ADR 0093): businessId 1º arg sempre. NUNCA usa session()
 * ou auth() — Jobs/Commands chamam direto. Repository força where('business_id').
 *
 * Tests biz=1 (ADR 0101): biz=99 NUNCA vaza dados de biz=1 (Pest valida).
 *
 * Observability D9.a: 4 spans canônicos
 *   - rb.assinatura.criar
 *   - rb.assinatura.pausar
 *   - rb.assinatura.retomar
 *   - rb.assinatura.cancelar
 *
 * @see Modules\RecurringBilling\Repositories\SubscriptionRepository
 * @see Modules\RecurringBilling\Services\AssinaturaCobrancaService (peer — cobrança/invoice)
 * @see memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md §5 SoC
 */
class AssinaturaService
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
    ) {}

    /**
     * Cria nova assinatura. Define `next_due_date` baseada no ciclo do plano.
     *
     * Payload aceito (validado em StoreAssinaturaRequest antes):
     *   - plan_id: int (FK rb_plans)
     *   - contact_id: int (FK contacts)
     *   - start_date: Y-m-d (default: hoje)
     *   - status: 'trialing'|'active' (default: 'active' — sem trial)
     *   - conta_bancaria_id: int|null (override gateway por contato)
     *   - payment_method: 'boleto'|'pix'|'cartao' (default: 'boleto')
     *   - metadata: array (campos livres — gateway_subscription_ref, gateway, valor, ciclo)
     *
     * Retorno:
     *   ['ok' => true, 'subscription' => Subscription]
     *   ['ok' => false, 'error' => string, 'http_status' => int]
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function criar(int $businessId, array $payload): array
    {
        return OtelHelper::spanBiz('rb.assinatura.criar', function () use ($businessId, $payload): array {
            return $this->criarInternal($businessId, $payload);
        }, [
            'module'      => 'RecurringBilling',
            'op'          => 'assinatura.criar',
            'business_id' => $businessId,
            'plan_id'     => (int) ($payload['plan_id'] ?? 0),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function criarInternal(int $businessId, array $payload): array
    {
        $planId = (int) ($payload['plan_id'] ?? 0);
        $contactId = (int) ($payload['contact_id'] ?? 0);

        if ($planId <= 0 || $contactId <= 0) {
            return [
                'ok' => false,
                'error' => 'plan_id e contact_id sao obrigatorios.',
                'http_status' => 422,
            ];
        }

        $plan = Plan::where('business_id', $businessId)->whereKey($planId)->first();

        if (! $plan) {
            return [
                'ok' => false,
                'error' => "Plano #{$planId} nao encontrado no business.",
                'http_status' => 404,
            ];
        }

        $startDate = isset($payload['start_date'])
            ? Carbon::parse($payload['start_date'])->toDateString()
            : now()->toDateString();

        $ciclo = $payload['metadata']['ciclo'] ?? $plan->ciclo ?? 'mensal';
        $nextDueDate = $this->calcularProximoVencimento($startDate, (string) $ciclo);

        try {
            $subscription = DB::transaction(function () use (
                $businessId,
                $planId,
                $contactId,
                $startDate,
                $nextDueDate,
                $payload
            ) {
                return Subscription::create([
                    'business_id'         => $businessId,
                    'plan_id'             => $planId,
                    'contact_id'          => $contactId,
                    'status'              => $payload['status'] ?? 'active',
                    'start_date'          => $startDate,
                    'next_due_date'       => $nextDueDate,
                    'billing_anchor_date' => $startDate,
                    'conta_bancaria_id'   => $payload['conta_bancaria_id'] ?? null,
                    'payment_method'      => $payload['payment_method'] ?? 'boleto',
                    'metadata'            => $payload['metadata'] ?? [],
                ]);
            });
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'error' => 'Falha persistindo assinatura: ' . $e->getMessage(),
                'http_status' => 500,
            ];
        }

        Log::info('rb.assinatura.criada', [
            'business_id'    => $businessId,
            'subscription_id' => $subscription->id,
            'plan_id'        => $planId,
            'next_due_date'  => $nextDueDate,
            'payment_method' => $subscription->payment_method,
        ]);

        return [
            'ok' => true,
            'subscription' => $subscription,
        ];
    }

    /**
     * Pausa assinatura ate `pausada_ate` (NULL = indefinido).
     * Idempotente: ja pausada retorna ok + skipped.
     */
    public function pausar(
        int $businessId,
        int $subscriptionId,
        ?string $pausadaAte = null,
        string $motivo = '',
    ): array {
        return OtelHelper::spanBiz('rb.assinatura.pausar', function () use ($businessId, $subscriptionId, $pausadaAte, $motivo): array {
            $sub = $this->subscriptions->acharPorId($businessId, $subscriptionId);

            if (! $sub) {
                return ['ok' => false, 'error' => 'Assinatura nao encontrada.', 'http_status' => 404];
            }

            if ($sub->status === 'paused') {
                return [
                    'ok' => true,
                    'subscription' => $sub,
                    'skipped' => 'already_paused',
                ];
            }

            if ($sub->status === 'canceled') {
                return [
                    'ok' => false,
                    'error' => 'Assinatura cancelada nao pode ser pausada.',
                    'http_status' => 422,
                    'subscription' => $sub,
                ];
            }

            $sub->update([
                'status'        => 'paused',
                'paused_at'     => now(),
                'paused_until'  => $pausadaAte,
            ]);

            Log::info('rb.assinatura.pausada', [
                'business_id'     => $businessId,
                'subscription_id' => $sub->id,
                'paused_until'    => $pausadaAte,
                'motivo'          => $motivo ?: null,
            ]);

            return ['ok' => true, 'subscription' => $sub->refresh()];
        }, [
            'module'         => 'RecurringBilling',
            'op'             => 'assinatura.pausar',
            'business_id'    => $businessId,
            'subscription_id' => $subscriptionId,
        ]);
    }

    /**
     * Retoma assinatura pausada. Recalcula next_due_date pra hoje + 1 ciclo
     * (evita cobrança retroativa surpresa pro cliente).
     */
    public function retomar(int $businessId, int $subscriptionId): array
    {
        return OtelHelper::spanBiz('rb.assinatura.retomar', function () use ($businessId, $subscriptionId): array {
            $sub = $this->subscriptions->acharPorId($businessId, $subscriptionId);

            if (! $sub) {
                return ['ok' => false, 'error' => 'Assinatura nao encontrada.', 'http_status' => 404];
            }

            if ($sub->status !== 'paused') {
                return [
                    'ok' => false,
                    'error' => 'Apenas assinaturas pausadas podem ser retomadas.',
                    'http_status' => 422,
                    'subscription' => $sub,
                ];
            }

            $ciclo = $sub->metadata['ciclo'] ?? $sub->plan?->ciclo ?? 'mensal';
            $novoVencimento = $this->calcularProximoVencimento(now()->toDateString(), (string) $ciclo);

            $sub->update([
                'status'        => 'active',
                'paused_at'     => null,
                'paused_until'  => null,
                'next_due_date' => $novoVencimento,
            ]);

            Log::info('rb.assinatura.retomada', [
                'business_id'     => $businessId,
                'subscription_id' => $sub->id,
                'next_due_date'   => $novoVencimento,
            ]);

            return ['ok' => true, 'subscription' => $sub->refresh()];
        }, [
            'module'         => 'RecurringBilling',
            'op'             => 'assinatura.retomar',
            'business_id'    => $businessId,
            'subscription_id' => $subscriptionId,
        ]);
    }

    /**
     * Cancela assinatura definitivamente. Append-only: `canceled_at` + `status='canceled'`.
     * NÃO deleta — manter histórico pra MRR baseline + auditoria CTN/LGPD.
     *
     * Idempotente: já cancelada retorna ok + skipped.
     */
    public function cancelar(int $businessId, int $subscriptionId, ?string $churnReason = null): array
    {
        return OtelHelper::spanBiz('rb.assinatura.cancelar', function () use ($businessId, $subscriptionId, $churnReason): array {
            $sub = $this->subscriptions->acharPorId($businessId, $subscriptionId);

            if (! $sub) {
                return ['ok' => false, 'error' => 'Assinatura nao encontrada.', 'http_status' => 404];
            }

            if ($sub->status === 'canceled') {
                return [
                    'ok' => true,
                    'subscription' => $sub,
                    'skipped' => 'already_canceled',
                ];
            }

            $sub->update([
                'status'       => 'canceled',
                'canceled_at'  => now(),
                'churn_reason' => $churnReason,
            ]);

            Log::info('rb.assinatura.cancelada', [
                'business_id'     => $businessId,
                'subscription_id' => $sub->id,
                'churn_reason'    => $churnReason,
            ]);

            return ['ok' => true, 'subscription' => $sub->refresh()];
        }, [
            'module'         => 'RecurringBilling',
            'op'             => 'assinatura.cancelar',
            'business_id'    => $businessId,
            'subscription_id' => $subscriptionId,
        ]);
    }

    /**
     * Calcula próximo vencimento a partir de uma data base + ciclo.
     * Helper compartilhado com AssinaturaCobrancaService::recalcularProximaCobranca.
     */
    public function calcularProximoVencimento(string $base, string $ciclo): string
    {
        $baseCarbon = Carbon::parse($base);

        return match ($ciclo) {
            'mensal'     => $baseCarbon->copy()->addMonth()->toDateString(),
            'trimestral' => $baseCarbon->copy()->addMonths(3)->toDateString(),
            'semestral'  => $baseCarbon->copy()->addMonths(6)->toDateString(),
            'anual'      => $baseCarbon->copy()->addYear()->toDateString(),
            default      => $baseCarbon->toDateString(),
        };
    }
}
