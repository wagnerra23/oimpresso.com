<?php

declare(strict_types=1);

namespace Modules\Superadmin\Observers;

use App\Business;
use App\System;
use Illuminate\Support\Facades\Log;
use Modules\Superadmin\Entities\Package;
use Modules\Superadmin\Entities\Subscription;

/**
 * Auto-onboarding SaaS: ao criar Business, cria Subscription waiting com
 * Package default + trial. Cron diário emite cobrança PIX Automático quando
 * trial expira.
 *
 * ADR 0170 Onda 5.B SIMPLIFICADA — resolve Wagner pergunta 2026-05-19
 * "quando cadastrar cliente novo vai cair na cobrança recorrente?". HOJE
 * dependia de Wagner criar Subscription manual em /superadmin/business/{id}/subscriptions.
 *
 * Captura UI Superadmin + API Delphi simultaneamente (ambos chamam Business::create()).
 *
 * Tier 0 Multi-tenant (ADR 0093):
 *   - Subscription.business_id = <tenant> (cross-tenant Wagner-only, pattern
 *     legacy UltimatePOS Subscription.php:30)
 *
 * Configuração necessária (Wagner setup manual em System):
 *   - System property `default_saas_package_id` = ID do Package "Premium"
 *   - Sem esta property, observer faz no-op (graceful — não força auto-onboarding)
 *
 * Skip rules (defensive):
 *   - business_id=1 (Wagner não cobra ele mesmo)
 *   - Business já tem Subscription (re-cadastro / Eloquent observer reentrante)
 *   - System property ausente OU Package referenciado não existe / inativo
 *   - Env=demo (consistência com after_business_created pattern UltimatePOS)
 */
final class BusinessAutoSubscriptionObserver
{
    public function created(Business $business): void
    {
        if ($business->id === 1) {
            return;
        }

        if (config('app.env') === 'demo') {
            return;
        }

        $existingCount = Subscription::where('business_id', $business->id)->count();
        if ($existingCount > 0) {
            return;
        }

        $defaultPackageId = System::getProperty('default_saas_package_id');
        if (empty($defaultPackageId)) {
            Log::info('[onda5b] auto-subscription skip: default_saas_package_id ausente em system', [
                'business_id' => $business->id,
            ]);

            return;
        }

        $package = Package::active()->find((int) $defaultPackageId);
        if (!$package) {
            Log::warning('[onda5b] auto-subscription skip: default_saas_package_id referencia Package inexistente/inativo', [
                'business_id' => $business->id,
                'default_package_id' => $defaultPackageId,
            ]);

            return;
        }

        $trialDays = (int) ($package->trial_days ?? 0);
        $trialEndDate = $trialDays > 0 ? now()->addDays($trialDays) : now();

        try {
            $subscription = Subscription::create([
                'business_id'             => $business->id,
                'package_id'              => $package->id,
                'paid_via'                => 'paymentgateway_pix_automatico',
                'payment_transaction_id'  => null,
                'start_date'              => null,
                'end_date'                => null,
                'trial_end_date'          => $trialEndDate,
                'status'                  => 'waiting',
                'package_price'           => $package->price,
                'package_details'         => $this->buildPackageDetails($package),
                'created_id'              => 1, // Wagner — observer roda em contexto Business::create
            ]);

            Log::info('[onda5b] auto-subscription criada', [
                'business_id'      => $business->id,
                'subscription_id'  => $subscription->id,
                'package_id'       => $package->id,
                'trial_days'       => $trialDays,
                'trial_end_date'   => $trialEndDate->toDateString(),
            ]);
        } catch (\Throwable $e) {
            // Falha em criar Subscription NÃO impede criação do Business —
            // Wagner reconcilia manual via /superadmin/business/{id}/subscriptions
            Log::error('[onda5b] auto-subscription falhou (Business permanece criado)', [
                'business_id' => $business->id,
                'package_id'  => $package->id,
                'exception'   => $e->getMessage(),
            ]);
        }
    }

    private function buildPackageDetails(Package $package): array
    {
        $details = [
            'location_count' => $package->location_count,
            'user_count'     => $package->user_count,
            'product_count'  => $package->product_count,
            'invoice_count'  => $package->invoice_count,
            'name'           => $package->name,
        ];

        if (!empty($package->custom_permissions)) {
            foreach ($package->custom_permissions as $name => $value) {
                $details[$name] = $value;
            }
        }

        return $details;
    }
}
