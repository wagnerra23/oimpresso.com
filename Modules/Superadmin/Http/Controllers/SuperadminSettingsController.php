<?php

namespace Modules\Superadmin\Http\Controllers;

use App\System;
use App\Utils\BusinessUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Superadmin\Support\RedactsPiiInLogs;

class SuperadminSettingsController extends Controller
{
    use RedactsPiiInLogs;

    /**
     * All Utils instance.
     */
    protected $businessUtil;

    protected $mailDrivers;

    protected $backupDisk;

    public function __construct(BusinessUtil $businessUtil)
    {
        $this->businessUtil = $businessUtil;

        $this->mailDrivers = [
            'smtp' => 'SMTP',
            'sendmail' => 'Sendmail',
            'mailgun' => 'Mailgun',
            'mandrill' => 'Mandrill',
            'ses' => 'SES',
            'sparkpost' => 'Sparkpost',
        ];

        $this->backupDisk = ['local' => 'Local', 'dropbox' => 'Dropbox'];
    }

    /**
     * Chaves do `.env` que são SEGREDO (senha, chave secreta, token). Nunca saem para a tela nem
     * para as props Inertia — a tela só sabe se estão definidas — e só são regravadas quando o
     * campo chega preenchido. Thread Superadmin/05 (2/2) · RUNBOOK-configuracoes.
     */
    public const SEGREDOS = [
        'MAIL_PASSWORD', 'STRIPE_SECRET_KEY',
        'PAYPAL_SANDBOX_API_PASSWORD', 'PAYPAL_SANDBOX_API_SECRET',
        'PAYPAL_LIVE_API_PASSWORD', 'PAYPAL_LIVE_API_SECRET',
        'DROPBOX_ACCESS_TOKEN', 'RAZORPAY_KEY_SECRET', 'PESAPAL_CONSUMER_SECRET',
        'PUSHER_APP_SECRET', 'PAYSTACK_SECRET_KEY',
        'FLUTTERWAVE_SECRET_KEY', 'FLUTTERWAVE_ENCRYPTION_KEY', 'MAPBOX_ACCESS_TOKEN',
    ];

    /** Chaves do `.env` que a tela mostra em claro (não são segredo). */
    private const ENV_VISIVEIS = [
        'APP_NAME', 'APP_TITLE', 'APP_LOCALE', 'ALLOW_REGISTRATION', 'GOOGLE_MAP_API_KEY',
        'MAIL_MAILER', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_ENCRYPTION',
        'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME', 'STRIPE_PUB_KEY', 'PAYPAL_MODE',
        'PAYPAL_SANDBOX_API_USERNAME', 'PAYPAL_LIVE_API_USERNAME', 'BACKUP_DISK',
        'RAZORPAY_KEY_ID', 'PESAPAL_CONSUMER_KEY', 'PESAPAL_LIVE', 'PUSHER_APP_ID',
        'PUSHER_APP_KEY', 'PUSHER_APP_CLUSTER', 'PAYSTACK_PUBLIC_KEY', 'FLUTTERWAVE_PUBLIC_KEY',
    ];

    /** Chaves da tabela `system` que a tela edita — lista fechada: `system` guarda outras coisas. */
    private const SISTEMA = [
        'app_currency_id', 'invoice_business_name', 'email', 'invoice_business_landmark',
        'invoice_business_zip', 'invoice_business_state', 'invoice_business_city',
        'invoice_business_country', 'package_expiry_alert_days', 'superadmin_register_tc',
        'welcome_email_subject', 'welcome_email_body', 'additional_js', 'additional_css',
        'offline_payment_details', 'enable_business_based_username', 'superadmin_enable_register_tc',
        'allow_email_settings_to_businesses', 'enable_new_business_registration_notification',
        'enable_new_subscription_notification', 'enable_welcome_email', 'enable_offline_payment',
    ];

    /**
     * Configurações do superadmin — era `superadmin::superadmin_settings.edit` (Blade + abas
     * AdminLTE + TinyMCE) e passa a Inertia. Vale para a plataforma inteira: não há
     * `business_id` aqui (ADR 0093 §exceções Superadmin); a trava é `can('superadmin')`.
     */
    public function edit(): InertiaResponse
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        return Inertia::render('superadmin/Configuracoes/Index', [
            'config' => Inertia::defer(fn () => $this->montarConfig()),
        ]);
    }

    private function montarConfig(): array
    {
        $is_demo = env('APP_ENV') == 'demo';
        $sistema = System::whereIn('key', self::SISTEMA)->pluck('value', 'key');

        $valores = [];
        foreach (self::SISTEMA as $k) {
            $valores[$k] = (string) ($sistema[$k] ?? '');
        }
        $semDemo = ['APP_NAME', 'APP_TITLE', 'APP_LOCALE', 'PAYPAL_MODE', 'BACKUP_DISK'];
        foreach (self::ENV_VISIVEIS as $k) {
            $valores[$k] = $is_demo && ! in_array($k, $semDemo, true) ? '' : (string) env($k, '');
        }

        // Só o FATO de estar definido. O valor do segredo nunca sai do servidor.
        $segredos = [];
        foreach (self::SEGREDOS as $k) {
            $segredos[$k] = ! $is_demo && (string) env($k, '') !== '';
        }

        $opcao = fn ($lista) => collect($lista)
            ->map(fn ($label, $v) => ['v' => (string) $v, 'label' => (string) $label])
            ->values()->all();

        return [
            'valores' => $valores,
            'segredos' => $segredos,
            'opcoes' => [
                'moedas' => $opcao($this->businessUtil->allCurrencies()),
                'idiomas' => $opcao(collect(config('constants.langs'))->map(fn ($l) => $l['full_name'])),
                'mail' => $opcao($this->mailDrivers),
                'backup' => $opcao($this->backupDisk),
            ],
            'cron' => (string) $this->businessUtil->getCronJobCommand(),
            'versao' => (string) System::getProperty('superadmin_version'),
        ];
    }

    /** Onde o `update()` grava. Isolado para o teste não tocar o `.env` real. */
    protected function envPath(): string
    {
        return base_path('.env');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function update(Request $request)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {

            //Disable .ENV settings in demo
            if (config('app.env') == 'demo') {
                $output = ['success' => 0,
                    'msg' => 'Feature disabled in demo!!',
                ];

                return back()->with('status', $output);
            }

            $system_settings = $request->only(['app_currency_id', 'invoice_business_name', 'email', 'invoice_business_landmark', 'invoice_business_zip', 'invoice_business_state', 'invoice_business_city', 'invoice_business_country', 'package_expiry_alert_days', 'superadmin_register_tc', 'welcome_email_subject', 'welcome_email_body', 'additional_js', 'additional_css', 'offline_payment_details']);

            //Checkboxes
            $checkboxes = ['enable_business_based_username', 'superadmin_enable_register_tc', 'allow_email_settings_to_businesses', 'enable_new_business_registration_notification', 'enable_new_subscription_notification', 'enable_welcome_email', 'enable_offline_payment'];
            $input = $request->input();
            foreach ($checkboxes as $checkbox) {
                $system_settings[$checkbox] = ! empty($input[$checkbox]) ? 1 : 0;
            }

            foreach ($system_settings as $key => $setting) {
                System::updateOrCreate(
                    ['key' => $key],
                    ['value' => $setting]
                            );
            }

            $env_settings = $request->only(['APP_NAME', 'APP_TITLE',
                'APP_LOCALE', 'MAIL_MAILER', 'MAIL_HOST', 'MAIL_PORT',
                'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_ENCRYPTION',
                'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME', 'STRIPE_PUB_KEY',
                'STRIPE_SECRET_KEY', 'PAYPAL_MODE',
                'PAYPAL_SANDBOX_API_USERNAME',
                'PAYPAL_SANDBOX_API_PASSWORD',
                'PAYPAL_SANDBOX_API_SECRET', 'PAYPAL_LIVE_API_USERNAME',
                'PAYPAL_LIVE_API_PASSWORD', 'PAYPAL_LIVE_API_SECRET',
                'BACKUP_DISK', 'DROPBOX_ACCESS_TOKEN',
                'RAZORPAY_KEY_ID', 'RAZORPAY_KEY_SECRET',
                'PESAPAL_CONSUMER_KEY', 'PESAPAL_CONSUMER_SECRET', 'PESAPAL_LIVE',
                'PUSHER_APP_ID', 'PUSHER_APP_KEY', 'PUSHER_APP_SECRET',
                'PUSHER_APP_CLUSTER', 'GOOGLE_MAP_API_KEY', 'PAYSTACK_SECRET_KEY',
                'PAYSTACK_PUBLIC_KEY', 'FLUTTERWAVE_PUBLIC_KEY',
                'FLUTTERWAVE_SECRET_KEY', 'FLUTTERWAVE_ENCRYPTION_KEY', 'MAPBOX_ACCESS_TOKEN',
            ]);

            // Segredo em branco = "manter o atual": a tela nunca recebe o valor, então campo vazio
            // não pode apagar a senha gravada. Só regrava quando o campo chega preenchido.
            foreach (self::SEGREDOS as $k) {
                if (array_key_exists($k, $env_settings) && (string) $env_settings[$k] === '') {
                    unset($env_settings[$k]);
                }
            }
            // Quebra de linha ou aspas num valor abririam uma linha nova no `.env`.
            $env_settings = array_map(fn ($v) => str_replace(["\r", "\n", '"'], '', (string) $v), $env_settings);

            $env_settings['ALLOW_REGISTRATION'] = ! empty($request->input('ALLOW_REGISTRATION')) ? 'true' : 'false';
            $env_settings['BROADCAST_DRIVER'] = 'pusher';

            $found_envs = [];
            $env_path = $this->envPath();
            $env_lines = file($env_path);
            foreach ($env_settings as $index => $value) {
                foreach ($env_lines as $key => $line) {
                    //Check if present then replace it.
                    if (strpos($line, $index) !== false) {
                        $env_lines[$key] = $index.'="'.$value.'"'.PHP_EOL;

                        $found_envs[] = $index;
                    }
                }
            }

            //Add the missing env settings
            $missing_envs = array_diff(array_keys($env_settings), $found_envs);
            if (! empty($missing_envs)) {
                $missing_envs = array_values($missing_envs);
                foreach ($missing_envs as $k => $key) {
                    if ($k == 0) {
                        $env_lines[] = PHP_EOL.$key.'="'.$env_settings[$key].'"'.PHP_EOL;
                    } else {
                        $env_lines[] = $key.'="'.$env_settings[$key].'"'.PHP_EOL;
                    }
                }
            }

            $env_content = implode('', $env_lines);

            if (is_writable($env_path) && file_put_contents($env_path, $env_content)) {
                $output = ['success' => 1,
                    'msg' => __('lang_v1.success'),
                ];
            } else {
                $output = ['success' => 0, 'msg' => 'Some setting could not be saved, make sure .env file has 644 permission & owned by www-data user'];
            }
        } catch (\Exception $e) {
            // LGPD D7.a — exception->getMessage() pode conter PII cross-tenant
            $this->logEmergencyRedacted($e, 'SuperadminSettingsController');

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()
            ->action([\Modules\Superadmin\Http\Controllers\SuperadminSettingsController::class, 'edit'])
            ->with('status', $output);
    }
}
