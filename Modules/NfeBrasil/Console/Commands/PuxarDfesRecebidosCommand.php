<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Jobs\BuscarDfesRecebidosJob;
use Modules\NfeBrasil\Models\NfeCertificado;

/**
 * US-NFE-051 · Command despacha BuscarDfesRecebidosJob pra cada business com cert ativo.
 *
 * Uso:
 *   php artisan nfebrasil:dist-dfe-puxar              # todos businesses com cert ativo
 *   php artisan nfebrasil:dist-dfe-puxar --business=4 # business específico
 *   php artisan nfebrasil:dist-dfe-puxar --sync       # roda sincronamente (não fila)
 *
 * Schedule: daily 06:15 BRT (após jana:health-check).
 */
class PuxarDfesRecebidosCommand extends Command
{
    protected $signature = 'nfebrasil:dist-dfe-puxar
        {--business= : Business ID específico (omite pra todos)}
        {--sync : Executa sincronamente sem fila}';

    protected $description = 'Puxa lote DistribuicaoDFe SEFAZ pra um ou todos businesses com cert ativo.';

    public function handle(): int
    {
        if (! Schema::hasTable('nfe_certificados')) {
            $this->error('Tabela nfe_certificados ausente — rode migrations.');
            return self::FAILURE;
        }

        $businessId = $this->option('business');
        $sync = (bool) $this->option('sync');

        $businessIds = $businessId
            ? [(int) $businessId]
            : NfeCertificado::where('ativo', true)->pluck('business_id')->all();

        if (empty($businessIds)) {
            $this->info('Nenhum business com cert ativo.');
            return self::SUCCESS;
        }

        foreach ($businessIds as $bid) {
            $job = new BuscarDfesRecebidosJob($bid);
            if ($sync) {
                $job->handle(app(\Modules\NfeBrasil\Services\Manifestacao\DistribuicaoDfeService::class));
                $this->line("  business={$bid} processado sync");
            } else {
                dispatch($job);
                $this->line("  business={$bid} dispatched");
            }
        }

        $this->info(sprintf('%d business(es) processados.', count($businessIds)));
        return self::SUCCESS;
    }
}
