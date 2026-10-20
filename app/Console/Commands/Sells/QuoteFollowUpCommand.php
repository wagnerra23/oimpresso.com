<?php

namespace App\Console\Commands\Sells;

use App\Jobs\QuoteFollowUpJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `sells:quote-follow-up` — retorno do orçamento não aberto em 48 h (US-SELL-070 · UC-FLX-04).
 *
 * Despacha UM QuoteFollowUpJob por empresa que tem cotação em rascunho, cada um com o seu
 * business_id (ADR 0093). `--business-id` restringe a uma empresa; `--sync` roda na hora.
 */
class QuoteFollowUpCommand extends Command
{
    protected $signature = 'sells:quote-follow-up
        {--business-id= : Só esta empresa}
        {--sync : Roda na hora em vez de enfileirar}';

    protected $description = 'Cria tarefa de retorno para orçamento enviado há mais de 48 h sem abertura (US-SELL-070)';

    public function handle(): int
    {
        if (! Schema::hasTable('transaction_quote_accesses') || ! Schema::hasTable('crm_schedules')) {
            $this->warn('Tabela transaction_quote_accesses ou crm_schedules ausente — nada a fazer.');

            return self::SUCCESS;
        }

        $empresas = $this->option('business-id') !== null
            ? collect([(int) $this->option('business-id')])->filter(fn ($id) => $id > 0)
            : DB::table('transactions')
                ->where('type', 'sell')->where('status', 'draft')->where('sub_status', 'quotation')
                ->distinct()->pluck('business_id')->map(fn ($id) => (int) $id);

        foreach ($empresas as $businessId) {
            if ($this->option('sync')) {
                $criadas = (new QuoteFollowUpJob($businessId))->handle();
                $this->info("business {$businessId}: {$criadas} retorno(s) criado(s)");
            } else {
                QuoteFollowUpJob::dispatch($businessId);
            }
        }

        return self::SUCCESS;
    }
}
