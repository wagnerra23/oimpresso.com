<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Modules\RecurringBilling\Jobs\SyncBankBalancesJob;

/**
 * US-RB-045 · Command despacha SyncBankBalancesJob (saldo Asaas/Inter → fin_contas_bancarias.saldo_cached).
 *
 * Uso:
 *   php artisan rb:sync-bank-balances              # todas contas com gateway_credential
 *   php artisan rb:sync-bank-balances --conta=42   # conta específica
 *   php artisan rb:sync-bank-balances --sync       # roda sincronamente sem fila
 *
 * Schedule: hourly (env=live) — mantém saldo_cached fresh pra dashboard /financeiro.
 * Sem schedule, saldo_cached fica NULL e dashboard mostra "—" (bug detectado 2026-05-10).
 */
class SyncBankBalancesCommand extends Command
{
    protected $signature = 'rb:sync-bank-balances
        {--conta= : ID de conta_bancaria específica (omite pra todas)}
        {--sync : Executa sincronamente sem fila}';

    protected $description = 'Sincroniza saldo de contas bancárias com gateway (Asaas/Inter) — popula saldo_cached.';

    public function handle(): int
    {
        if (! Schema::hasTable('fin_contas_bancarias')) {
            $this->error('Tabela fin_contas_bancarias ausente — rode migrations Modules/Financeiro.');
            return self::FAILURE;
        }

        $contaId = $this->option('conta');
        $sync = (bool) $this->option('sync');

        $job = new SyncBankBalancesJob(
            contaBancariaId: $contaId ? (int) $contaId : null
        );

        if ($sync) {
            $job->handle();
            $this->info($contaId
                ? "Conta #{$contaId} sincronizada (sync)."
                : 'Todas contas com gateway_credential sincronizadas (sync).');
        } else {
            dispatch($job);
            $this->info($contaId
                ? "Conta #{$contaId} dispatched."
                : 'Job dispatched pra todas contas com gateway_credential.');
        }

        return self::SUCCESS;
    }
}
