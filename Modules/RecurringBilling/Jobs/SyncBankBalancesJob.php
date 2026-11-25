<?php

namespace Modules\RecurringBilling\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;
use Modules\Financeiro\Models\ContaBancaria;
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
        // SUPERADMIN: varredura de plataforma DELIBERADA (US-RB-045) — sincroniza
        // o saldo de TODA conta com credencial de gateway, de todos os tenants,
        // num processo só. Cada iteração usa a credencial da PRÓPRIA conta e
        // escreve `saldo_cached` nela mesma: não há mistura de dado entre tenants.
        //
        // O bypass é declarado AQUI de propósito, em vez de herdado do
        // early-return de `BusinessScopeImpl` (que só não aplica o scope porque
        // não existe sessão em fila). Sem esta linha, "cross-tenant deliberado" é
        // indistinguível de "esqueceram de filtrar". ADR 0093 +
        // memory/proibicoes.md §"Multi-tenant Tier 0". Escopo travado por
        // `SyncBankBalancesJobTest` ("multi-tenant: ...").
        //
        // ⚠️ Este job NÃO é agendado — só roda on-demand via
        // `Modules/RecurringBilling/Console/Commands/SyncBankBalancesCommand.php`.
        $query = ContaBancaria::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            // Credencial Asaas ou Inter ativa do PaymentGateway ligada à conta (onda 4, 2026-11-24).
            ->comCredencialGateway(['asaas', 'inter']);

        if ($this->contaBancariaId) {
            $query->where('id', $this->contaBancariaId);
        }

        $query->each(function (ContaBancaria $conta) {
            try {
                $saldo = $this->fetchSaldo($conta);
                if ($saldo !== null) {
                    $conta->update([
                        'saldo_cached'        => $saldo,
                        'saldo_atualizado_em' => now(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning("SyncBankBalancesJob: erro ao sincronizar conta {$conta->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    private function fetchSaldo(ContaBancaria $conta): ?float
    {
        // Credencial do PaymentGateway ligada à própria conta, com o `config_json` decifrado pelo
        // cast (decisão b1; ondas 1 e 4 da saída de `rb_boleto_credentials`, 2026-11-24).
        $credencial = $conta->credencialGateway(['asaas', 'inter']);
        if ($credencial === null) {
            return null;
        }
        $config = $credencial->config_json ?? [];

        return match ((string) $credencial->gateway_key) {
            'asaas' => $this->fetchAsaasSaldo($config, (string) $credencial->ambiente),
            'inter' => $this->fetchInterSaldo($conta, $config),
            default => null,
        };
    }

    private function fetchAsaasSaldo(array $config, string $ambiente): float
    {
        // `ambiente` é coluna da credencial, não chave do config_json (antes lia do config e
        // caía sempre em produção).
        $baseUrl = $ambiente === 'sandbox'
            ? 'https://sandbox.asaas.com/api/v3'
            : 'https://api.asaas.com/v3';

        $response = Http::withHeaders([
            'access_token' => $config['api_key'],
        ])->get("{$baseUrl}/finance/balance")->throw()->json();

        return (float) ($response['balance'] ?? 0);
    }

    private function fetchInterSaldo(ContaBancaria $conta, array $config): float
    {
        // O InterBankingClient só fala mTLS com o certificado em base64 (formato copiado do RB).
        // Credencial criada direto no PaymentGateway guarda caminho de arquivo; aí a conta é
        // pulada com motivo no log, em vez de cair num índice indefinido.
        // Um nome por linha: a lista numa linha só casa a regra generic-api-key do gitleaks.
        $obrigatorios = [
            'client_id',
            'client_secret',
            'certificado_crt_b64',
            'certificado_key_b64',
        ];
        foreach ($obrigatorios as $campo) {
            if (empty($config[$campo])) {
                throw new \RuntimeException("Credencial Inter do PaymentGateway sem '{$campo}' (o sync de saldo exige o certificado em base64).");
            }
        }

        $client = new InterBankingClient($config, $conta->business_id);

        return $client->getSaldo()['disponivel'];
    }
}
