<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Console\Commands;

use Illuminate\Console\Command;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Drivers\InterDriver;

/**
 * Registra a URL pública do webhook de cobrança no Inter via API.
 *
 * Inter v3 não expõe esse cadastro no portal — `PUT /cobranca/v3/cobrancas/webhook`
 * é a única forma. Sem ele, a infra `InterWebhookController` que recebe em
 * `/paymentgateway/webhooks/inter/{businessId}` fica órfã — Inter não sabe
 * pra onde mandar.
 *
 * Uso:
 *   php artisan paymentgateway:inter:register-webhook --credential=12
 *   php artisan paymentgateway:inter:register-webhook --credential=12 --dry-run
 *   php artisan paymentgateway:inter:register-webhook --credential=12 --url=https://...
 *
 * --url omitido → monta automático via route('paymentgateway.webhooks.inter')
 * usando `business_id` da credencial.
 */
class RegisterInterWebhookCommand extends Command
{
    protected $signature = 'paymentgateway:inter:register-webhook
                            {--credential= : ID de payment_gateway_credentials}
                            {--url= : URL pública (omitir → monta via route helper)}
                            {--dry-run : Mostra a URL e o request sem chamar Inter}';

    protected $description = 'Registra a URL do webhook de cobrança no Inter via PUT /cobranca/v3/cobrancas/webhook.';

    public function handle(InterDriver $driver): int
    {
        $credId = (int) $this->option('credential');
        if ($credId <= 0) {
            $this->error('--credential=<id> é obrigatório (id de payment_gateway_credentials).');

            return self::FAILURE;
        }

        $cred = PaymentGatewayCredential::query()->find($credId);
        if (! $cred) {
            $this->error("PaymentGatewayCredential {$credId} não encontrada.");

            return self::FAILURE;
        }

        if ($cred->gateway_key !== 'inter') {
            $this->error("Credential {$credId} tem gateway_key='{$cred->gateway_key}', esperado 'inter'.");

            return self::FAILURE;
        }

        $url = (string) $this->option('url') ?: $this->montarUrlWebhook($cred);

        $this->line('');
        $this->line('Credential   : ' . ($cred->nome_display ?? "id={$cred->id}") . " (business_id={$cred->business_id}, ambiente={$cred->ambiente})");
        $this->line('URL webhook  : ' . $url);
        $this->line('');

        if ($this->option('dry-run')) {
            $this->warn('--dry-run: NÃO chamando Inter. Use sem a flag pra registrar de fato.');

            return self::SUCCESS;
        }

        $this->line('Chamando PUT /cobranca/v3/cobrancas/webhook ...');

        try {
            $ok = $driver->registerWebhook($cred, $url);
        } catch (\Throwable $e) {
            $this->error('Falhou: ' . $e->getMessage());

            return self::FAILURE;
        }

        if (! $ok) {
            $this->error('Inter retornou erro (não-2xx). Verifique scopes (boleto-cobranca.read+write), certificado mTLS e client_id/secret.');

            return self::FAILURE;
        }

        $this->info('Webhook registrado no Inter com sucesso.');
        $this->line('Pra confirmar: php artisan paymentgateway:inter:register-webhook --credential=' . $credId . ' --dry-run');

        return self::SUCCESS;
    }

    private function montarUrlWebhook(PaymentGatewayCredential $cred): string
    {
        $base = rtrim((string) config('app.url'), '/');

        return $base . '/paymentgateway/webhooks/inter/' . $cred->business_id;
    }
}
