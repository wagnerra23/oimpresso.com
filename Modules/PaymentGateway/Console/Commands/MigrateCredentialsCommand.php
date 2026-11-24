<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Console\Commands;

use Illuminate\Console\Command;
use Modules\PaymentGateway\Services\CopiaCredencialLegadaRb;

/**
 * Onda 2.5 — ADR 0170.
 *
 * Backfill conservador: lê `rb_boleto_credentials` (legacy RB) e popula
 * `payment_gateway_credentials` (canon PaymentGateway) preservando
 * `business_id`, `banco`→`gateway_key`, `ambiente`, `ativo`, `config_json`
 * (segredos decifrados) e `nome_display`.
 *
 * 2026-11-23 (decisão b1): a regra passou para `CopiaCredencialLegadaRb`, a mesma da migration
 * de dados. Ela decifra os campos que o RB cifra (antes o ciphertext ia para o PG) e NÃO liga a
 * credencial a conta: `rb_boleto_credentials.conta_bancaria_id` é `fin_contas_bancarias.id`, e o
 * do PG é `accounts.id` — copiar trocava o namespace, e credencial ligada à conta muda quem emite.
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
        $businessId = $this->option('business') !== null ? (int) $this->option('business') : null;
        $apply = (bool) $this->option('apply');
        $mode = $apply ? 'APPLY' : 'DRY-RUN';

        $this->info("PaymentGateway migrate-credentials — modo {$mode}");
        if ($businessId !== null) {
            $this->line("Restrito a business_id = {$businessId}");
        }

        $relatorio = (new CopiaCredencialLegadaRb())->executar($businessId, $apply);
        if ($relatorio === []) {
            $this->warn('Nada a migrar.');

            return self::SUCCESS;
        }

        foreach ($relatorio as $l) {
            $this->line("· biz={$l['business_id']} ".($l['gateway_key'] ?? '?')."/{$l['ambiente']} — {$l['acao']} (legacy rb_id={$l['rb_id']})");
        }

        $contagem = array_count_values(array_column($relatorio, 'acao'));
        $this->newLine();
        $this->info("Resumo {$mode}:");
        foreach (['criar', 'decifrar_campos', 'existe', 'sem_mapeamento'] as $acao) {
            $this->line(sprintf('  · %s: %d', $acao, $contagem[$acao] ?? 0));
        }

        if (! $apply) {
            $this->newLine();
            $this->warn('Modo DRY-RUN — nada persistido. Rerun com --apply pra executar.');
        }

        return self::SUCCESS;
    }
}
