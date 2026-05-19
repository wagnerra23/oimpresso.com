<?php

namespace Modules\Superadmin\Http\Controllers;

use App\System;
use Illuminate\Routing\Controller;
use Modules\Superadmin\Entities\Package;
use Modules\Superadmin\Entities\Subscription;
use Modules\Superadmin\Notifications\NewSubscriptionNotification;
use Modules\Superadmin\Support\RedactsPiiInLogs;
use Notification;

class BaseController extends Controller
{
    use RedactsPiiInLogs;

    /**
     * Alias backward-compat — método inline do controller usa snake_case por
     * convenção UltimatePOS herdada. Delegação pro trait centraliza redaction.
     * LGPD Tier 0 D7.a (Wave 11).
     */
    protected function _log_emergency_redacted(\Throwable $e, string $context = ''): void
    {
        $this->logEmergencyRedacted($e, $context);
    }

    /**
     * Returns the list of all configured payment gateway
     *
     * @return Response
     */
    public function _payment_gateways()
    {
        $gateways = [];

        //Check if stripe is configured or not
        if (env('STRIPE_PUB_KEY') && env('STRIPE_SECRET_KEY')) {
            $gateways['stripe'] = 'Stripe';
        }

        //Check if paypal is configured or not
        if ((env('PAYPAL_SANDBOX_API_USERNAME') && env('PAYPAL_SANDBOX_API_PASSWORD') && env('PAYPAL_SANDBOX_API_SECRET')) || (env('PAYPAL_LIVE_API_USERNAME') && env('PAYPAL_LIVE_API_PASSWORD') && env('PAYPAL_LIVE_API_SECRET'))) {
            $gateways['paypal'] = 'PayPal';
        }

        //Check if Razorpay is configured or not
        if ((env('RAZORPAY_KEY_ID') && env('RAZORPAY_KEY_SECRET'))) {
            $gateways['razorpay'] = 'Razor Pay';
        }

        //Check if Pesapal is configured or not
        if ((config('pesapal.consumer_key') && config('pesapal.consumer_secret'))) {
            $gateways['pesapal'] = 'PesaPal';
        }

        // ADR 0170 Onda 5 — PaymentGateway entra como gateway adicional pra
        // mensalidade SaaS Oimpresso. Ativo SE há credencial BCB ativa em biz=1
        // E há conta bancária Financeiro vinculada. Pattern: 6º gateway.
        if ($this->isPaymentGatewayPixAutomaticoConfigured()) {
            $gateways['paymentgateway_pix_automatico'] = 'PIX Automático BCB';
        }

        //check if Paystack is configured or not
        $system = System::getCurrency();
        if (in_array($system->country, ['Nigeria', 'Ghana']) && (config('paystack.publicKey') && config('paystack.secretKey'))) {
            $gateways['paystack'] = 'Paystack';
        }

        //check if Flutterwave is configured or not
        if (env('FLUTTERWAVE_PUBLIC_KEY') && env('FLUTTERWAVE_SECRET_KEY') && env('FLUTTERWAVE_ENCRYPTION_KEY')) {
            $gateways['flutterwave'] = 'Flutterwave';
        }

        // check if offline payment is enabled or not
        $is_offline_payment_enabled = System::getProperty('enable_offline_payment');

        if ($is_offline_payment_enabled) {
            $gateways['offline'] = 'Offline';
        }

        return $gateways;
    }

    /**
     * Enter details for subscriptions
     *
     * @return object
     */
    public function _add_subscription($business_id, $package, $gateway, $payment_transaction_id, $user_id, $is_superadmin = false)
    {
        if (! is_object($package)) {
            $package = Package::active()->find($package);
        }

        $subscription = ['business_id' => $business_id,
            'package_id' => $package->id,
            'paid_via' => $gateway,
            'payment_transaction_id' => $payment_transaction_id,
        ];

        if ($package->price != 0 && (in_array($gateway, ['offline', 'pesapal']) && ! $is_superadmin)) {
            //If offline then dates will be decided when approved by superadmin
            $subscription['start_date'] = null;
            $subscription['end_date'] = null;
            $subscription['trial_end_date'] = null;
            $subscription['status'] = 'waiting';
        } else {
            $dates = $this->_get_package_dates($business_id, $package);

            $subscription['start_date'] = $dates['start'];
            $subscription['end_date'] = $dates['end'];
            $subscription['trial_end_date'] = $dates['trial'];
            $subscription['status'] = 'approved';
        }

        $subscription['package_price'] = $package->price;
        $subscription['package_details'] = [
            'location_count' => $package->location_count,
            'user_count' => $package->user_count,
            'product_count' => $package->product_count,
            'invoice_count' => $package->invoice_count,
            'name' => $package->name,
        ];
        //Custom permissions.
        if (! empty($package->custom_permissions)) {
            foreach ($package->custom_permissions as $name => $value) {
                $subscription['package_details'][$name] = $value;
            }
        }

        $subscription['created_id'] = $user_id;
        $subscription = Subscription::create($subscription);

        if (! $is_superadmin) {
            $email = System::getProperty('email');
            $is_notif_enabled = System::getProperty('enable_new_subscription_notification');

            if (! empty($email) && $is_notif_enabled == 1) {
                Notification::route('mail', $email)
                ->notify(new NewSubscriptionNotification($subscription));
            }
        }

        return $subscription;
    }

    /**
     * Verifica se PaymentGateway PIX Automático BCB está configurado pra biz=1.
     *
     * ADR 0170 Onda 5 — requer (1) módulo PaymentGateway enabled, (2) credencial
     * BCB ativa em biz=1, (3) ContaBancaria Financeiro vinculada à credencial.
     * Falta de qualquer 1 → gateway omitido da listagem (Wagner vê só Pesapal/Stripe/etc).
     */
    protected function isPaymentGatewayPixAutomaticoConfigured(): bool
    {
        if (!class_exists(\Modules\PaymentGateway\Models\PaymentGatewayCredential::class)) {
            return false;
        }

        try {
            // Canon Onda 5 (Wagner 2026-05-19) — credencial BCB ativa que TENHA
            // conta_bancaria_id vinculada via wizard step 3. Direção UNIFICADA —
            // não depende mais de FK reverso em fin_contas_bancarias.
            return \Modules\PaymentGateway\Models\PaymentGatewayCredential::query()
                ->withoutGlobalScopes()
                ->where('business_id', 1)
                ->where('gateway_key', 'bcb_pix')
                ->where('ativo', true)
                ->whereNotNull('conta_bancaria_id')
                ->exists();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[onda5] isPaymentGatewayPixAutomaticoConfigured threw', [
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * The function returns the start/end/trial end date for a package.
     *
     * @param  int  $business_id
     * @param  object  $package
     * @return array
     */
    protected function _get_package_dates($business_id, $package)
    {
        $output = ['start' => '', 'end' => '', 'trial' => ''];

        //calculate start date
        $start_date = Subscription::end_date($business_id);
        $output['start'] = $start_date->toDateString();

        //Calculate end date
        if ($package->interval == 'days') {
            $output['end'] = $start_date->addDays($package->interval_count)->toDateString();
        } elseif ($package->interval == 'months') {
            $output['end'] = $start_date->addMonths($package->interval_count)->toDateString();
        } elseif ($package->interval == 'years') {
            $output['end'] = $start_date->addYears($package->interval_count)->toDateString();
        }

        $output['trial'] = $start_date->addDays($package->trial_days);

        return $output;
    }
}
