<?php

namespace Modules\RecurringBilling\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\RecurringBilling\Models\BoletoCredential;
use Modules\RecurringBilling\Services\Banking\InterBankingClient;

/**
 * Sincroniza saldo de todos os bancos com API (Asaas, Inter).
 * Roda diário 06:00 como fallback + pode ser disparado on-demand por conta.
 */
class SyncBankBalancesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private readonly ?int $contaBancariaId = null  // null = sincroniza todas
    ) {}

    public function handle(): void
    {
        $query = ContaBancaria::whereNotNull('rb_gateway_credential_id')
            ->with('gatewayCredential');

        if ($this->contaBancariaId) {
            $query->where('id', $this->contaBancariaId);
        }

        $redactor = app(PiiRedactor::class);

        $query->each(function (ContaBancaria $conta) use ($redactor) {
            try {
                $saldo = $this->fetchSaldo($conta);
                if ($saldo !== null) {
                    $conta->update([
                        'saldo_cached'        => $saldo,
                        'saldo_atualizado_em' => now(),
                    ]);
                }
            } catch (\Throwable $e) {
                // LGPD: exception message pode trazer trecho do payload Inter/Asaas
                // (CPF beneficiário, CNPJ pagador). Redact defensivo antes de log.
                Log::warning("SyncBankBalancesJob: erro ao sincronizar conta {$conta->id}", [
                    'error' => $redactor->redact($e->getMessage()),
                ]);
            }
        });
    }

    private function fetchSaldo(ContaBancaria $conta): ?float
    {
        $credential = $conta->gatewayCredential;
        if (! $credential) {
            return null;
        }

        $config = $credential->config_json ?? [];
        foreach (['client_secret', 'api_key', 'certificado_senha', 'certificado_key_b64'] as $field) {
            if (isset($config[$field])) {
                $config[$field] = Crypt::decryptString($config[$field]);
            }
        }

        return match ($credential->banco) {
            'asaas' => $this->fetchAsaasSaldo($config),
            'inter' => $this->fetchInterSaldo($conta, $config),
            default => null,
        };
    }

    private function fetchAsaasSaldo(array $config): float
    {
        $baseUrl = ($config['ambiente'] ?? 'production') === 'sandbox'
            ? 'https://sandbox.asaas.com/api/v3'
            : 'https://api.asaas.com/v3';

        $response = Http::withHeaders([
            'access_token' => $config['api_key'],
        ])->get("{$baseUrl}/finance/balance")->throw()->json();

        return (float) ($response['balance'] ?? 0);
    }

    private function fetchInterSaldo(ContaBancaria $conta, array $config): float
    {
        $client = new InterBankingClient($config, $conta->business_id);

        return $client->getSaldo()['disponivel'];
    }
}
