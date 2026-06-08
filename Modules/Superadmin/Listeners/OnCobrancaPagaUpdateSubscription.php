<?php

declare(strict_types=1);

namespace Modules\Superadmin\Listeners;

use App\Business;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\PaymentGateway\Events\CobrancaPaga;
use Modules\Superadmin\Entities\Package;
use Modules\Superadmin\Entities\Subscription;

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
 *   - Business update usa withoutGlobalScopes() pra cross-tenant explícito.
 *
 * Idempotência: se Subscription.status === 'approved' → return (não duplica).
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

        $dates = $this->calcPackageDates((int) $subscription->business_id, $package);

        $subscription->status = 'approved';
        $subscription->start_date = $dates['start'];
        $subscription->end_date = $dates['end'];
        $subscription->trial_end_date = $dates['trial'];
        $subscription->paid_via = 'paymentgateway_pix_automatico';
        $subscription->payment_transaction_id = (string) $event->cobrancaId;
        $subscription->save();
        // Spatie LogsActivity append-only registra mudança (LGPD D7.b CC Art. 206)

        $business = Business::withoutGlobalScopes()->find($subscription->business_id);
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
