<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\ExtratoLancamento;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\RecurringBilling\Dto\StatementLineDto;
use Modules\RecurringBilling\Services\Banking\Drivers\InterStatementDriver;
use Modules\RecurringBilling\Services\Banking\InterBankingClient;

/**
 * Sincroniza extrato dos últimos `$diasRetro` dias pra cada conta com
 * credencial Inter ativa. Idempotente via UNIQUE em
 * `(conta_bancaria_id, idempotency_key)` — re-sync 2× não duplica.
 *
 * Roda diário 07:00 BRT (live) via `app/Console/Kernel.php`. Pode ser
 * disparado on-demand passando `$contaBancariaId` específica.
 *
 * @see US-RB-046
 */
class SyncBankStatementsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 120;

    public function __construct(
        private readonly ?int $contaBancariaId = null,
        private readonly int $diasRetro = 7,
    ) {}

    public function handle(): void
    {
        $query = ContaBancaria::query()
            ->whereNotNull('rb_gateway_credential_id')
            ->whereHas('gatewayCredential', fn ($q) => $q
                ->where('banco', 'inter')
                ->where('ativo', true)
            )
            ->with('gatewayCredential');

        if ($this->contaBancariaId) {
            $query->where('id', $this->contaBancariaId);
        }

        $from = now()->subDays($this->diasRetro)->startOfDay();
        $to   = now()->endOfDay();

        $redactor = app(PiiRedactor::class);

        $query->each(function (ContaBancaria $conta) use ($from, $to, $redactor) {
            try {
                $this->syncConta($conta, $from, $to);
            } catch (\Throwable $e) {
                // LGPD (Wave 10 D7): exception Inter Banking pode trazer trecho do
                // extrato (contraparte_documento = CPF/CNPJ, nome). Redact defensivo.
                Log::warning('SyncBankStatementsJob: erro conta', [
                    'conta_id'    => $conta->id,
                    'business_id' => $conta->business_id,
                    'error'       => $redactor->redact($e->getMessage()),
                ]);
            }
        });
    }

    private function syncConta(ContaBancaria $conta, Carbon $from, Carbon $to): void
    {
        $config = $this->decryptConfig($conta->gatewayCredential->config_json ?? []);
        $client = new InterBankingClient($config, $conta->business_id);
        $driver = new InterStatementDriver($client);

        $linhas = $driver->fetchStatement($from, $to);

        $linhas->each(function (StatementLineDto $linha) use ($conta) {
            ExtratoLancamento::query()->updateOrCreate(
                [
                    'conta_bancaria_id' => $conta->id,
                    'idempotency_key'   => $linha->idempotencyKey,
                ],
                [
                    'business_id'           => $conta->business_id,
                    'data'                  => $linha->data->toDateString(),
                    'valor'                 => $linha->valor,
                    'tipo'                  => $linha->tipo,
                    'descricao'             => $linha->descricao,
                    'contraparte_documento' => $linha->contraparteDocumento,
                    'contraparte_nome'      => $linha->contraparteNome,
                    'raw_payload'           => $linha->raw,
                ]
            );
        });
    }

    private function decryptConfig(array $config): array
    {
        foreach (['client_secret', 'api_key', 'certificado_senha', 'certificado_key_b64'] as $field) {
            if (isset($config[$field])) {
                $config[$field] = Crypt::decryptString($config[$field]);
            }
        }

        return $config;
    }
}
