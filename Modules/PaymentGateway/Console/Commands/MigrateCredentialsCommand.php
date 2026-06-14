<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;

/**
 * Onda 2.5 — ADR 0170.
 *
 * Backfill conservador: lê `rb_boleto_credentials` (legacy RB) e popula
 * `payment_gateway_credentials` (canon PaymentGateway) preservando
 * `business_id`, `banco`→`gateway_key`, `ambiente`, `ativo`, `config_json`,
 * `nome_display`, `conta_bancaria_id`.
 *
 * Vincula `fin_contas_bancarias.payment_gateway_credential_id` (FK nova
 * adicionada na migration 130000) à credencial recém-criada.
 *
 * **DEFAULT É --dry-run** — comando seguro pra inspeção. Use `--apply`
 * pra persistir. Wagner aprova manual antes de cada execução em prod.
 *
 * Idempotente — 2x apply mesmo input NÃO duplica (chave única
 * payment_gateway_credentials(business_id, gateway_key, ambiente)).
 *
 * Multi-tenant Tier 0: opcional `--business={id}` pra restringir.
 *
 * NÃO toca `rb_boleto_credentials` (legado mantido até Onda 6 cleanup).
 *
 * Uso:
 *   php artisan paymentgateway:migrate-credentials                 # dry-run, todos
 *   php artisan paymentgateway:migrate-credentials --apply         # persiste, todos
 *   php artisan paymentgateway:migrate-credentials --business=1    # dry-run só biz=1
 *   php artisan paymentgateway:migrate-credentials --business=1 --apply
 */
class MigrateCredentialsCommand extends Command
{
    protected $signature = 'paymentgateway:migrate-credentials
                            {--business= : Restringir a 1 business_id (default: todos)}
                            {--apply : Persistir mudanças (default: dry-run)}';

    protected $description = 'Backfill rb_boleto_credentials → payment_gateway_credentials (Onda 2.5 ADR 0170)';

    public function handle(): int
    {
        $businessId = $this->option('business');
        $apply = (bool) $this->option('apply');
        $mode = $apply ? 'APPLY' : 'DRY-RUN';

        $this->info("PaymentGateway migrate-credentials — modo {$mode}");
        if ($businessId) {
            $this->line("Restrito a business_id = {$businessId}");
        }

        $query = DB::table('rb_boleto_credentials');
        if ($businessId) {
            $query->where('business_id', $businessId);
        }

        $legacyRows = $query->get();
        $this->info("Encontradas {$legacyRows->count()} credenciais legacy em rb_boleto_credentials.");

        if ($legacyRows->isEmpty()) {
            $this->warn('Nada a migrar.');

            return self::SUCCESS;
        }

        $created = 0;
        $skippedExisting = 0;
        $linkedContas = 0;
        $errors = [];

        foreach ($legacyRows as $row) {
            $gatewayKey = $this->mapBancoToGatewayKey($row->banco);
            if ($gatewayKey === null) {
                $errors[] = "[id={$row->id}] banco='{$row->banco}' não mapeável → pulando";

                continue;
            }

            // Idempotência: tenta achar PG cred com mesma (business, gateway, ambiente).
            // SUPERADMIN: comando de backfill CLI sem sessão; itera credenciais legacy de todos os tenants e checa duplicata por business_id de cada linha.
            $existing = PaymentGatewayCredential::query()
                ->withoutGlobalScopes()
                ->where('business_id', $row->business_id)
                ->where('gateway_key', $gatewayKey)
                ->where('ambiente', $row->ambiente)
                ->first();

            if ($existing) {
                $skippedExisting++;
                $this->line("· biz={$row->business_id} {$gatewayKey}/{$row->ambiente} — já existe (pg_cred_id={$existing->id})");
            } else {
                $this->line("· biz={$row->business_id} {$gatewayKey}/{$row->ambiente} — CRIAR (legacy rb_id={$row->id})");
                if ($apply) {
                    $existing = PaymentGatewayCredential::query()->create([
                        'business_id'       => $row->business_id,
                        'gateway_key'       => $gatewayKey,
                        'ambiente'          => $row->ambiente,
                        'ativo'             => (bool) $row->ativo,
                        'nome_display'      => $row->nome_display,
                        'config_json'       => $this->decodeConfig($row->config_json),
                        'conta_bancaria_id' => $row->conta_bancaria_id,
                        'health_status'     => 'unknown',
                    ]);
                    $created++;
                }
            }

            // Vincula fin_contas_bancarias se aplicável.
            if ($apply && $existing && $row->conta_bancaria_id) {
                $linked = DB::table('fin_contas_bancarias')
                    ->where('id', $row->conta_bancaria_id)
                    ->where('business_id', $row->business_id) // Tier 0
                    ->whereNull('payment_gateway_credential_id')
                    ->update(['payment_gateway_credential_id' => $existing->id]);

                if ($linked > 0) {
                    $linkedContas++;
                }
            }
        }

        $this->newLine();
        $this->info("Resumo {$mode}:");
        $this->line("  · credenciais a criar / criadas: {$created}");
        $this->line("  · já existentes (skip): {$skippedExisting}");
        $this->line("  · fin_contas_bancarias vinculadas: {$linkedContas}");
        if (! empty($errors)) {
            $this->warn('  · erros:');
            foreach ($errors as $err) {
                $this->warn("      {$err}");
            }
        }

        if (! $apply) {
            $this->newLine();
            $this->warn('Modo DRY-RUN — nada persistido. Rerun com --apply pra executar.');
        }

        return self::SUCCESS;
    }

    /**
     * Mapeia `banco` legado RB pro `gateway_key` canônico PG.
     */
    private function mapBancoToGatewayKey(string $banco): ?string
    {
        return match ($banco) {
            'inter' => 'inter',
            'c6'    => 'c6',
            'asaas' => 'asaas',
            default => null,
        };
    }

    /**
     * Decodifica `config_json` do legacy. Pode vir string JSON OU
     * array decodificado se Eloquent castou.
     */
    private function decodeConfig(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
