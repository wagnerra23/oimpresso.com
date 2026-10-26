<?php

declare(strict_types=1);

namespace Modules\Financeiro\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Services\Integrations\PluggyBankSyncService;
use Throwable;

/**
 * `financeiro:pluggy-sync` — importa o extrato das contas vinculadas ao Pluggy para
 * `fin_extrato_lancamentos`, a mesma tabela que o sync Inter alimenta e que a Conciliação
 * lê na origem "api" (ADR 0236). US-FIN-074.
 *
 * Conta vinculada = `fin_contas_bancarias.metadata.pluggy.account_id` preenchido (o widget
 * de conexão que grava esse vínculo é a próxima etapa da US). Com `PLUGGY_ENABLED=false`
 * (default) o comando não chama a API e sai dizendo isso. Idempotente: o upsert usa o
 * UNIQUE (conta_bancaria_id, idempotency_key).
 */
class PluggySyncCommand extends Command
{
    protected $signature = 'financeiro:pluggy-sync
        {--conta= : só esta conta bancária (id em fin_contas_bancarias)}
        {--dias= : janela em dias (default financeiro.pluggy.sync_window_days)}';

    protected $description = 'Importa o extrato das contas vinculadas ao Pluggy (Open Finance) para a conciliação.';

    public function handle(PluggyBankSyncService $sync): int
    {
        if (! config('financeiro.pluggy.enabled', false)) {
            $this->info('Pluggy desligado (PLUGGY_ENABLED=false) — nada sincronizado.');

            return self::SUCCESS;
        }

        $dias = (int) ($this->option('dias') ?: config('financeiro.pluggy.sync_window_days', 30));
        $from = now()->subDays(max(1, $dias))->startOfDay();
        $to = now()->endOfDay();

        // SUPERADMIN: varredura de plataforma deliberada, igual ao SyncBankStatementsJob —
        // o cron sincroniza toda conta vinculada ao Pluggy, de todos os tenants. Cada conta
        // é sincronizada com o business_id DELA (argumento explícito do syncAccount), sem
        // mistura entre tenants.
        $contas = ContaBancaria::query()
            ->withoutGlobalScope(BusinessScopeImpl::class)
            ->whereNotNull('metadata->pluggy->account_id')
            ->when($this->option('conta'), fn ($q, $id) => $q->where('id', (int) $id))
            ->orderBy('id')
            ->get();

        if ($contas->isEmpty()) {
            $this->info('Nenhuma conta vinculada ao Pluggy (metadata.pluggy.account_id).');

            return self::SUCCESS;
        }

        $falhas = 0;
        foreach ($contas as $conta) {
            try {
                $stats = $sync->syncAccount(
                    (int) $conta->business_id,
                    (int) $conta->id,
                    (string) data_get($conta->metadata, 'pluggy.account_id'),
                    $from,
                    $to,
                );
                $this->line(sprintf(
                    'conta %d (biz %d): %d novos · %d atualizados · %d ignorados · %d erros',
                    $conta->id, $conta->business_id,
                    $stats['imported'], $stats['updated'], $stats['skipped'], $stats['errors'],
                ));
                if ($stats['errors'] > 0) {
                    $falhas++;
                }
            } catch (Throwable $e) {
                $falhas++;
                Log::warning('[PluggySync] conta falhou', [
                    'business_id'       => $conta->business_id,
                    'conta_bancaria_id' => $conta->id,
                    'error'             => $e->getMessage(),
                ]);
                $this->error("conta {$conta->id} (biz {$conta->business_id}): {$e->getMessage()}");
            }
        }

        return $falhas > 0 ? self::FAILURE : self::SUCCESS;
    }
}
