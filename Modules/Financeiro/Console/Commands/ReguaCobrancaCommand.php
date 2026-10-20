<?php

declare(strict_types=1);

namespace Modules\Financeiro\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Financeiro\Jobs\ReguaCobrancaJob;
use Modules\Financeiro\Services\ReguaCobrancaConfig;

/**
 * `financeiro:regua-cobranca` — enfileira um `ReguaCobrancaJob` por business com a régua
 * LIGADA (US-FIN-071 · UC-FLX-29). Business sem a chave em `common_settings` (o default) não
 * recebe job. Agendado diariamente pelo `FinanceiroServiceProvider`. Idempotente: o job não
 * repete lembrete na mesma etapa.
 */
class ReguaCobrancaCommand extends Command
{
    protected $signature = 'financeiro:regua-cobranca {--business= : só este business}';

    protected $description = 'Régua de cobrança: lembretes D+1/D+5/D+15 dos títulos a receber vencidos (por business com a régua ligada).';

    public function handle(ReguaCobrancaConfig $configs): int
    {
        $ids = DB::table('business')
            ->where('common_settings', 'like', '%'.ReguaCobrancaConfig::CHAVE.'%')
            ->when($this->option('business'), fn ($q, $b) => $q->where('id', (int) $b))
            ->orderBy('id')
            ->pluck('id')
            ->map('intval');

        $enfileirados = 0;
        foreach ($ids as $biz) {
            if (! $configs->de($biz)['ativo']) {
                continue;
            }
            ReguaCobrancaJob::dispatch($biz);
            $enfileirados++;
        }

        $this->info("Régua de cobrança enfileirada: {$enfileirados} business(es).");

        return self::SUCCESS;
    }
}
