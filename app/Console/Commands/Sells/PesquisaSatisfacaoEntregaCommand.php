<?php

namespace App\Console\Commands\Sells;

use App\Jobs\PesquisaSatisfacaoEntregaJob;
use App\Services\Sells\PesquisaEntregaConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `sells:pesquisa-entrega` — pesquisa de satisfação 2 dias depois da entrega (US-SELL-071 · UC-FLX-25).
 *
 * Despacha UM PesquisaSatisfacaoEntregaJob por empresa que ligou a pesquisa
 * (`business.common_settings['sells_pesquisa_entrega']`), cada um com o seu business_id (ADR 0093).
 * `--business-id` restringe a uma empresa; `--sync` roda na hora.
 */
class PesquisaSatisfacaoEntregaCommand extends Command
{
    protected $signature = 'sells:pesquisa-entrega
        {--business-id= : Só esta empresa}
        {--sync : Roda na hora em vez de enfileirar}';

    protected $description = 'Envia a pesquisa de satisfação 2 dias depois da entrega e abre atendimento na nota baixa (US-SELL-071)';

    public function handle(PesquisaEntregaConfig $configs): int
    {
        if (! Schema::hasTable('transaction_satisfaction_surveys')) {
            $this->warn('Tabela transaction_satisfaction_surveys ausente — nada a fazer.');

            return self::SUCCESS;
        }

        $empresas = $this->option('business-id') !== null
            ? collect([(int) $this->option('business-id')])->filter(fn ($id) => $id > 0)
            : DB::table('business')->where('common_settings', 'like', '%'.PesquisaEntregaConfig::CHAVE.'%')
                ->pluck('id')->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $configs->de($id)['ativo']);

        foreach ($empresas as $businessId) {
            if ($this->option('sync')) {
                $r = (new PesquisaSatisfacaoEntregaJob($businessId))->handle($configs);
                $this->info("business {$businessId}: {$r['enviadas']} enviada(s), {$r['nao_enviadas']} não enviada(s), {$r['atendimentos']} atendimento(s)");
            } else {
                PesquisaSatisfacaoEntregaJob::dispatch($businessId);
            }
        }

        return self::SUCCESS;
    }
}
