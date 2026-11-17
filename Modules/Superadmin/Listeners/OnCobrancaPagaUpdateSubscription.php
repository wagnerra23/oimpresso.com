<?php

declare(strict_types=1);

namespace Modules\Superadmin\Listeners;

use App\Business;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\PaymentGateway\Events\CobrancaPaga;
use Modules\Superadmin\Entities\Package;
use Modules\Superadmin\Entities\Subscription;
use Modules\Superadmin\Services\SubscriptionLifecycleService;

/**
 * Renova licença SaaS quando cobrança 'subscription_license' é paga.
 *
 * ADR 0170 Onda 5 SIMPLIFICADA — PaymentGateway entra como gateway adicional
 * em Superadmin::Subscription. Pattern imitado de
 * PesaPalController::pesaPalPaymentConfirmation 1:1, com fonte de trigger
 * trocada (evento canônico vs callback HTTP).
 *
 * Fluxo:
 *   webhook BCB → CobrancaPaga(origemType='subscription_license') →
 *   este listener → Subscription.status='approved' + dates set →
 *   desbloqueia business.officeimpresso_bloqueado=false se estava true →
 *   próximo /oauth/token Delphi do tenant passa.
 *
 * Multi-tenant Tier 0 (ADR 0093):
 *   - Cobranca.business_id = 1 (Wagner — dono da cobrança)
 *   - Subscription.business_id = <tenant_id> (cross-tenant Wagner-only,
 *     pattern legacy UltimatePOS documentado em Subscription.php:30)
 *   - Business update busca pelo id da Subscription. `App\Business` não tem escopo
 *     global, então não há escopo a tirar.
 *
 * Valor: aprova só se valorPagoCentavos == valor cobrado (valorEsperadoCentavos);
 * divergiu → log warning e não aprova.
 *
 * Idempotência: se Subscription.status === 'approved' → return (não duplica).
 * Status que o SubscriptionLifecycleService não aprova (cancelada, recusada…) →
 * log warning e não aprova nem desbloqueia.
 * Race: se Subscription.id no event não bate em DB → log + return (Wagner
 * reconcilia manualmente).
 */
final class OnCobrancaPagaUpdateSubscription
{
    public function handle(CobrancaPaga $event): void
    {
        if ($event->origemType !== 'subscription_license') {
            return;
        }

        if ($event->origemId === null) {
            Log::warning('[onda5] CobrancaPaga origem_type=subscription_license sem origem_id', [
                'cobranca_id' => $event->cobrancaId,
            ]);

            return;
        }

        $subscription = Subscription::find($event->origemId);
        if (!$subscription) {
            Log::error('[onda5] CobrancaPaga sem Subscription correspondente', [
                'cobranca_id' => $event->cobrancaId,
                'origem_id' => $event->origemId,
            ]);

            return;
        }

        if ($subscription->status === 'approved') {
            return;
        }

        $package = Package::find($subscription->package_id);
        if (!$package) {
            Log::error('[onda5] Subscription aponta pra Package inexistente', [
                'subscription_id' => $subscription->id,
                'package_id' => $subscription->package_id,
            ]);

            return;
        }

        // O valor pago tem de ser o valor cobrado. Sem esta conferência, qualquer
        // CobrancaPaga com a origem certa aprovava a assinatura — inclusive um pagamento
        // parcial ou um evento reprocessado de outra cobrança. Divergiu: não aprova, e o
        // log fica para conciliação manual.
        $esperadoCentavos = self::valorEsperadoCentavos($subscription, $package);
        if ($event->valorPagoCentavos !== $esperadoCentavos) {
            Log::warning('[onda5] CobrancaPaga com valor diferente do preço da assinatura — não aprovada', [
                'cobranca_id' => $event->cobrancaId,
                'subscription_id' => $subscription->id,
                'esperado_centavos' => $esperadoCentavos,
                'pago_centavos' => $event->valorPagoCentavos,
            ]);

            return;
        }

        $dates = $this->calcPackageDates((int) $subscription->business_id, $package);

        // A transição passa pelo SubscriptionLifecycleService (RUNBOOK do módulo): é ele
        // quem decide se o status atual aceita aprovação e grava tudo numa transação.
        // Spatie LogsActivity append-only registra a mudança (LGPD D7.b CC Art. 206).
        $aprovou = app(SubscriptionLifecycleService::class)->approve(
            $subscription,
            Carbon::parse($dates['start']),
            Carbon::parse($dates['end']),
            [
                'trial_end_date' => $dates['trial'],
                'paid_via' => 'paymentgateway_pix_automatico',
                'payment_transaction_id' => (string) $event->cobrancaId,
            ],
        );

        if (!$aprovou) {
            Log::warning('[onda5] CobrancaPaga para assinatura em status que não aceita aprovação', [
                'cobranca_id' => $event->cobrancaId,
                'subscription_id' => $subscription->id,
                'status' => $subscription->status,
            ]);

            return;
        }

        $business = Business::find($subscription->business_id);
        if ($business && (bool) $business->officeimpresso_bloqueado === true) {
            $business->officeimpresso_bloqueado = false;
            $business->save();
            Log::info('[onda5] business desbloqueado por pagamento SaaS', [
                'business_id' => $business->id,
                'cobranca_id' => $event->cobrancaId,
                'subscription_id' => $subscription->id,
            ]);
        }
    }

    /**
     * Centavos que a cobrança desta assinatura cobrou. Mesma regra das duas origens da
     * cobrança `subscription_license`: `package_price` da assinatura e, se ele for zero,
     * o preço do pacote (EmitTrialExpiredCobrancasCommand); o confirm do
     * SubscriptionController usa `package->price`, que o `_add_subscription` acabou de
     * copiar para `package_price`. Arredondamento igual ao das duas: round(preço × 100).
     */
    public static function valorEsperadoCentavos(Subscription $subscription, Package $package): int
    {
        $centavos = (int) round(((float) $subscription->package_price) * 100);
        if ($centavos <= 0) {
            $centavos = (int) round(((float) $package->price) * 100);
        }

        return $centavos;
    }

    /**
     * Calcula dates do package — pattern BaseController::_get_package_dates
     * inlined (BaseController é instance method, listener é stateless).
     *
     * @return array{start: string, end: string, trial: \Carbon\Carbon}
     */
    private function calcPackageDates(int $businessId, Package $package): array
    {
        $start = Subscription::end_date($businessId);
        if (!$start instanceof Carbon) {
            $start = Carbon::parse($start);
        }

        $output = ['start' => $start->toDateString(), 'end' => '', 'trial' => null];

        if ($package->interval === 'days') {
            $output['end'] = (clone $start)->addDays((int) $package->interval_count)->toDateString();
        } elseif ($package->interval === 'months') {
            $output['end'] = (clone $start)->addMonths((int) $package->interval_count)->toDateString();
        } elseif ($package->interval === 'years') {
            $output['end'] = (clone $start)->addYears((int) $package->interval_count)->toDateString();
        }

        $output['trial'] = (clone $start)->addDays((int) ($package->trial_days ?? 0));

        return $output;
    }
}
