<?php

declare(strict_types=1);

namespace Modules\Superadmin\Listeners;

use App\Business;
use Illuminate\Support\Facades\Log;
use Modules\PaymentGateway\Events\CobrancaVencida;
use Modules\Superadmin\Entities\Subscription;

/**
 * Bloqueia tenant SaaS quando cobrança 'subscription_license' vence sem pagamento.
 *
 * ADR 0170 Onda 5 SIMPLIFICADA — par do OnCobrancaPagaUpdateSubscription.
 * Wagner regra: "se não paga, bloqueia. paga, libera."
 *
 * Multi-tenant Tier 0:
 *   - Business update usa withoutGlobalScopes() (cross-tenant intencional)
 *   - Pattern documentado em Subscription.php:30 "Subscriptions tocam
 *     pagamento de TODOS tenants (cross-tenant intencional Wagner-only)"
 *
 * Enforcement canônico subsequente (não precisa código novo):
 *   - User::validateForPassportPasswordGrant rejeita /oauth/token quando
 *     officeimpresso_bloqueado=true → Delphi recebe HTTP 400 invalid_grant
 *   - OImpressoRegistroController retorna autorizado='N', message='Empresa bloqueada'
 *     quando registrar é chamado por business bloqueado
 *
 * RecurringBilling responsibility — RB faz smart retry 3 retentativas
 * antes de disparar CobrancaVencida (ADR 0170 §contratos). Quando chega
 * aqui, já houve 3 tentativas falhas. Bloqueio é decisão final.
 */
final class OnCobrancaVencidaBloqueaSubscription
{
    public function handle(CobrancaVencida $event): void
    {
        if ($event->origemType !== 'subscription_license') {
            return;
        }

        if ($event->origemId === null) {
            Log::warning('[onda5] CobrancaVencida origem_type=subscription_license sem origem_id', [
                'cobranca_id' => $event->cobrancaId,
            ]);

            return;
        }

        $subscription = Subscription::find($event->origemId);
        if (!$subscription) {
            Log::error('[onda5] CobrancaVencida sem Subscription correspondente', [
                'cobranca_id' => $event->cobrancaId,
                'origem_id' => $event->origemId,
            ]);

            return;
        }

        if ($subscription->status === 'declined') {
            return;
        }

        $subscription->status = 'declined';
        $subscription->save();

        $business = Business::withoutGlobalScopes()->find($subscription->business_id);
        if ($business && (bool) $business->officeimpresso_bloqueado === false) {
            $business->officeimpresso_bloqueado = true;
            $business->save();
            Log::warning('[onda5] business bloqueado por inadimplencia SaaS', [
                'business_id' => $business->id,
                'cobranca_id' => $event->cobrancaId,
                'subscription_id' => $subscription->id,
                'dias_vencido' => $event->diasVencido,
                'vencimento_original' => $event->vencimentoOriginal->format('Y-m-d'),
            ]);
        }
    }
}
